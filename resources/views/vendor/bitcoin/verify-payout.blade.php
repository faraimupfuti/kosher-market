@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="card">
        <div class="card-body">
            <h3>Verify Bitcoin Payout Address</h3>
            <p class="text-muted">Verification is required before Kosher Market can send automatic BTC payouts to this address.</p>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

            <p><strong>Address:</strong> <code>{{ $vendor->bitcoin_payout_address }}</code></p>

            @if(session('verification_token'))
                <div class="alert alert-warning">
                    <strong>Verification challenge:</strong><br>
                    <code style="word-break:break-all">{{ session('verification_token') }}</code>
                    <div class="small mt-2">This challenge expires in 30 minutes. Keep it private.</div>
                </div>
            @endif

            <form method="POST" action="{{ route('vendor.bitcoin.payout.verify.request') }}" class="mb-4">
                @csrf
                <button class="btn btn-primary" type="submit">Generate Verification Challenge</button>
            </form>

            <form method="POST" action="{{ route('vendor.bitcoin.payout.verify.confirm') }}">
                @csrf
                <label class="form-label">Verification challenge</label>
                <input name="token" class="form-control" maxlength="64" minlength="64" required autocomplete="one-time-code">
                <button class="btn btn-success mt-3" type="submit">Confirm Address</button>
            </form>
        </div>
    </div>
</div>
@endsection
