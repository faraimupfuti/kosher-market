<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\BitcoinSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BitcoinPayoutController extends Controller
{
    public function edit()
    {
        $vendor = Auth::guard('vendor')->user();

        return view('vendor.bitcoin.payout', compact('vendor'));
    }

    public function update(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        $data = $request->validate([
            'bitcoin_payout_address' => ['required', 'string', 'max:120', 'regex:/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/'],
        ]);

        $vendor->update([
            'bitcoin_payout_address' => trim($data['bitcoin_payout_address']),
            'bitcoin_payout_address_verified_at' => null,
        ]);

        return back()->with('success', 'Bitcoin payout address saved. It must be verified before payouts are processed.');
    }

    public function index()
    {
        $vendorId = Auth::guard('vendor')->id();
        $payouts = BitcoinSettlement::with('escrow.order')
            ->where('vendor_id', $vendorId)
            ->where('type', 'seller_payout')
            ->latest()
            ->paginate(20);

        return view('vendor.bitcoin.payouts', compact('payouts'));
    }
}
