<?php

namespace App\Engines\Social;

/** Tests and local work without a Meta account: remembers what it was asked to publish and report. */
final class FakeMetaClient implements MetaClient
{
    /** @var list<array{platform: string, message: string, link: ?string, image: ?string}> */
    public static array $posts = [];

    /** @var list<array{event: string, id: string, user: array, data: array, url: ?string}> */
    public static array $conversions = [];

    /** Set to a text: the next post fails with it (as Meta would answer). */
    public static ?string $refuse = null;

    public static function reset(): void
    {
        self::$posts = [];
        self::$conversions = [];
        self::$refuse = null;
    }

    public function available(): bool
    {
        return true;
    }

    public function postToPage(string $message, ?string $link = null, ?string $imageUrl = null): string
    {
        return $this->post('facebook', $message, $link, $imageUrl);
    }

    public function postToInstagram(string $imageUrl, string $caption): string
    {
        return $this->post('instagram', $caption, null, $imageUrl);
    }

    public function postVideoToPage(string $path, string $title, string $description): string
    {
        return $this->post('facebook_video', $title."\n\n".$description, null, $path);
    }

    public function postReelToInstagram(string $videoUrl, string $caption): string
    {
        return $this->post('instagram_reel', $caption, null, $videoUrl);
    }

    public function campaigns(string $period = 'last_7d'): array
    {
        $scale = $period === 'last_30d' ? 4 : 1;

        return [
            ['id' => '1201', 'name' => 'Modely k tisku CZ', 'status' => 'ACTIVE', 'spend' => 350.0 * $scale, 'impressions' => 12000 * $scale, 'clicks' => 240 * $scale, 'currency' => 'CZK'],
            ['id' => '1202', 'name' => 'Nástroje EN', 'status' => 'PAUSED', 'spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'currency' => 'CZK'],
        ];
    }

    public function sendConversion(string $event, string $eventId, array $user, array $data = [], ?string $url = null): bool
    {
        // what would leave: the e-mail only as its hash
        $user['email'] = ! empty($user['email']) ? hash('sha256', mb_strtolower(trim((string) $user['email']))) : null;
        self::$conversions[] = ['event' => $event, 'id' => $eventId, 'user' => $user, 'data' => $data, 'url' => $url];

        return true;
    }

    private function post(string $platform, string $message, ?string $link, ?string $image): string
    {
        if (self::$refuse !== null) {
            throw new MetaFailed(self::$refuse);
        }
        self::$posts[] = ['platform' => $platform, 'message' => $message, 'link' => $link, 'image' => $image];

        return $platform.'_'.count(self::$posts);
    }
}
