<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CustomerNewMessage;
use App\Mail\PrinterNewMessage;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat bound to an inquiry × printer. Access: the customer proves it with the inquiry token in the URL,
 * the printer with a logged-in printer role. Polling (GET since=id) every few seconds; SSE can replace it later.
 */
class ThreadController extends Controller
{
    /** @return array{thread: Thread, side: string} */
    private function resolve(Request $request, Thread $thread): array
    {
        $user = $request->user();
        $token = (string) $request->query('inquiry', $request->input('inquiry', ''));
        if ($user && $user->isPrinter() && $user->printerProfile && $thread->printer_profile_id === $user->printerProfile->id) {
            return ['thread' => $thread, 'side' => 'printer'];
        }
        $inquiry = $thread->inquiry;
        if ($token !== '' && hash_equals($inquiry->token, $token)) {
            return ['thread' => $thread, 'side' => 'customer'];
        }
        if ($user && $inquiry->customer_user_id === $user->id) {
            return ['thread' => $thread, 'side' => 'customer'];
        }
        abort(403);
    }

    public function messages(Request $request, Thread $thread): JsonResponse
    {
        ['side' => $side] = $this->resolve($request, $thread);
        $since = (int) $request->query('since', 0);
        $msgs = $thread->messages()->where('id', '>', $since)->orderBy('id')->limit(200)->get();
        $thread->forceFill([$side === 'customer' ? 'customer_read_at' : 'printer_read_at' => now()])->saveQuietly();

        return response()->json(['messages' => $msgs->map->toArray()->all(), 'header' => $thread->header, 'side' => $side]);
    }

    public function post(Request $request, Thread $thread): JsonResponse
    {
        ['side' => $side] = $this->resolve($request, $thread);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:'.((int) config('inquiries.chat_attachment_max_mb', 20) * 1024), 'mimes:jpg,jpeg,png,webp,gif,pdf,stl,3mf,obj,step,stp,zip'],
        ]);
        if (empty($data['body']) && ! $request->hasFile('file')) {
            return response()->json(['message' => 'empty'], 422);
        }
        $path = null;
        $name = null;
        if ($request->hasFile('file')) {
            $name = mb_substr($request->file('file')->getClientOriginalName(), 0, 255);
            $path = $request->file('file')->store('chat/'.$thread->id, 'local');
        }
        $msg = $thread->post($side, $data['body'] ?? null, $request->user(), $path, $name);
        $this->notifyOtherSide($thread, $side);

        return response()->json(['message' => $msg->toArray()], 201);
    }

    public function attachment(Request $request, Thread $thread, Message $message): StreamedResponse
    {
        $this->resolve($request, $thread);
        abort_unless($message->thread_id === $thread->id && $message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: 'file');
    }

    /** One mail per "burst": only when the other side has not read since the previous unread message. */
    private function notifyOtherSide(Thread $thread, string $fromSide): void
    {
        $inquiry = $thread->inquiry;
        $printer = $thread->printerProfile;
        if ($fromSide === 'customer') {
            if ($thread->unreadFor('printer') !== 1) {
                return;
            }
            $to = $printer->contact_email ?: $printer->user->email;
            Mail::to($to)->locale($printer->user->locale ?: 'cs')->queue(new PrinterNewMessage($inquiry, null, $printer));
        } else {
            if ($thread->unreadFor('customer') !== 1) {
                return;
            }
            if ($inquiry->customer && ! $inquiry->customer->notify_email) {
                return;
            }
            Mail::to($inquiry->contact_email)->locale($inquiry->locale)->queue(new CustomerNewMessage($inquiry, null, $printer));
        }
    }
}
