<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\VendorRegistrationFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('vendor.auth.register');
    }

    public function store(Request $request, VendorRegistrationFeeService $fees)
    {
        $key = 'vendor-register:'.strtolower((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many registration attempts. Please try again later.'])->withInput();
        }
        RateLimiter::hit($key, 600);

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:vendors,email'],
            'phone' => ['nullable','string','max:50'],
            'password' => ['required','string','min:8','confirmed'],
            'terms' => ['accepted'],
        ]);

        $vendor = Vendor::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'pending',
        ]);

        Auth::guard('vendor')->login($vendor);
        $request->session()->regenerate();

        try {
            $fee = $fees->createOrGet($vendor);
            return redirect()->route('vendor.registration-fee')->with('bitcoin_invoice', [
                'checkoutLink' => $fee->checkout_url,
                'invoiceId' => $fee->btcpay_invoice_id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('vendor.registration-fee')->with('error', 'Your vendor account was created, but the Bitcoin registration invoice could not be generated. Please try again from the registration-fee page.');
        }
    }
}
