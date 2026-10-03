<?php

namespace App\Engines\Mail;

/** Tests and local work: a handful of messages in memory, replies are remembered instead of sent. */
final class FakeMailbox implements Mailbox
{
    /** @var array<string, array<string, mixed>> id → message (the fields of message()) */
    public static array $messages = [];

    /** @var list<array{thread_id: string, in_reply_to: string, to: string, subject: string, body: string}> */
    public static array $replies = [];

    public static bool $available = true;

    public static function reset(): void
    {
        self::$messages = [];
        self::$replies = [];
        self::$available = true;
    }

    /** @param  array<string, mixed>  $fields */
    public static function put(string $id, array $fields): void
    {
        self::$messages[$id] = $fields + ['id' => $id, 'thread_id' => 't-'.$id, 'from' => 'nekdo@example.com', 'from_name' => '', 'to' => 'info@matplace.com',
            'subject' => '(bez předmětu)', 'date' => now()->toRfc2822String(), 'message_id' => '<'.$id.'@example.com>', 'text' => '', 'unread' => true];
    }

    public function available(): bool
    {
        return self::$available;
    }

    public function address(): string
    {
        return 'info@matplace.com';
    }

    public function recent(int $max = 30, bool $unreadOnly = false): array
    {
        $this->up();
        $out = [];
        foreach (array_reverse(self::$messages) as $m) {
            if ($unreadOnly && ! $m['unread']) {
                continue;
            }
            $out[] = ['id' => $m['id'], 'thread_id' => $m['thread_id'], 'from' => $m['from'], 'from_name' => $m['from_name'], 'subject' => $m['subject'], 'date' => $m['date'],
                'snippet' => mb_substr((string) $m['text'], 0, 120), 'unread' => (bool) $m['unread']];
        }

        return array_slice($out, 0, $max);
    }

    public function message(string $id): ?array
    {
        $this->up();

        return self::$messages[$id] ?? null;
    }

    public function reply(string $threadId, string $inReplyTo, string $to, string $subject, string $body): string
    {
        $this->up();
        self::$replies[] = compact('threadId', 'inReplyTo', 'to', 'subject', 'body');

        return 'sent-'.count(self::$replies);
    }

    public function markRead(string $id): void
    {
        $this->up();
        if (isset(self::$messages[$id])) {
            self::$messages[$id]['unread'] = false;
        }
    }

    private function up(): void
    {
        if (! self::$available) {
            throw new MailboxFailed('The mailbox is not connected.');
        }
    }
}
