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
     * One message with its text. Null when it does not exist (any more). `fresh` is what the person wrote, `quoted`
     * the e-mail they quote under it (App\Domain\Mail\MailText), `attachments` their files.
     *
     * @return array{id: string, thread_id: string, from: string, from_name: string, to: string, subject: string, date: ?string, message_id: string, text: string, fresh: string, quoted: string, attachments: list<array{id: string, filename: string, mime: string, size: int}>, unread: bool}|null
     *
     * @throws MailboxFailed
     */
    public function message(string $id): ?array;

    /**
     * The bytes of one attachment of a message; null when it is gone.
     *
     * @throws MailboxFailed
     */
    public function attachment(string $messageId, string $attachmentId): ?string;

    /**
     * A reply in the thread of a message, as plain text, from the mailbox's own address. Returns the id of the sent message.
     *
     * @throws MailboxFailed
     */
    public function reply(string $threadId, string $inReplyTo, string $to, string $subject, string $body): string;

    /** @throws MailboxFailed */
    public function markRead(string $id): void;

    /**
     * Out of the inbox: read and archived, still in "all mail" and in search (the watch does this with our own
     * notifications and with bounce/authentication reports, docs/AU.md).
     *
     * @throws MailboxFailed
     */
    public function archive(string $id): void;
}
