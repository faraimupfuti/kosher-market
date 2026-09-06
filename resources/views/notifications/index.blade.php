@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-5" style="max-width:900px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Notifications</h2>
        @if($notifications->whereNull('read_at')->count())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
        @endif
    </div>
    <div class="list-group shadow-sm">
        @forelse($notifications as $notification)
            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'bg-light' }}">@csrf
                <button type="submit" class="w-100 text-start border-0 bg-transparent p-0">
                    <div class="d-flex justify-content-between gap-3"><strong>{{ data_get($notification->data, 'sender_name', 'Notification') }}</strong><small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small></div>
                    <div class="text-muted mt-1">{{ data_get($notification->data, 'preview', 'You have a new notification.') }}</div>
                </button>
            </form>
        @empty
            <div class="list-group-item text-center text-muted py-5">No notifications yet.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $notifications->links() }}</div>
</div>
@endsection
