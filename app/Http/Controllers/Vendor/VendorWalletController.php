<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorWalletTransaction;
use App\Services\VendorWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VendorWalletController extends Controller
{
    public function __construct(private VendorWalletService $wallets) {}

    public function show()
    {
        $vendor = Auth::guard('vendor')->user();
        $wallet = $this->wallets->walletFor($vendor);
        $transactions = $wallet->transactions()->latest()->paginate(20);
        return view('vendor.bitcoin.wallet', compact('vendor', 'wallet', 'transactions'));
    }

    public function generateAddress()
    {
        $vendor = Auth::guard('vendor')->user();
        try {
            $wallet = $this->wallets->allocateDepositAddress($vendor);
            return back()->with('success', 'Your Bitcoin deposit address is ready.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'amount_btc' => ['required', 'string', 'regex:/^\d+(\.\d{1,8})?$/'],
        ]);
        $vendor = Auth::guard('vendor')->user();
        try {
            $settlement = $this->wallets->requestWithdrawal($vendor, $data['amount_btc']);
            return back()->with('success', 'Withdrawal request submitted for administrator review.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
    }
}
