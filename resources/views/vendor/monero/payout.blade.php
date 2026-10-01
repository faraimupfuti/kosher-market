@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="card">
        <div class="card-body">
            <h3>Monero Withdrawal Address</h3>
            <p class="text-muted">Kosher Market uses Monero for marketplace settlement. Never enter a seed phrase or private key.</p>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('vendor.monero.payout.update') }}">
                @csrf @method('PUT')
                <label class="form-label">Monero withdrawal address</label>
                <input name="xmr_payout_address" value="{{ old('xmr_payout_address', $vendor->xmr_payout_address) }}" class="form-control" placeholder="4... or 8..." required maxlength="180" autocomplete="off">
                <div class="form-text">Use a Monero mainnet primary address (4...) or subaddress (8...) that you control.</div>
                <button class="btn btn-primary mt-3">Save Monero Address</button>
            </form>
            <hr>
            <p><strong>Status:</strong> {{ $vendor->xmr_payout_address_verified_at ? 'Verified' : 'Not verified' }}</p>
        </div>
    </div>
</div>
@endsection
