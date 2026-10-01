@extends('admin.layouts.master')
@section('content')
<div class="container-fluid py-4">
    <h3>Monero Settlements</h3>
    <p class="text-muted">Vendor withdrawals and buyer refunds are queued here. Escrow release credits the vendor's internal wallet; administrators manually submit withdrawals to SHKeeper. Private keys never enter Kosher Market.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-4"><div class="card-body"><h5>Vendor Monero withdrawal addresses</h5>
        <div class="table-responsive"><table class="table"><thead><tr><th>Vendor</th><th>Address</th><th>Verification</th><th></th></tr></thead><tbody>
        @foreach($vendors as $vendor)<tr><td>{{ $vendor->name }}<br><small>{{ $vendor->email }}</small></td><td><code>{{ $vendor->monero_payout_address }}</code></td><td>{{ $vendor->monero_payout_address_verified_at ? 'Verified' : 'Not verified' }}</td><td>@if(!$vendor->monero_payout_address_verified_at)<form method="POST" action="{{ route('admin.monero.vendors.verify',$vendor) }}">@csrf<button class="btn btn-sm btn-success">Verify</button></form>@endif</td></tr>@endforeach
        </tbody></table></div>
    </div></div>
    <div class="card"><div class="card-body"><h5>Settlement queue</h5>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Type</th><th>Vendor</th><th>Amount</th><th>Destination</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @foreach($settlements as $s)<tr>
            <td>#{{ $s->id }}</td><td>{{ str_replace('_',' ',ucfirst($s->type)) }}</td><td>{{ $s->vendor?->name ?: 'Buyer refund' }}</td><td>{{ number_format((float)$s->amount,8) }} XMR</td>
            <td>@if($s->destination_address)<code>{{ $s->destination_address }}</code>@else<form method="POST" action="{{ route('admin.monero.settlements.destination',$s) }}" class="d-flex gap-2">@csrf<input name="destination_address" class="form-control" placeholder="4..." required><button class="btn btn-sm btn-secondary">Save</button></form>@endif</td>
            <td>{{ ucwords(str_replace('_',' ',$s->status)) }} @if($s->monero_txid)<br><small>TX: {{ $s->monero_txid }}</small>@endif</td>
            <td>@if($s->destination_address && !$s->shkeeper_payout_id)<form method="POST" action="{{ route('admin.monero.settlements.submit',$s) }}">@csrf<button class="btn btn-sm btn-primary">Submit to SHKeeper</button></form>@elseif($s->shkeeper_payout_id)<form method="POST" action="{{ route('admin.monero.settlements.sync',$s) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">Sync SHKeeper</button></form>@endif</td>
        </tr>@endforeach
        </tbody></table></div>{{ $settlements->links() }}
    </div></div>
</div>
@endsection
