@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="card">
        <div class="card-body">
            <h3>Bitcoin Payout Settings</h3>
            <p class="text-muted">Velstore pays sellers in Bitcoin only. Never enter a seed phrase or private key.</p>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('vendor.bitcoin.payout.update') }}">
                @csrf @method('PATCH')
                <label class="form-label">Bitcoin payout address</label>
                <input name="bitcoin_payout_address" value="{{ old('bitcoin_payout_address', $vendor->bitcoin_payout_address) }}" class="form-control" placeholder="bc1..." required maxlength="120" autocomplete="off">
                <div class="form-text">Use a Bitcoin mainnet address that you control. Verify every character before saving.</div>
                <button class="btn btn-primary mt-3">Save Bitcoin Address</button>
            </form>
            <hr>
            <p><strong>Status:</strong> {{ $vendor->bitcoin_payout_address_verified_at ? 'Verified' : 'Not verified' }}</p>
        </div>
    </div>
</div>
@endsection
