@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-4" style="max-width:900px">
    @php
        $other = $guard === 'customer' ? $conversation->vendor : $conversation->customer;
        $name = $guard === 'customer' ? ($other->pseudonym ?: $other->name) : $other->name;
        $messagesUrl = $guard === 'customer' ? route('chat.messages', $conversation) : route('vendor.chat.messages', $conversation);
        $sendUrl = $guard === 'customer' ? route('chat.send', $conversation) : route('vendor.chat.send', $conversation);
        $downloadRoute = $guard === 'customer' ? 'chat.attachments.download' : 'vendor.chat.attachments.download';
    @endphp
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <strong>{{ $name }}</strong>
                @if($conversation->product)<span class="badge bg-secondary ms-2">{{ $conversation->product->translation->name ?? 'Product' }}</span>@endif
                @if($conversation->order_id)<span class="badge bg-secondary ms-2">Order #{{ $conversation->order_id }}</span>@endif
            </div>
            <a class="btn btn-sm btn-outline-light" href="{{ $guard === 'customer' ? route('chat.index') : route('vendor.chat.index') }}">All messages</a>
        </div>
        <div id="chat-messages" class="card-body bg-light" style="min-height:420px;max-height:65vh;overflow-y:auto;">
            @forelse($conversation->messages as $message)
                @php $mine = ($guard === 'customer' && $message->sender_customer_id === $conversation->customer_id) || ($guard === 'vendor' && $message->sender_vendor_id === $conversation->vendor_id); @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}" data-message-id="{{ $message->id }}">
                    <div class="p-3 rounded-3 {{ $mine ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width:78%">
                        @if($message->body)<div style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $message->body }}</div>@endif
                        @if($message->attachment_path)
                            <a class="d-inline-block mt-2 {{ $mine ? 'text-white' : '' }}" href="{{ route($downloadRoute, $message) }}"><i class="fa-solid fa-paperclip me-1"></i>{{ $message->attachment_name ?: 'Attachment' }}</a>
                            @if($message->attachment_size)<small class="opacity-75 ms-1">({{ number_format($message->attachment_size / 1024, 1) }} KB)</small>@endif
                        @endif
                        <small class="opacity-75 d-block mt-1">{{ $message->created_at->format('M j, Y H:i') }}</small>
                    </div>
                </div>
            @empty
                <div id="empty-chat" class="text-center text-muted py-5">Start the conversation.</div>
            @endforelse
        </div>
        <div class="card-footer bg-white">
            <form id="chat-form" method="POST" action="{{ $sendUrl }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                @csrf
                <label class="btn btn-outline-secondary mb-0" title="Attach a file"><i class="fa-solid fa-paperclip"></i><input id="chat-attachment" type="file" name="attachment" hidden accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"></label>
                <input id="chat-input" name="body" class="form-control" maxlength="5000" placeholder="Write a message…" autocomplete="off">
                <button id="chat-send" class="btn btn-primary px-4"><i class="fa-regular fa-paper-plane me-1"></i> Send</button>
            </form>
            <div id="attachment-name" class="small text-muted mt-1"></div>
            @if($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    const box=document.getElementById('chat-messages'),form=document.getElementById('chat-form'),input=document.getElementById('chat-input'),file=document.getElementById('chat-attachment'),fileName=document.getElementById('attachment-name'),send=document.getElementById('chat-send');
    const messagesUrl=@json($messagesUrl), downloadBase=@json(url('/'.($guard === 'customer' ? 'chat/attachments' : 'vendor/chat/attachments')));
    let lastId=Math.max(0,...Array.from(box.querySelectorAll('[data-message-id]')).map(el=>Number(el.dataset.messageId)||0));
    function appendMessage(m){if(box.querySelector('[data-message-id="'+m.id+'"]'))return;const mine=@json($guard==='customer')?Number(m.sender_customer_id)===Number(@json($conversation->customer_id)):Number(m.sender_vendor_id)===Number(@json($conversation->vendor_id));const row=document.createElement('div');row.className='d-flex mb-3 '+(mine?'justify-content-end':'justify-content-start');row.dataset.messageId=m.id;const bubble=document.createElement('div');bubble.className='p-3 rounded-3 '+(mine?'bg-primary text-white':'bg-white border');bubble.style.maxWidth='78%';if(m.body){const body=document.createElement('div');body.style.cssText='white-space:pre-wrap;overflow-wrap:anywhere;';body.textContent=m.body;bubble.appendChild(body);}if(m.attachment_path){const link=document.createElement('a');link.className='d-inline-block mt-2 '+(mine?'text-white':'');link.href=downloadBase+'/'+m.id;link.textContent='📎 '+(m.attachment_name||'Attachment');bubble.appendChild(link);}const time=document.createElement('small');time.className='opacity-75 d-block mt-1';time.textContent=new Date(m.created_at).toLocaleString();bubble.appendChild(time);row.appendChild(bubble);document.getElementById('empty-chat')?.remove();box.appendChild(row);}
    async function poll(){try{const r=await fetch(messagesUrl+'?after='+lastId,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});if(!r.ok)return;const d=await r.json();(d.messages||[]).forEach(m=>{appendMessage(m);lastId=Math.max(lastId,Number(m.id)||0);});if((d.messages||[]).length)box.scrollTop=box.scrollHeight;}catch(e){}}
    form.addEventListener('submit',async e=>{e.preventDefault();if(!input.value.trim()&&!file.files.length)return;send.disabled=true;try{const r=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form),credentials:'same-origin'});if(!r.ok)throw new Error();const d=await r.json();if(d.message){appendMessage(d.message);lastId=Math.max(lastId,Number(d.message.id)||0);input.value='';file.value='';fileName.textContent='';box.scrollTop=box.scrollHeight;}}catch(err){form.submit();}finally{send.disabled=false;input.focus();}});
    file.addEventListener('change',()=>{fileName.textContent=file.files.length?file.files[0].name:'';});
    box.scrollTop=box.scrollHeight;poll();const timer=setInterval(poll,3000);window.addEventListener('beforeunload',()=>clearInterval(timer));
})();
</script>
@endsection
