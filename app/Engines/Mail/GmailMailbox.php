<?php

namespace App\Engines\Mail;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Gmail over its REST API with the OAuth token of the mailbox's owner (taken over from the old site: a token file
 * with a refresh token and the scopes gmail.modify / gmail.send). The access token is refreshed when it runs out and
 * written back into the file.
 */
final class GmailMailbox implements Mailbox
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me/';

    private ?string $accessToken = null;

    /** @param  array{token_path?: ?string, client_id?: ?string, client_secret?: ?string, inbox?: ?string}  $config */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (string) ($this->config['client_id'] ?? '') !== '' && (string) ($this->config['client_secret'] ?? '') !== '' && is_file((string) ($this->config['token_path'] ?? ''));
    }

    public function address(): string
    {
        return (string) ($this->config['inbox'] ?? 'info@matplace.com');
    }

    public function recent(int $max = 30, bool $unreadOnly = false): array
    {
        $list = $this->get('messages', ['q' => $unreadOnly ? 'is:unread in:inbox' : 'in:inbox', 'maxResults' => max(1, min(100, $max))]);
        $out = [];
        foreach ((array) ($list['messages'] ?? []) as $m) {
            $msg = $this->get('messages/'.$m['id'], ['format' => 'metadata', 'metadataHeaders' => ['From', 'Subject', 'Date']]);
            if (empty($msg['id'])) {
                continue;
            }
            $h = self::headers($msg);
            [$from, $name] = self::address_($h['from'] ?? '');
            $out[] = ['id' => (string) $msg['id'], 'thread_id' => (string) ($msg['threadId'] ?? $msg['id']), 'from' => $from, 'from_name' => $name,
                'subject' => (string) ($h['subject'] ?? ''), 'date' => $h['date'] ?? null, 'snippet' => html_entity_decode((string) ($msg['snippet'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'unread' => in_array('UNREAD', (array) ($msg['labelIds'] ?? []), true)];
        }

        return $out;
    }

    public function message(string $id): ?array
    {
        $msg = $this->get('messages/'.$id, ['format' => 'full'], true);

        return $msg === null ? null : self::parse($msg);
    }

    /**
     * A full message of the API as the admin reads it. Public so it can be checked against a saved answer of the API.
     *
     * @param  array<string, mixed>  $msg
     * @return array{id: string, thread_id: string, from: string, from_name: string, to: string, subject: string, date: ?string, message_id: string, text: string, unread: bool}
     */
    public static function parse(array $msg): array
    {
        $h = self::headers($msg);
        [$from, $name] = self::address_($h['from'] ?? '');
        [$text, $html] = self::bodies((array) ($msg['payload'] ?? []));
        if (trim($text) === '' && $html !== '') {
            $text = html_entity_decode(strip_tags((string) preg_replace(['~<br\s*/?>~i', '~</(p|div|tr|li|h[1-6])>~i', '~<style\b.*?</style>~is', '~<script\b.*?</script>~is'], ["\n", "\n", '', ''], $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)));

        return ['id' => (string) $msg['id'], 'thread_id' => (string) ($msg['threadId'] ?? $msg['id']), 'from' => $from, 'from_name' => $name, 'to' => (string) ($h['to'] ?? ''),
            'subject' => (string) ($h['subject'] ?? ''), 'date' => $h['date'] ?? null, 'message_id' => (string) ($h['message-id'] ?? ''), 'text' => $text,
            'unread' => in_array('UNREAD', (array) ($msg['labelIds'] ?? []), true)];
    }

    public function reply(string $threadId, string $inReplyTo, string $to, string $subject, string $body): string
    {
        $raw = self::raw($this->address(), $to, $subject, $body, $inReplyTo);
        $res = $this->post('messages/send', ['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 'threadId' => $threadId]);
        if (empty($res['id'])) {
            throw new MailboxFailed('Gmail did not accept the reply: '.json_encode($res['error']['message'] ?? $res));
        }

        return (string) $res['id'];
    }

    /** The reply as an RFC 822 message: plain text, in the thread of the original. */
    public static function raw(string $from, string $to, string $subject, string $body, string $inReplyTo): string
    {
        if (! preg_match('/^re:/i', $subject)) {
            $subject = 'Re: '.$subject;
        }
        $lines = [
            'From: matplace <'.$from.'>',
            'To: '.$to,
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        if ($inReplyTo !== '') {
            $lines[] = 'In-Reply-To: '.$inReplyTo;
            $lines[] = 'References: '.$inReplyTo;
        }

        return implode("\r\n", $lines)."\r\n\r\n".quoted_printable_encode(str_replace("\r\n", "\n", $body));
    }

    public function markRead(string $id): void
    {
        $this->post('messages/'.$id.'/modify', ['removeLabelIds' => ['UNREAD']]);
    }

    // ── the API ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null null only when $nullOn404 and the message is gone */
    private function get(string $path, array $query = [], bool $nullOn404 = false): ?array
    {
        $res = Http::timeout(20)->withToken($this->token())->get(self::API.$path, $query);
        if ($nullOn404 && $res->status() === 404) {
            return null;
        }
        if (! $res->ok()) {
            throw new MailboxFailed('Gmail answered '.$res->status().': '.mb_substr((string) $res->json('error.message', $res->body()), 0, 200));
        }

        return (array) $res->json();
    }

    private function post(string $path, array $data): array
    {
        $res = Http::timeout(20)->withToken($this->token())->asJson()->post(self::API.$path, $data);
        if (! $res->ok()) {
            throw new MailboxFailed('Gmail answered '.$res->status().': '.mb_substr((string) $res->json('error.message', $res->body()), 0, 200));
        }

        return (array) $res->json();
    }

    /** A valid access token: the stored one while it lasts, else a fresh one from the refresh token (written back). */
    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $path = (string) ($this->config['token_path'] ?? '');
        if (! $this->available()) {
            throw new MailboxFailed('The mailbox is not set up (GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_TOKEN_PATH).');
        }
        $tok = json_decode((string) File::get($path), true) ?: [];
        if (! empty($tok['access_token']) && (int) ($tok['expires_at'] ?? 0) > time() + 60) {
            return $this->accessToken = (string) $tok['access_token'];
        }
        if (empty($tok['refresh_token'])) {
            throw new MailboxFailed('The token file has no refresh token.');
        }
        $res = Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->config['client_id'], 'client_secret' => $this->config['client_secret'], 'refresh_token' => $tok['refresh_token'], 'grant_type' => 'refresh_token',
        ]);
        if (! $res->ok() || ! $res->json('access_token')) {
            throw new MailboxFailed('Google refused to refresh the mailbox token: '.mb_substr((string) $res->json('error_description', $res->body()), 0, 200));
        }
        $tok['access_token'] = (string) $res->json('access_token');
        $tok['expires_at'] = time() + (int) $res->json('expires_in', 3600);
        File::put($path, (string) json_encode($tok));

        return $this->accessToken = $tok['access_token'];
    }

    /** @return array<string, string> header name (lower case) → value */
    private static function headers(array $msg): array
    {
        $out = [];
        foreach ((array) ($msg['payload']['headers'] ?? []) as $h) {
            $out[strtolower((string) ($h['name'] ?? ''))] = (string) ($h['value'] ?? '');
        }

        return $out;
    }

    /** @return array{0: string, 1: string} address, name */
    private static function address_(string $raw): array
    {
        if (preg_match('/^(.*?)\s*<([^>]+)>\s*$/', $raw, $m)) {
            return [strtolower(trim($m[2])), trim($m[1], " \"'")];
        }

        return [strtolower(trim($raw)), ''];
    }

    /** @return array{0: string, 1: string} text/plain, text/html (the first of each kind, walking the parts) */
    private static function bodies(array $payload): array
    {
        $text = $html = '';
        $data = (string) ($payload['body']['data'] ?? '');
        if ($data !== '') {
            $decoded = (string) base64_decode(strtr($data, '-_', '+/'));
            if (($payload['mimeType'] ?? '') === 'text/html') {
                $html = $decoded;
            } elseif (str_starts_with((string) ($payload['mimeType'] ?? 'text/plain'), 'text/')) {
                $text = $decoded;
            }
        }
        foreach ((array) ($payload['parts'] ?? []) as $part) {
            [$t, $h] = self::bodies((array) $part);
            $text = $text !== '' ? $text : $t;
            $html = $html !== '' ? $html : $h;
        }

        return [$text, $html];
    }
}
