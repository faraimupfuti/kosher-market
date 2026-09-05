@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:900px">
    <div class="card shadow-sm">
        <div class="card-header"><strong>{{ $guard === 'customer' ? ($conversation->vendor->pseudonym ?: $conversation->vendor->name) : $conversation->customer->name }}</strong></div>
        <div class="card-body" style="min-height:420px">
            @foreach($conversation->messages as $message)
                @php $mine = ($guard === 'customer' && $message->sender_customer_id === $conversation->customer_id) || ($guard === 'vendor' && $message->sender_vendor_id === $conversation->vendor_id); @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="p-2 rounded border {{ $mine ? 'bg-primary text-white' : 'bg-light' }}" style="max-width:75%">
                        <div>{{ $message->body }}</div>
                        <small class="opacity-75">{{ $message->created_at->format('M j, H:i') }}</small>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer">
            <form method="POST" action="{{ route('chat.send', $conversation) }}" class="d-flex gap-2">
                @csrf
                <input name="body" class="form-control" maxlength="5000" placeholder="Write a message…" required autocomplete="off">
                <button class="btn btn-primary">Send</button>
            </form>
        </div>
    </div>
</div>
@endsection
