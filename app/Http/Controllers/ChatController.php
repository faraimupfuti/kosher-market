<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $guard = $request->user('customer') ? 'customer' : 'vendor';
        $user = $request->user($guard);

        $conversations = Conversation::with([
            'customer', 'vendor', 'product',
            'messages' => fn ($q) => $q->latest()->limit(1),
        ])
            ->when($guard === 'customer', fn ($q) => $q->where('customer_id', $user->id))
            ->when($guard === 'vendor', fn ($q) => $q->where('vendor_id', $user->id))
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->whereNull('read_at')
                ->when($guard === 'customer', fn ($q) => $q->whereNotNull('sender_vendor_id'))
                ->when($guard === 'vendor', fn ($q) => $q->whereNotNull('sender_customer_id'))
            ])
            ->orderByDesc('last_message_at')->orderByDesc('id')->get();

        return view('chat.index', compact('conversations', 'guard'));
    }

    public function start(Request $request, Vendor $vendor)
    {
        abort_unless($vendor->isSellingEnabled(), 404);
        $customer = $request->user('customer');
        abort_unless($customer, 403);

        $data = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);
        $orderId = !empty($data['order_id']) ? (int) $data['order_id'] : null;
        $productId = !empty($data['product_id']) ? (int) $data['product_id'] : null;

        if ($orderId) {
            $ownsOrder = $customer->orders()->whereKey($orderId)->where('vendor_id', $vendor->id)->exists();
            abort_unless($ownsOrder, 403);
        }

        if ($productId) {
            $product = Product::whereKey($productId)->where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();
        }

        $query = Conversation::where('customer_id', $customer->id)->where('vendor_id', $vendor->id);
        $orderId ? $query->where('order_id', $orderId) : $query->whereNull('order_id');
        $productId ? $query->where('product_id', $productId) : $query->whereNull('product_id');
        $conversation = $query->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'product_id' => $productId,
                'order_id' => $orderId,
                'last_message_at' => now(),
            ]);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->load(['customer', 'vendor', 'product', 'order', 'messages' => fn ($q) => $q->oldest()]);

        $guard = $request->user('customer') ? 'customer' : 'vendor';
        $conversation->messages()->whereNull('read_at')
            ->when($guard === 'customer', fn ($q) => $q->whereNotNull('sender_vendor_id'))
            ->when($guard === 'vendor', fn ($q) => $q->whereNotNull('sender_customer_id'))
            ->update(['read_at' => now()]);

        return view('chat.show', compact('conversation', 'guard'));
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');

        if ($vendor && !$vendor->isSellingEnabled()) {
            abort(403);
        }

        DB::transaction(function () use ($conversation, $validated, $customer, $vendor) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_customer_id' => $customer?->id,
                'sender_vendor_id' => $vendor?->id,
                'body' => trim($validated['body']),
            ]);
            $conversation->update(['last_message_at' => now()]);
        });

        return redirect()->route($vendor ? 'vendor.chat.show' : 'chat.show', $conversation)->with('success', 'Message sent.');
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        abort_unless(
            ($customer && $conversation->customer_id === $customer->id) ||
            ($vendor && $conversation->vendor_id === $vendor->id),
            403
        );
    }
}
