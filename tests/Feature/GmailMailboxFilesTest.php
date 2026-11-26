<?php

namespace Tests\Feature;

use App\Engines\Mail\GmailMailbox;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The Gmail side of docs/AU.md §7: attachments of a message, the quote split off, the inbox listed in parallel. */
class GmailMailboxFilesTest extends TestCase
{
    private string $tokenPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenPath = sys_get_temp_dir().'/gmail_token_test_'.uniqid().'.json';
        File::put($this->tokenPath, (string) json_encode(['access_token' => 'tok', 'expires_at' => time() + 3600, 'refresh_token' => 'r']));
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenPath);
        parent::tearDown();
    }

    private function mailbox(): GmailMailbox
    {
        return new GmailMailbox(['token_path' => $this->tokenPath, 'client_id' => 'c', 'client_secret' => 's', 'inbox' => 'info@matplace.com']);
    }

    public function test_photos_of_a_phone_mail_are_found_and_the_quote_is_split_off(): void
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $parsed = GmailMailbox::parse(['id' => 'm1', 'payload' => ['mimeType' => 'multipart/mixed', 'headers' => [['name' => 'From', 'value' => 'mila@example.com']], 'parts' => [
            ['mimeType' => 'multipart/alternative', 'parts' => [
                ['mimeType' => 'text/plain', 'body' => ['data' => $b64("Přišel mi pes.\n---------- Původní e-mail ----------\nOd: matplace")]],
            ]],
            ['mimeType' => 'image/jpeg', 'filename' => 'IMG_1.jpg', 'body' => ['attachmentId' => 'att-1', 'size' => 120000]],
            ['mimeType' => 'image/png', 'filename' => '', 'body' => ['attachmentId' => 'att-2', 'size' => 5000]],
        ]]]);
        $this->assertSame('Přišel mi pes.', $parsed['fresh']);
        $this->assertStringStartsWith('---------- Původní e-mail', $parsed['quoted']);
        $this->assertSame([['id' => 'att-1', 'filename' => 'IMG_1.jpg', 'mime' => 'image/jpeg', 'size' => 120000], ['id' => 'att-2', 'filename' => 'priloha.png', 'mime' => 'image/png', 'size' => 5000]], $parsed['attachments']);
    }

    public function test_the_inbox_and_an_attachment_come_from_the_api(): void
    {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'a'], ['id' => 'b']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/a?*' => Http::response(['id' => 'a', 'threadId' => 'ta', 'labelIds' => ['UNREAD'], 'snippet' => 'Ahoj', 'payload' => ['headers' => [['name' => 'From', 'value' => 'Jana <jana@example.com>'], ['name' => 'Subject', 'value' => 'Dotaz']]]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/b?*' => Http::response(['id' => 'b', 'threadId' => 'tb', 'labelIds' => [], 'snippet' => 'x', 'payload' => ['headers' => [['name' => 'From', 'value' => 'petr@example.com']]]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/a/attachments/att-1' => Http::response(['data' => rtrim(strtr(base64_encode('JPEGBYTES'), '+/', '-_'), '='), 'size' => 9]),
        ]);
        $list = $this->mailbox()->recent(10);
        $this->assertSame(['a', 'b'], array_column($list, 'id'));
        $this->assertSame(['jana@example.com', 'Jana', 'Dotaz', true], [$list[0]['from'], $list[0]['from_name'], $list[0]['subject'], $list[0]['unread']]);
        $this->assertFalse($list[1]['unread']);
        $this->assertSame('JPEGBYTES', $this->mailbox()->attachment('a', 'att-1'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok'));
    }
}
