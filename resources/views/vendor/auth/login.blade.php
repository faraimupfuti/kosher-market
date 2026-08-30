@extends('admin.layouts.login')

@section('css')
<style>
html, body { height: 100%; margin: 0; background-color: #f4f7f6; color: #333333; justify-content: center; align-items: center; }
.container-wrapper { width: 100%; max-width: 450px; padding: 20px; }
.login-container { width: 100%; background-color: #ffffff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
</style>
@endsection

@section('content')
<div class="container-wrapper">
    <div class="login-container">
        <div class="text-center mb-3"><h1 class="fw-bold">Kosher Market</h1></div>
        <h2 class="text-center mb-4">Vendor Sign In</h2>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('vendor.login.submit') }}" autocomplete="off">
            @csrf
            <div class="mb-3">
                <label for="pseudonym" class="form-label">Vendor Pseudonym</label>
                <input type="text" class="form-control @error('pseudonym') is-invalid @enderror" name="pseudonym" value="{{ old('pseudonym') }}" id="pseudonym" maxlength="40" required autofocus>
                <div class="form-text">Sign in using the pseudonym you chose when registering.</div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember" value="1">
                <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            <div class="d-grid"><button type="submit" class="btn btn-primary">Sign In</button></div>
        </form>
    </div>
</div>
@endsection
