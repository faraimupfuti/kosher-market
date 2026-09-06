@extends('themes.xylo.layouts.master')
@section('content')
<div class="container py-4"><h1>Disputes & Buyer Protection</h1><p class="text-muted">Track active cases, evidence and resolutions.</p><div class="list-group">@forelse($disputes as $dispute)<a class="list-group-item list-group-item-action" href="{{ route('disputes.show',$dispute) }}"><strong>Order #{{ $dispute->order_id }}</strong> — {{ $dispute->reason }} <span class="badge bg-secondary float-end">{{ str_replace('_',' ',$dispute->status) }}</span><div class="small text-muted">Priority: {{ $dispute->priority }} · {{ $dispute->created_at->diffForHumans() }}</div></a>@empty<div class="alert alert-light">No disputes found.</div>@endforelse</div>{{ $disputes->links() }}</div>
@endsection
