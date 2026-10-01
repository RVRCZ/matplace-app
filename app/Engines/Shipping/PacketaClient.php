<?php

namespace App\Engines\Shipping;

use App\Models\FarmOrder;
use Illuminate\Support\Facades\Http;

/**
 * Packeta (Zásilkovna). One REST endpoint takes XML; the root element names the operation (createPacket,
 * packetLabelPdf …), there are no paths. Taken over from the old site's PacketaService and extended for parcels
 * abroad: partner carriers, their pickup points and their labels.
 *
 * Password = server side only. The API key is public (it opens the pickup point picker in the browser).
 */
final class PacketaClient implements ShippingCarrier
{
    /** @param  array{api_key?: ?string, api_password?: ?string, eshop?: ?string, endpoint?: string, carriers_url?: string, validate_url?: string, tracking_url?: string, timeout?: int}  $config */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return ! empty($this->config['api_password']);
    }

    public function createPacket(FarmOrder $order): Packet
    {
        $parcel = Parcel::forOrder($order);
        $attributes = array_filter([
            'number' => $parcel->number,
            'name' => $parcel->name,
            'surname' => $parcel->surname,
            'email' => $parcel->email,
            'phone' => $parcel->phone,
            'addressId' => (string) $parcel->addressId,
            'carrierPickupPoint' => $parcel->carrierPickupPoint,
            'currency' => $parcel->currency,
            // paid in advance: never cash on delivery
            'value' => number_format($parcel->value, 2, '.', ''),
            'weight' => number_format($parcel->weightKg, 3, '.', ''),
            'eshop' => (string) ($this->config['eshop'] ?? ''),
            'street' => $parcel->street,
            'houseNumber' => $parcel->houseNumber,
            'city' => $parcel->city,
            'zip' => $parcel->zip,
        ], fn ($v) => $v !== null && $v !== '');

        $result = $this->call('createPacket', ['packetAttributes' => $attributes]);
        $id = (string) ($result->id ?? '');
        if ($id === '') {
            throw new ShippingFailed('Packeta accepted the parcel but returned no id.');
        }

        return new Packet($id, (string) ($result->barcode ?? 'Z'.$id));
    }

    public function labelPdf(string $packetId, bool $carrierLabel = false): string
    {
        $packetId = ltrim(trim($packetId), 'Zz');   // a barcode "Z1330908814" is the id with a letter in front
        if ($carrierLabel) {
            try {
                // a partner carrier prints its own label: first its number of the parcel, then the label for it
                $number = (string) ($this->call('packetCourierNumberV2', ['packetId' => $packetId])->courierNumber ?? '');
                if ($number !== '') {
                    return $this->pdf($this->call('packetCourierLabelPdf', ['packetId' => $packetId, 'courierNumber' => $number]));
                }
            } catch (ShippingFailed) {
                // the partner gives no label over the API (or not yet): Packeta's own label gets the parcel to the depot
            }
        }

        return $this->pdf($this->call('packetLabelPdf', ['packetId' => $packetId, 'format' => 'A6 on A6', 'offset' => '0']));
    }

    public function trackingUrl(string $barcode, ?string $locale = null): string
    {
        $locale = in_array($locale, ['cs', 'en', 'es', 'sk', 'de', 'pl', 'hu', 'ro'], true) ? $locale : 'en';

        return strtr((string) ($this->config['tracking_url'] ?? 'https://tracking.packeta.com/{locale}/?id={barcode}'), ['{locale}' => $locale, '{barcode}' => rawurlencode($barcode)]);
    }

    public function carriers(): array
    {
        if (empty($this->config['api_key'])) {
            throw new ShippingFailed('Packeta is not configured (PACKETA_API_KEY).');
        }
        $url = str_replace('{key}', (string) $this->config['api_key'], (string) $this->config['carriers_url']);
        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 20))->acceptJson()->get($url);
        } catch (\Throwable $e) {
            throw new ShippingFailed('The carrier list could not be reached: '.$e->getMessage());
        }
        $rows = $response->json();
        // the feed is a plain list; an object with "carriers" is accepted as well
        $rows = is_array($rows) && isset($rows['carriers']) ? $rows['carriers'] : $rows;
        if (! $response->successful() || ! is_array($rows) || $rows === []) {
            throw new ShippingFailed('The carrier list answered HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 300));
        }
        $truthy = fn ($v) => $v === true || $v === 1 || in_array(strtolower((string) $v), ['true', '1', 'yes'], true);

        return array_values(array_filter(array_map(function ($c) use ($truthy) {
            if (! is_array($c) || empty($c['id']) || empty($c['country']) || (array_key_exists('available', $c) && ! $truthy($c['available']))) {
                return null;
            }

            return ['id' => (int) $c['id'], 'country' => strtoupper((string) $c['country']), 'type' => $truthy($c['pickupPoints'] ?? false) ? 'point' : 'home', 'name' => (string) ($c['name'] ?? ''),
                'max_kg' => isset($c['maxWeight']) ? (float) $c['maxWeight'] : null, 'api_allowed' => $truthy($c['apiAllowed'] ?? false)];
        }, $rows)));
    }

    public function carriersForCountry(string $country): array
    {
        $country = strtoupper($country);

        return array_values(array_filter($this->carriers(), fn (array $c) => $c['country'] === $country));
    }

    public function refusePoint(array $point, string $country, float $kg): ?string
    {
        if (empty($this->config['api_key']) || empty($this->config['validate_url'])) {
            return null;
        }
        $external = ! empty($point['carrier_id']);
        try {
            $response = Http::timeout(8)->acceptJson()->asJson()->post((string) $this->config['validate_url'], [
                'apiKey' => (string) $this->config['api_key'],
                'point' => $external ? ['carrierId' => (string) $point['carrier_id'], 'carrierPickupPointId' => (string) $point['id']] : ['id' => (string) $point['id']],
                'options' => ['country' => strtolower($country), 'weight' => round(max(0.05, $kg), 2)],
            ]);
        } catch (\Throwable) {
            return null;   // the check is down: the order is not held up, the country the picker reported was compared already
        }
        if (! $response->successful() || ! is_array($response->json())) {
            return $response->status() === 400 ? 'NotFound' : null;
        }
        if ($response->json('isValid') === false) {
            return implode(', ', array_map(fn ($e) => (string) ($e['code'] ?? 'invalid'), (array) $response->json('errors'))) ?: 'invalid';
        }

        return null;
    }

    /**
     * @param  array<string, string|array<string, string>>  $body
     *
     * @throws ShippingFailed
     */
    private function call(string $operation, array $body): \SimpleXMLElement
    {
        if (! $this->available()) {
            throw new ShippingFailed('Packeta is not configured (PACKETA_API_PASS).');
        }
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><'.$operation.'/>');
        $xml->addChild('apiPassword', self::text((string) $this->config['api_password']));
        foreach ($body as $key => $value) {
            if (is_array($value)) {
                $node = $xml->addChild($key);
                foreach ($value as $k => $v) {
                    $node->addChild($k, self::text((string) $v));
                }
            } else {
                $xml->addChild($key, self::text($value));
            }
        }
        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 20))->withBody((string) $xml->asXML(), 'text/xml; charset=utf-8')
                ->post((string) ($this->config['endpoint'] ?? 'https://www.zasilkovna.cz/api/rest'));
        } catch (\Throwable $e) {
            throw new ShippingFailed('Packeta could not be reached: '.$e->getMessage());
        }
        $answer = @simplexml_load_string((string) $response->body());
        if ($answer === false) {
            throw new ShippingFailed('Packeta answered HTTP '.$response->status().' with something that is not XML: '.mb_substr((string) $response->body(), 0, 200));
        }
        if ((string) ($answer->status ?? '') !== 'ok') {
            throw new ShippingFailed(self::fault($answer));
        }

        return $answer->result ?? $answer;
    }

    /** "PacketAttributesFault: … (addressId: is not valid; weight: is too big)" — what the operator needs to fix the order. */
    private static function fault(\SimpleXMLElement $answer): string
    {
        $parts = array_filter([(string) ($answer->fault ?? ''), (string) ($answer->string ?? '')]);
        $fields = [];
        foreach ($answer->detail->attributes->fault ?? [] as $f) {
            $fields[] = trim((string) ($f->name ?? '').': '.(string) ($f->fault ?? ''), ': ');
        }

        return (implode(': ', $parts) ?: 'Packeta refused the request').($fields ? ' ('.implode('; ', $fields).')' : '');
    }

    /** @throws ShippingFailed */
    private function pdf(\SimpleXMLElement $result): string
    {
        $pdf = base64_decode(trim((string) $result), true);
        if ($pdf === false || ! str_starts_with($pdf, '%PDF')) {
            throw new ShippingFailed('Packeta returned a label that is not a PDF.');
        }

        return $pdf;
    }

    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
