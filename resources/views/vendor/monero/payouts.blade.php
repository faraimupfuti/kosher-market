@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="card"><div class="card-body">
        <h3>Monero Payouts</h3>
        <p class="text-muted">Your released escrow balances and XMRPay settlement status.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Settlement</th><th>Order</th><th>Amount</th><th>Status</th><th>TXID</th></tr></thead><tbody>
        @forelse($payouts as $payout)
            <tr><td>#{{ $payout->id }}</td><td>#{{ $payout->escrow?->order_id }}</td><td>{{ number_format((float)$payout->amount, 8) }} XMR</td><td>{{ ucwords(str_replace('_', ' ', $payout->status)) }}</td><td>@if($payout->monero_txid)<code>{{ $payout->monero_txid }}</code>@else—@endif</td></tr>
        @empty
            <tr><td colspan="5">No Monero payouts yet.</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $payouts->links() }}
    </div></div>
</div>
@endsection
