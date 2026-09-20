<?php

namespace App\Domain\Registry;

use Illuminate\Support\Facades\Http;

/** Czech business register (ARES, free public API): does this company number exist and what is the subject called. */
final class Ares
{
    /** @return array{name: string}|null null = not found or the register is unreachable (never blocks saving) */
    public function lookup(string $ico): ?array
    {
        $ico = preg_replace('/\D/', '', $ico);
        if (strlen($ico) < 6 || strlen($ico) > 8) {
            return null;
        }
        $ico = str_pad($ico, 8, '0', STR_PAD_LEFT);
        try {
            $r = Http::timeout(6)->acceptJson()->get('https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/'.$ico);
            if (! $r->successful() || empty($r->json('obchodniJmeno'))) {
                return null;
            }

            return ['name' => mb_substr((string) $r->json('obchodniJmeno'), 0, 200)];
        } catch (\Throwable) {
            return null;
        }
    }
}
