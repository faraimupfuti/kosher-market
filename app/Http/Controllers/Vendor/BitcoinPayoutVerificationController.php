<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\BitcoinPayoutAddressVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BitcoinPayoutVerificationController extends Controller
{
    public function show()
    {
        $vendor = Auth::guard('vendor')->user();

        return view('vendor.bitcoin.verify-payout', compact('vendor'));
    }

    public function requestVerification(Request $request, BitcoinPayoutAddressVerificationService $service)
    {
        $vendor = Auth::guard('vendor')->user();

        if (! $vendor->bitcoin_payout_address) {
            return back()->withErrors(['bitcoin_payout_address' => 'Add a Bitcoin payout address first.']);
        }

        $token = $service->issue($vendor, $vendor->bitcoin_payout_address);

        return back()->with('verification_token', $token)
            ->with('success', 'Verification challenge created. Confirm it within 30 minutes.');
    }

    public function confirm(Request $request, BitcoinPayoutAddressVerificationService $service)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $vendor = Auth::guard('vendor')->user();
        $service->confirm($vendor, $data['token']);

        return back()->with('success', 'Bitcoin payout address verified. Automatic payouts are now enabled for this address.');
    }
}
