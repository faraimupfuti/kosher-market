<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BitcoinSettlement;
use App\Models\Vendor;
use App\Services\BitcoinEscrowService;
use Illuminate\Http\Request;
use Throwable;

class BitcoinSettlementController extends Controller
{
    public function index()
    {
        $settlements = BitcoinSettlement::with(['escrow.order', 'vendor'])->latest()->paginate(30);
        $vendors = Vendor::whereNotNull('bitcoin_payout_address')->orderBy('name')->get(['id','name','email','bitcoin_payout_address','bitcoin_payout_address_verified_at']);
        return view('admin.bitcoin.settlements', compact('settlements', 'vendors'));
    }

    public function verifyVendor(Vendor $vendor)
    {
        if (!$vendor->bitcoin_payout_address || !preg_match('/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $vendor->bitcoin_payout_address)) return back()->withErrors(['vendor' => 'Vendor does not have a valid Bitcoin mainnet payout address.']);
        $vendor->update(['bitcoin_payout_address_verified_at' => now()]);
        return back()->with('success', 'Vendor Bitcoin payout address verified.');
    }

    public function destination(Request $request, BitcoinSettlement $settlement)
    {
        $data = $request->validate(['destination_address' => ['required','string','max:120','regex:/^(bc1[ac-hj-np-z02-9]{11,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/']);
        if ($settlement->status === 'completed') return back()->withErrors(['settlement' => 'Completed settlements cannot be changed.']);
        $settlement->update(['destination_address' => trim($data['destination_address']), 'status' => 'pending', 'error_message' => null]);
        return back()->with('success', 'Bitcoin settlement destination saved.');
    }

    public function submit(BitcoinSettlement $settlement, BitcoinEscrowService $service)
    {
        try { $service->submitSettlement($settlement); return back()->with('success', 'Payout request submitted to BTCPay. It now requires approval.'); }
        catch (Throwable $e) { return back()->withErrors(['settlement' => $e->getMessage()]); }
    }

    public function approve(BitcoinSettlement $settlement, BitcoinEscrowService $service)
    {
        try { $service->approveSettlement($settlement); return back()->with('success', 'BTCPay payout approved.'); }
        catch (Throwable $e) { return back()->withErrors(['settlement' => $e->getMessage()]); }
    }

    public function sync(BitcoinSettlement $settlement, BitcoinEscrowService $service)
    {
        try { $service->syncSettlement($settlement); return back()->with('success', 'Settlement status synchronized with BTCPay.'); }
        catch (Throwable $e) { return back()->withErrors(['settlement' => $e->getMessage()]); }
    }
}
