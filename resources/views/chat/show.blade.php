@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-5" style="max-width:900px">
    @php
        $other = $guard === 'customer' ? $conversation->vendor : $conversation->customer;
        $name = $guard === 'customer' ? ($other->pseudonym ?: $other->name) : $other->name;
    @endphp

    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <strong>{{ $name }}</strong>
                @if($conversation->order_id)
                    <span class="badge bg-secondary ms-2">Order #{{ $conversation->order_id }}</span>
                @endif
            </div>
            <a class="btn btn-sm btn-outline-light" href="{{ $guard === 'customer' ? route('chat.index') : route('vendor.chat.index') }}">All messages</a>
        </div>

        <div class="card-body bg-light" style="min-height:420px;max-height:65vh;overflow-y:auto;">
            @forelse($conversation->messages as $message)
                @php
                    $mine = ($guard === 'customer' && $message->sender_customer_id === $conversation->customer_id)
                        || ($guard === 'vendor' && $message->sender_vendor_id === $conversation->vendor_id);
                @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="p-3 rounded-3 {{ $mine ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width:78%">
                        <div style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $message->body }}</div>
                        <small class="opacity-75 d-block mt-1">{{ $message->created_at->format('M j, Y H:i') }}</small>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">Start the conversation.</div>
            @endforelse
        </div>

        <div class="card-footer bg-white">
            <form method="POST" action="{{ $guard === 'customer' ? route('chat.send', $conversation) : route('vendor.chat.send', $conversation) }}" class="d-flex gap-2">
                @csrf
                <input name="body" class="form-control" maxlength="5000" placeholder="Write a message…" required autocomplete="off">
                <button class="btn btn-primary px-4"><i class="fa-regular fa-paper-plane me-1"></i> Send</button>
            </form>
            @if($errors->any())
                <div class="text-danger small mt-2">{{ $errors->first('body') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
