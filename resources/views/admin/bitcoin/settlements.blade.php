@extends('admin.layouts.master')
@section('content')
<div class="container-fluid py-4">
    <h3>Bitcoin Settlements</h3>
    <p class="text-muted">Seller payouts and buyer refunds are queued here. Private keys never enter Velstore.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card mb-4"><div class="card-body"><h5>Vendor Bitcoin addresses</h5>
        <div class="table-responsive"><table class="table"><thead><tr><th>Vendor</th><th>Address</th><th>Verification</th><th></th></tr></thead><tbody>
        @foreach($vendors as $vendor)<tr><td>{{ $vendor->name }}<br><small>{{ $vendor->email }}</small></td><td><code>{{ $vendor->bitcoin_payout_address }}</code></td><td>{{ $vendor->bitcoin_payout_address_verified_at ? 'Verified' : 'Not verified' }}</td><td>@if(!$vendor->bitcoin_payout_address_verified_at)<form method="POST" action="{{ route('admin.bitcoin.vendors.verify',$vendor) }}">@csrf<button class="btn btn-sm btn-success">Verify</button></form>@endif</td></tr>@endforeach
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body"><h5>Settlement queue</h5>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Type</th><th>Amount</th><th>Destination</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @foreach($settlements as $s)<tr>
            <td>#{{ $s->id }}</td><td>{{ $s->type }}</td><td>{{ number_format((float)$s->amount,8) }} BTC</td>
            <td>@if($s->destination_address)<code>{{ $s->destination_address }}</code>@else<form method="POST" action="{{ route('admin.bitcoin.settlements.destination',$s) }}" class="d-flex gap-2">@csrf<input name="destination_address" class="form-control" placeholder="bc1..." required><button class="btn btn-sm btn-secondary">Save</button></form>@endif</td>
            <td>{{ $s->status }} @if($s->bitcoin_txid)<br><small>TX: {{ $s->bitcoin_txid }}</small>@endif</td>
            <td>@if($s->destination_address && !$s->btcpay_payout_id)<form method="POST" action="{{ route('admin.bitcoin.settlements.submit',$s) }}">@csrf<button class="btn btn-sm btn-primary">Submit to BTCPay</button></form>@elseif($s->btcpay_payout_id)<form method="POST" action="{{ route('admin.bitcoin.settlements.sync',$s) }}">@csrf<button class="btn btn-sm btn-outline-primary">Sync</button></form>@endif</td>
        </tr>@endforeach
        </tbody></table></div>{{ $settlements->links() }}
    </div></div>
</div>
@endsection
