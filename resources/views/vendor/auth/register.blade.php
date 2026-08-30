@extends('admin.layouts.login')

@section('content')
<div class="container py-5" style="max-width:620px">
    <div class="card shadow-sm border-0 p-4">
        <div class="text-center mb-4">
            <h1 class="fw-bold">Kosher Market</h1>
            <p class="text-muted mb-0">Become a Bitcoin marketplace vendor</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="alert alert-warning">
            <strong>Vendor onboarding fee: $200 USD equivalent in Bitcoin.</strong><br>
            This is a one-time fee. Your vendor account remains pending until the Bitcoin payment is confirmed.
        </div>

        <form method="POST" action="{{ route('vendor.register.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label">Business / Vendor Name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
            <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone') }}"></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div><div class="col-md-6 mb-3"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div></div>
            <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required><label class="form-check-label" for="terms">I agree to the Kosher Market vendor terms and understand the $200 one-time Bitcoin onboarding fee.</label></div>
            <button class="btn btn-primary w-100" type="submit">Create Vendor Account & Continue to Bitcoin Payment</button>
        </form>
        <div class="text-center mt-3"><a href="{{ route('vendor.login') }}">Already a vendor? Sign in</a></div>
    </div>
</div>
@endsection
