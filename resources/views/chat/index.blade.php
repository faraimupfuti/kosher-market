@extends('themes.xylo.layouts.master')

@section('content')
<section class="breadcrumb-section">
    <div class="container">
        <div class="breadcrumbs"><span>Messages</span></div>
    </div>
</section>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="sec-heading mb-1">Messages</h1>
            <p class="text-muted mb-0">Private conversations between customers and vendors.</p>
        </div>
    </div>

    @forelse($conversations as $conversation)
        @php
            $other = $guard === 'customer' ? $conversation->vendor : $conversation->customer;
            $name = $guard === 'customer' ? ($other->pseudonym ?: $other->name) : $other->name;
            $last = $conversation->messages->first();
        @endphp
        <a href="{{ $guard === 'customer' ? route('chat.show', $conversation) : route('vendor.chat.show', $conversation) }}" class="text-decoration-none text-reset">
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-body d-flex justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px;">
                            {{ strtoupper(substr($name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <strong>{{ $name }}</strong>
                            @if($conversation->order_id)
                                <span class="badge bg-light text-dark ms-2">Order #{{ $conversation->order_id }}</span>
                            @endif
                            <div class="text-muted small text-truncate" style="max-width:70vw;">
                                {{ $last?->body ?? 'No messages yet' }}
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">{{ $conversation->last_message_at?->diffForHumans() }}</small>
                        @if($conversation->unread_count > 0)
                            <span class="badge rounded-pill bg-danger mt-1">{{ $conversation->unread_count }} unread</span>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="alert alert-light border text-center py-5">
            <i class="fa-regular fa-comments fs-2 d-block mb-3"></i>
            No conversations yet. Open a product and message its vendor to start one.
        </div>
    @endforelse
</div>
@endsection
