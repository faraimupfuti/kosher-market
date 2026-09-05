@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Messages</h2>
    @forelse($conversations as $conversation)
        @php $other = $guard === 'customer' ? $conversation->vendor : $conversation->customer; @endphp
        <a href="{{ route('chat.show', $conversation) }}" class="text-decoration-none text-reset">
            <div class="card mb-2 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><strong>{{ $guard === 'customer' ? ($other->pseudonym ?: $other->name) : $other->name }}</strong>
                        <div class="text-muted small">{{ optional($conversation->messages->first())->body ?? 'No messages yet' }}</div>
                    </div>
                    <small class="text-muted">{{ $conversation->last_message_at?->diffForHumans() }}</small>
                </div>
            </div>
        </a>
    @empty
        <div class="alert alert-light border">No conversations yet.</div>
    @endforelse
</div>
@endsection
