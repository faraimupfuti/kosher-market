@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Monero Wallet</h2><p class="text-muted mb-0">Your Kosher Market vendor wallet. Escrow proceeds become available here after an administrator releases an order.</p></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Available</small><div class="fs-3 fw-bold">{{ $wallet->availableXmr() }} XMR</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Locked in withdrawals</small><div class="fs-3 fw-bold">{{ $wallet->lockedXmr() }} XMR</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Total wallet balance</small><div class="fs-3 fw-bold">{{ \App\Services\MoneroAmount::fromAtomic(bcadd($wallet->xmr_atomic_available,$wallet->xmr_atomic_locked,0)) }} XMR</div></div></div></div>
    </div>
    <div class="card mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h5>Receive Monero</h5>@if($wallet->xmr_deposit_address)<form method="POST" action="{{ route('vendor.wallet.sync') }}">@csrf<button class="btn btn-outline-primary btn-sm">Sync Deposits</button></form>@endif</div>
        @if($wallet->xmr_deposit_address)
            <label class="form-label">Your deposit address</label><div class="input-group"><input class="form-control font-monospace" value="{{ $wallet->deposit_address }}" readonly onclick="this.select()"><button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $wallet->deposit_address }}')">Copy</button></div><div class="form-text">Send XMR to this address, then use Sync Deposits to manually reconcile confirmed transactions.</div>
        @else
            <p class="text-muted">Generate your personal XMR deposit address to receive funds into your marketplace wallet.</p><form method="POST" action="{{ route('vendor.wallet.address') }}">@csrf<button class="btn btn-primary">Generate XMR Deposit Address</button></form>
        @endif
    </div></div>
    <div class="card mb-4"><div class="card-body"><h5>Withdraw Monero</h5><p class="text-muted">Withdraw from your available wallet balance to your verified Monero address. Every withdrawal is reviewed and submitted manually by an administrator.</p><form method="POST" action="{{ route('vendor.wallet.withdraw') }}" class="row g-2 align-items-end">@csrf<div class="col-md-6"><label class="form-label">Amount (XMR)</label><input name="amount_xmr" class="form-control" placeholder="0.00100000" required inputmode="decimal"></div><div class="col-md-6"><button class="btn btn-primary">Request Withdrawal</button></div></form><div class="mt-3"><small>Withdrawal address: <span class="font-monospace">{{ $vendor->xmr_payout_address ?: 'Not configured' }}</span></small></div></div></div>
    <div class="card"><div class="card-body"><h5>Wallet activity</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td>{{ $transaction->created_at }}</td><td>{{ str_replace('_', ' ', ucfirst($transaction->type)) }}</td><td>{{ $transaction->amountXmr() }} XMR</td><td>{{ ucfirst($transaction->status) }}</td><td class="font-monospace">{{ $transaction->reference }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No wallet activity yet.</td></tr>@endforelse</tbody></table></div>{{ $transactions->links() }}</div></div>
</div>
@endsection
