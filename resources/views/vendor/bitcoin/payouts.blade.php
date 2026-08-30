@extends('vendor.layouts.master')
@section('content')
<div class="container py-4">
    <div class="card"><div class="card-body">
        <h3>Bitcoin Payouts</h3>
        <p class="text-muted">Released escrow balances awaiting payout or already processed.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Escrow</th><th>Order</th><th>Amount</th><th>Status</th><th>Released</th></tr></thead><tbody>
        @forelse($payouts as $payout)
            <tr><td>#{{ $payout->id }}</td><td>#{{ $payout->order_id }}</td><td>{{ number_format((float)$payout->seller_amount, 8) }} BTC</td><td>{{ ucfirst($payout->status) }}</td><td>{{ $payout->released_at?->format('Y-m-d H:i') }}</td></tr>
        @empty
            <tr><td colspan="5">No Bitcoin payouts yet.</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $payouts->links() }}
    </div></div>
</div>
@endsection
