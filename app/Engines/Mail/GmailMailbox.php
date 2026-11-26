<?php

namespace App\Engines\Mail;

use App\Domain\Mail\MailText;
use Illuminate\Http\Client\Response;
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
        $ids = array_column((array) ($list['messages'] ?? []), 'id');
        // the headers of all of them at once, ten requests at a time (one by one the inbox page took ten seconds)
        $token = $this->token();
        $bodies = [];
        foreach (array_chunk($ids, 10) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(fn ($id) => $pool->as((string) $id)->timeout(20)->withToken($token)
                ->get(self::API.'messages/'.$id.'?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Date'), $chunk));
            foreach ($chunk as $id) {
                $r = $responses[$id] ?? null;
                $bodies[$id] = $r instanceof Response && $r->ok() ? (array) $r->json() : $this->get('messages/'.$id, ['format' => 'metadata', 'metadataHeaders' => ['From', 'Subject', 'Date']], true);
            }
        }
        $out = [];
        foreach ($ids as $id) {
            $msg = $bodies[$id] ?? null;
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
        $text = MailText::tidy(str_replace("\r\n", "\n", $text));
        $split = MailText::split($text);

        return ['id' => (string) $msg['id'], 'thread_id' => (string) ($msg['threadId'] ?? $msg['id']), 'from' => $from, 'from_name' => $name, 'to' => (string) ($h['to'] ?? ''),
            'subject' => (string) ($h['subject'] ?? ''), 'date' => $h['date'] ?? null, 'message_id' => (string) ($h['message-id'] ?? ''), 'text' => $text,
            'fresh' => $split['fresh'], 'quoted' => $split['quoted'], 'attachments' => self::attachments((array) ($msg['payload'] ?? [])),
            'unread' => in_array('UNREAD', (array) ($msg['labelIds'] ?? []), true)];
    }

    public function attachment(string $messageId, string $attachmentId): ?string
    {
        $res = $this->get('messages/'.$messageId.'/attachments/'.$attachmentId, [], true);
        $data = (string) ($res['data'] ?? '');

        return $data === '' ? null : (string) base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * The files of a message: photos a customer sent, PDFs, inline pictures (those count too: phones put photos inline).
     *
     * @return list<array{id: string, filename: string, mime: string, size: int}>
     */
    private static function attachments(array $payload): array
    {
        $out = [];
        $id = (string) ($payload['body']['attachmentId'] ?? '');
        $mime = strtolower((string) ($payload['mimeType'] ?? ''));
        if ($id !== '' && ! str_starts_with($mime, 'multipart/')) {
            $name = (string) ($payload['filename'] ?? '');
            $out[] = ['id' => $id, 'filename' => $name !== '' ? $name : 'priloha.'.(explode('/', $mime)[1] ?? 'bin'), 'mime' => $mime, 'size' => (int) ($payload['body']['size'] ?? 0)];
        }
        foreach ((array) ($payload['parts'] ?? []) as $part) {
            $out = array_merge($out, self::attachments((array) $part));
        }

        return $out;
    }

    public function reply(string $threadId, string $inReplyTo, string $to, string $subject, string $body, array $files = []): string
    {
        $raw = self::raw($this->address(), $to, $subject, $body, $inReplyTo, $files);
        $res = $this->post('messages/send', ['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 'threadId' => $threadId]);
        if (empty($res['id'])) {
            throw new MailboxFailed('Gmail did not accept the reply: '.json_encode($res['error']['message'] ?? $res));
        }

        return (string) $res['id'];
    }

    /**
     * The reply as an RFC 822 message: plain text, in the thread of the original; with files a multipart/mixed message
     * whose first part is that text (docs/AU.md §8).
     *
     * @param  list<array{name: string, mime: string, path: string}>  $files
     */
    public static function raw(string $from, string $to, string $subject, string $body, string $inReplyTo, array $files = []): string
    {
        if (! preg_match('/^re:/i', $subject)) {
            $subject = 'Re: '.$subject;
        }
        $lines = [
            'From: matplace <'.$from.'>',
            'To: '.$to,
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
        ];
        if ($inReplyTo !== '') {
            $lines[] = 'In-Reply-To: '.$inReplyTo;
            $lines[] = 'References: '.$inReplyTo;
        }
        $text = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode(str_replace("\r\n", "\n", $body));
        $files = array_values(array_filter($files, fn (array $f) => is_file((string) ($f['path'] ?? ''))));
        if ($files === []) {
            return implode("\r\n", $lines)."\r\n".$text;
        }
        $boundary = 'mp-'.bin2hex(random_bytes(12));
        $lines[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
        $out = implode("\r\n", $lines)."\r\n\r\n--".$boundary."\r\n".$text;
        foreach ($files as $f) {
            $name = '=?UTF-8?B?'.base64_encode((string) $f['name']).'?=';
            $out .= "\r\n--".$boundary."\r\nContent-Type: ".($f['mime'] ?: 'application/octet-stream').'; name="'.$name."\"\r\nContent-Disposition: attachment; filename=\"".$name."\"\r\n"
                ."Content-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string) file_get_contents($f['path'])), 76, "\r\n");
        }

        return $out."\r\n--".$boundary."--\r\n";
    }

    public function markRead(string $id): void
    {
        $this->post('messages/'.$id.'/modify', ['removeLabelIds' => ['UNREAD']]);
    }

    public function archive(string $id): void
    {
        $this->post('messages/'.$id.'/modify', ['removeLabelIds' => ['INBOX', 'UNREAD']]);
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
