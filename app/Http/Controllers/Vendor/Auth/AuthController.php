<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('vendor.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'pseudonym' => ['required', 'string', 'max:40'],
            'password' => ['required', 'min:6'],
        ]);

        $key = 'vendor-login:'.strtolower($data['pseudonym']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pseudonym' => 'Too many login attempts. Please try again later.'])->withInput();
        }

        if (Auth::guard('vendor')->attempt(['pseudonym' => $data['pseudonym'], 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return redirect()->route('vendor.dashboard');
        }

        RateLimiter::hit($key, 300);

        return back()->withErrors(['pseudonym' => 'Invalid pseudonym or password.'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendor.login');
    }

    public function dashboard()
    {
        return view('vendor.dashboard');
    }
}
