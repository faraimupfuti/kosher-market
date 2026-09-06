@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-4" style="max-width:900px">
    @php
        $other = $guard === 'customer' ? $conversation->vendor : $conversation->customer;
        $name = $guard === 'customer' ? ($other->pseudonym ?: $other->name) : $other->name;
        $messagesUrl = $guard === 'customer' ? route('chat.messages', $conversation) : route('vendor.chat.messages', $conversation);
        $sendUrl = $guard === 'customer' ? route('chat.send', $conversation) : route('vendor.chat.send', $conversation);
    @endphp

    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <strong>{{ $name }}</strong>
                @if($conversation->product)
                    <span class="badge bg-secondary ms-2">{{ $conversation->product->translation->name ?? 'Product' }}</span>
                @endif
                @if($conversation->order_id)
                    <span class="badge bg-secondary ms-2">Order #{{ $conversation->order_id }}</span>
                @endif
            </div>
            <a class="btn btn-sm btn-outline-light" href="{{ $guard === 'customer' ? route('chat.index') : route('vendor.chat.index') }}">All messages</a>
        </div>

        <div id="chat-messages" class="card-body bg-light" style="min-height:420px;max-height:65vh;overflow-y:auto;">
            @forelse($conversation->messages as $message)
                @php
                    $mine = ($guard === 'customer' && $message->sender_customer_id === $conversation->customer_id)
                        || ($guard === 'vendor' && $message->sender_vendor_id === $conversation->vendor_id);
                @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}" data-message-id="{{ $message->id }}">
                    <div class="p-3 rounded-3 {{ $mine ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width:78%">
                        <div style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $message->body }}</div>
                        <small class="opacity-75 d-block mt-1">{{ $message->created_at->format('M j, Y H:i') }}</small>
                    </div>
                </div>
            @empty
                <div id="empty-chat" class="text-center text-muted py-5">Start the conversation.</div>
            @endforelse
        </div>

        <div class="card-footer bg-white">
            <div id="typing-indicator" class="small text-muted mb-2" style="display:none">Typing…</div>
            <form id="chat-form" method="POST" action="{{ $sendUrl }}" class="d-flex gap-2">
                @csrf
                <input id="chat-input" name="body" class="form-control" maxlength="5000" placeholder="Write a message…" required autocomplete="off">
                <button id="chat-send" class="btn btn-primary px-4"><i class="fa-regular fa-paper-plane me-1"></i> Send</button>
            </form>
            @if($errors->any())
                <div class="text-danger small mt-2">{{ $errors->first('body') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    const box = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const send = document.getElementById('chat-send');
    const typing = document.getElementById('typing-indicator');
    const messagesUrl = @json($messagesUrl);
    let lastId = Math.max(0, ...Array.from(box.querySelectorAll('[data-message-id]')).map(el => Number(el.dataset.messageId) || 0));
    let timer;

    function appendMessage(message) {
        if (box.querySelector('[data-message-id="' + message.id + '"]')) return;
        const mine = @json($guard === 'customer')
            ? Number(message.sender_customer_id) === Number(@json($conversation->customer_id))
            : Number(message.sender_vendor_id) === Number(@json($conversation->vendor_id));
        const row = document.createElement('div');
        row.className = 'd-flex mb-3 ' + (mine ? 'justify-content-end' : 'justify-content-start');
        row.dataset.messageId = message.id;
        const bubble = document.createElement('div');
        bubble.className = 'p-3 rounded-3 ' + (mine ? 'bg-primary text-white' : 'bg-white border');
        bubble.style.maxWidth = '78%';
        const body = document.createElement('div');
        body.style.cssText = 'white-space:pre-wrap;overflow-wrap:anywhere;';
        body.textContent = message.body;
        const time = document.createElement('small');
        time.className = 'opacity-75 d-block mt-1';
        time.textContent = new Date(message.created_at).toLocaleString();
        bubble.append(body, time);
        row.appendChild(bubble);
        const empty = document.getElementById('empty-chat');
        if (empty) empty.remove();
        box.appendChild(row);
    }

    function scrollBottom() { box.scrollTop = box.scrollHeight; }

    async function poll() {
        try {
            const response = await fetch(messagesUrl + '?after=' + encodeURIComponent(lastId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!response.ok) return;
            const data = await response.json();
            (data.messages || []).forEach(message => {
                appendMessage(message);
                lastId = Math.max(lastId, Number(message.id) || 0);
            });
            if ((data.messages || []).length) scrollBottom();
        } catch (e) {
            // A temporary network failure should not break the chat loop.
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        send.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: new URLSearchParams(new FormData(form)),
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('send failed');
            const data = await response.json();
            if (data.message) {
                appendMessage(data.message);
                lastId = Math.max(lastId, Number(data.message.id) || 0);
                input.value = '';
                scrollBottom();
            }
        } catch (e) {
            form.submit();
        } finally {
            send.disabled = false;
            input.focus();
        }
    });

    let typingTimeout;
    input.addEventListener('input', function () {
        typing.style.display = input.value.length ? 'block' : 'none';
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => { typing.style.display = 'none'; }, 800);
    });

    scrollBottom();
    poll();
    timer = setInterval(poll, 3000);
    window.addEventListener('beforeunload', () => clearInterval(timer));
})();
</script>
@endsection
