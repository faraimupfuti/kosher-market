<?php

namespace App\Http\Controllers;

use App\Models\ChatReport;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ChatModerationController extends Controller
{
    private function authorizeConversation(Request $request, Conversation $conversation): string
    {
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        if ($customer && (int) $conversation->customer_id === (int) $customer->id) return 'customer';
        if ($vendor && (int) $conversation->vendor_id === (int) $vendor->id) return 'vendor';
        abort(403);
    }

    public function block(Request $request, Conversation $conversation)
    {
        $guard = $this->authorizeConversation($request, $conversation);
        $conversation->update([$guard === 'customer' ? 'blocked_by_customer_at' : 'blocked_by_vendor_at' => now()]);
        return back()->with('success', 'Conversation blocked.');
    }

    public function unblock(Request $request, Conversation $conversation)
    {
        $guard = $this->authorizeConversation($request, $conversation);
        $conversation->update([$guard === 'customer' ? 'blocked_by_customer_at' : 'blocked_by_vendor_at' => null]);
        return back()->with('success', 'Conversation unblocked.');
    }

    public function report(Request $request, Conversation $conversation)
    {
        $guard = $this->authorizeConversation($request, $conversation);
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:spam,harassment,fraud,illegal_content,other'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        ChatReport::create([
            'conversation_id' => $conversation->id,
            'reporter_customer_id' => $guard === 'customer' ? $request->user('customer')->id : null,
            'reporter_vendor_id' => $guard === 'vendor' ? $request->user('vendor')->id : null,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);
        return back()->with('success', 'Report submitted for moderation.');
    }
}
