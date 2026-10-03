<?php

namespace App\Engines\Mail;

/**
 * The shared mailbox (info@matplace.com) the admin answers from. Reading what came in, sending a reply in the same
 * thread, marking a message read. Gmail behind it in production, a fake in tests and local work.
 */
interface Mailbox
{
    public function available(): bool;

    /** The address the mailbox belongs to (replies leave from it). */
    public function address(): string;

    /**
     * Newest messages of the inbox.
     *
     * @return list<array{id: string, thread_id: string, from: string, from_name: string, subject: string, date: ?string, snippet: string, unread: bool}>
     *
     * @throws MailboxFailed
     */
    public function recent(int $max = 30, bool $unreadOnly = false): array;

    /**
     * One message with its text. Null when it does not exist (any more).
     *
     * @return array{id: string, thread_id: string, from: string, from_name: string, to: string, subject: string, date: ?string, message_id: string, text: string, unread: bool}|null
     *
     * @throws MailboxFailed
     */
    public function message(string $id): ?array;

    /**
     * A reply in the thread of a message, as plain text, from the mailbox's own address. Returns the id of the sent message.
     *
     * @throws MailboxFailed
     */
    public function reply(string $threadId, string $inReplyTo, string $to, string $subject, string $body): string;

    /** @throws MailboxFailed */
    public function markRead(string $id): void;
}
