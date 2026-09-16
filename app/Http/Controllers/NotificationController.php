<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user('customer') ?: $request->user('vendor');
        abort_unless($user, 403);
        $notifications = $user->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $id)
    {
        $user = $request->user('customer') ?: $request->user('vendor');
        abort_unless($user, 403);
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        $conversationId = data_get($notification->data, 'conversation_id');
        if ($conversationId) {
            return redirect()->route($request->user('vendor') ? 'vendor.chat.show' : 'chat.show', $conversationId);
        }

        return back();
    }

    public function readAll(Request $request)
    {
        $user = $request->user('customer') ?: $request->user('vendor');
        abort_unless($user, 403);
        $user->unreadNotifications->markAsRead();

        return back();
    }
}
