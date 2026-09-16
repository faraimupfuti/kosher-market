<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Vendor;
use App\Notifications\ChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $guard = $request->user('customer') ? 'customer' : 'vendor';
        $user = $request->user($guard);
        $conversations = Conversation::with(['customer', 'vendor', 'product', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->when($guard === 'customer', fn ($q) => $q->where('customer_id', $user->id))
            ->when($guard === 'vendor', fn ($q) => $q->where('vendor_id', $user->id))
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->when($guard === 'customer', fn ($q) => $q->whereNotNull('sender_vendor_id'))->when($guard === 'vendor', fn ($q) => $q->whereNotNull('sender_customer_id'))])
            ->orderByDesc('last_message_at')->orderByDesc('id')->get();

        return view('chat.index', compact('conversations', 'guard'));
    }

    public function start(Request $request, Vendor $vendor)
    {
        abort_unless($vendor->isSellingEnabled(), 404);
        $customer = $request->user('customer');
        abort_unless($customer, 403);
        $data = $request->validate(['order_id' => ['nullable', 'integer', 'exists:orders,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id']]);
        $orderId = ! empty($data['order_id']) ? (int) $data['order_id'] : null;
        $productId = ! empty($data['product_id']) ? (int) $data['product_id'] : null;
        if ($orderId) {
            abort_unless($customer->orders()->whereKey($orderId)->where('vendor_id', $vendor->id)->exists(), 403);
        }
        if ($productId) {
            Product::whereKey($productId)->where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();
        }
        $query = Conversation::where('customer_id', $customer->id)->where('vendor_id', $vendor->id);
        $orderId ? $query->where('order_id', $orderId) : $query->whereNull('order_id');
        $productId ? $query->where('product_id', $productId) : $query->whereNull('product_id');
        $conversation = $query->first();
        if (! $conversation) {
            $conversation = Conversation::create(['customer_id' => $customer->id, 'vendor_id' => $vendor->id, 'product_id' => $productId, 'order_id' => $orderId, 'last_message_at' => now()]);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->load(['customer', 'vendor', 'product', 'order', 'messages' => fn ($q) => $q->oldest()]);
        $guard = $request->user('customer') ? 'customer' : 'vendor';
        $this->markInboundRead($conversation, $guard);

        return view('chat.show', compact('conversation', 'guard'));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $guard = $request->user('customer') ? 'customer' : 'vendor';
        $after = max(0, (int) $request->integer('after'));
        $messages = $conversation->messages()->when($after > 0, fn ($q) => $q->where('id', '>', $after))->oldest()->limit(100)->get(['id', 'conversation_id', 'sender_customer_id', 'sender_vendor_id', 'body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'created_at', 'read_at']);
        $this->markInboundRead($conversation, $guard);

        return response()->json(['messages' => $messages, 'last_id' => $messages->last()?->id ?? $after]);
    }

    public function downloadAttachment(Request $request, Message $message)
    {
        $conversation = $message->conversation;
        $this->authorizeConversation($request, $conversation);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path), ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream']);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx'],
        ]);
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        $guard = $customer ? 'customer' : 'vendor';
        abort_if($conversation->isBlockedFor($guard), 403, 'This conversation is blocked.');
        if ($vendor && ! $vendor->isSellingEnabled()) {
            abort(403);
        }
        $message = DB::transaction(function () use ($conversation, $validated, $customer, $vendor, $request) {
            $attachment = $request->file('attachment');
            $path = $attachment?->store('chat-attachments', 'local');

            return tap(Message::create([
                'conversation_id' => $conversation->id,
                'sender_customer_id' => $customer?->id,
                'sender_vendor_id' => $vendor?->id,
                'body' => trim((string) ($validated['body'] ?? '')),
                'attachment_path' => $path,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'attachment_mime' => $attachment?->getMimeType(),
                'attachment_size' => $attachment?->getSize(),
            ]), fn () => $conversation->update(['last_message_at' => now()]));
        });
        $recipient = $customer ? $conversation->vendor : $conversation->customer;
        $senderName = $customer ? ($customer->name ?: 'Customer') : ($vendor->pseudonym ?: $vendor->name ?: 'Vendor');
        $preview = trim((string) ($message->body ?: 'Sent an attachment'));
        $recipient?->notify(new ChatMessageNotification($conversation->id, $senderName, mb_substr($preview, 0, 160)));
        if ($request->expectsJson()) {
            return response()->json(['message' => $message->fresh()], 201);
        }

        return redirect()->route($vendor ? 'vendor.chat.show' : 'chat.show', $conversation)->with('success', 'Message sent.');
    }

    private function markInboundRead(Conversation $conversation, string $guard): void
    {
        $conversation->messages()->whereNull('read_at')->when($guard === 'customer', fn ($q) => $q->whereNotNull('sender_vendor_id'))->when($guard === 'vendor', fn ($q) => $q->whereNotNull('sender_customer_id'))->update(['read_at' => now()]);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        abort_unless(($customer && (int) $conversation->customer_id === (int) $customer->id) || ($vendor && (int) $conversation->vendor_id === (int) $vendor->id), 403);
    }
}
