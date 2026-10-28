<?php

namespace App\Domain\Stats;

use Illuminate\Http\Client\Response;

/** A refused Search Console / Google OAuth request. `reason` is Google's machine word (invalid_grant, quotaExceeded…). */
final class SearchConsoleError extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }

    public static function from(Response $r, string $what): self
    {
        $json = (array) $r->json();
        $err = $json['error'] ?? null;
        $reason = is_array($err) ? (string) ($err['errors'][0]['reason'] ?? $err['status'] ?? 'error') : (string) ($err ?? 'http_'.$r->status());
        $text = is_array($err) ? (string) ($err['message'] ?? '') : (string) ($json['error_description'] ?? '');

        return new self($reason, trim($what.': '.($text ?: mb_substr($r->body(), 0, 300))), $r->status());
    }

    /** The stored grant no longer works (revoked, password changed…): the account has to be connected again. */
    public function needsReconnect(): bool
    {
        return in_array($this->reason, ['invalid_grant', 'unauthorized_client', 'not_connected'], true) || $this->status === 401;
    }
}
