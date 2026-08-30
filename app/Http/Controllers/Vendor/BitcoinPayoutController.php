<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
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
            'bitcoin_payout_address' => ['required', 'string', 'max:120', 'regex:/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{20,110}$/'],
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
        $payouts = EscrowTransaction::with('order')
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['released', 'paid'])
            ->whereHas('ledgerEntries', fn ($q) => $q->where('type', 'seller_payout_due'))
            ->latest('released_at')
            ->paginate(20);

        return view('vendor.bitcoin.payouts', compact('payouts'));
    }
}
