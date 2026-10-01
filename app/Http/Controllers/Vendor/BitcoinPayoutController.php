<?php
namespace App\Http\Controllers\Vendor;
use App\Http\Controllers\Controller;
use App\Models\BitcoinSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BitcoinPayoutController extends Controller
{
 public function edit(){return view('vendor.monero.payout',['vendor'=>Auth::guard('vendor')->user()]);}
 public function update(Request $request){$vendor=Auth::guard('vendor')->user();$data=$request->validate(['xmr_payout_address'=>['required','string','max:180','regex:/^4[0-9AB][1-9A-HJ-NP-Za-km-z]{93}$/']]);$vendor->update(['xmr_payout_address'=>trim($data['xmr_payout_address']),'xmr_payout_address_verified_at'=>null]);return back()->with('success','Monero payout address saved. It must be verified before withdrawals are processed.');}
 public function index(){$payouts=BitcoinSettlement::with('escrow.order')->where('vendor_id',Auth::guard('vendor')->id())->where('type','vendor_withdrawal')->latest()->paginate(20);return view('vendor.monero.payouts',compact('payouts'));}
}
