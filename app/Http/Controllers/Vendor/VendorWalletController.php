<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
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
        return view('vendor.monero.wallet', compact('vendor', 'wallet', 'transactions'));
    }

    public function generateAddress()
    {
        $vendor = Auth::guard('vendor')->user();
        try {
            $this->wallets->allocateDepositAddress($vendor);
            return back()->with('success', 'Your Monero deposit address is ready.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }
    }

    public function sync()
    {
        $vendor = Auth::guard('vendor')->user();
        try {
            $count = $this->wallets->syncDeposits($vendor);
            return back()->with('success', $count . ' confirmed deposit(s) added to your wallet.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate(['amount_xmr' => ['required', 'string', 'regex:/^\d+(\.\d{1,12})?$/']]);
        $vendor = Auth::guard('vendor')->user();
        try {
            $this->wallets->requestWithdrawal($vendor, $data['amount_xmr']);
            return back()->with('success', 'Withdrawal request submitted for administrator review.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }
    }
}
