<?php

namespace App\Domain\Mail;

/**
 * What a person wrote in a reply, apart from the e-mail they quote under it (docs/AU.md §7). Mail programs mark the
 * quoted part in their own way: Seznam "---------- Původní e-mail ----------", Gmail "Dne … napsal(a):" / "On … wrote:",
 * Outlook "-----Original Message-----" or a block "Od: … Odesláno: …", and older ones lines starting with ">".
 */
final class MailText
{
    private const MARKERS = [
        '/^-{3,}\s*(Původní|Puvodni|Původní zpráva|Original|Forwarded|Přeposlaná|Mensaje original|Mensaje reenviado)[^\n]*-{0,}\s*$/imu',
        '/^-{3,}\s*Original Message\s*-{3,}\s*$/im',
        '/^(Dne|On|El)\s.{4,200}(napsal|napsala|napsal\(a\)|wrote|escribió)\s*:?\s*$/imu',
        '/^(Od|From|De):\s.+\n(.*\n){0,3}?(Odesláno|Sent|Enviado|Datum|Date|Fecha):\s.+$/imu',
        '/^_{10,}\s*$/m',
    ];

    /** @return array{fresh: string, quoted: string} */
    public static function split(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $cut = null;
        foreach (self::MARKERS as $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE) && ($cut === null || $m[0][1] < $cut)) {
                $cut = (int) $m[0][1];
            }
        }
        // a block of "> …" lines counts too
        if (preg_match('/^>.*$/m', $text, $m, PREG_OFFSET_CAPTURE) && ($cut === null || $m[0][1] < $cut)) {
            $cut = (int) $m[0][1];
        }
        if ($cut === null || trim(substr($text, 0, $cut)) === '') {
            return ['fresh' => self::tidy($text), 'quoted' => ''];
        }

        return ['fresh' => self::tidy(substr($text, 0, $cut)), 'quoted' => self::tidy(substr($text, $cut))];
    }

    /** Spaces at line ends and runs of empty lines (HTML mails turned into text are full of them) out. */
    public static function tidy(string $text): string
    {
        $text = (string) preg_replace('/[ \t\x{00A0}]+$/mu', '', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** The language a person wrote in, as well as a short text tells: cs | es | en (cs when unsure). */
    public static function locale(string $text): string
    {
        $t = mb_strtolower($text);
        if (preg_match('/[ěščřžůť]/u', $t)) {
            return 'cs';
        }
        if (preg_match('/[ñ¿¡]|\b(hola|gracias|pedido|buenos|buenas|saludos|quiero|puedo|tengo)\b/u', $t)) {
            return 'es';
        }
        if (preg_match('/\b(hello|hi|thanks|thank you|please|order|would|could|regards)\b/', $t) && ! preg_match('/[áéíóúý]/u', $t)) {
            return 'en';
        }

        return 'cs';
    }
}
