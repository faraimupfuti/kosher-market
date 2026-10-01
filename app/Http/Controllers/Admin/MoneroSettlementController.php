<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MoneroSettlement;
use App\Models\Vendor;
use App\Services\MoneroEscrowService;
use Illuminate\Http\Request;
use Throwable;

class MoneroSettlementController extends Controller
{
 public function index(){ $settlements=MoneroSettlement::with(['escrow.order','vendor'])->latest()->paginate(30);$vendors=Vendor::whereNotNull('xmr_payout_address')->orderBy('name')->get(['id','name','email','xmr_payout_address','xmr_payout_address_verified_at']);return view('admin.monero.settlements',compact('settlements','vendors')); }
 public function verifyVendor(Vendor $vendor){if(!$vendor->xmr_payout_address||!preg_match('/^(?:4|8)[1-9A-HJ-NP-Za-km-z]{94}$/',$vendor->xmr_payout_address))return back()->withErrors(['vendor'=>'Vendor does not have a valid Monero primary payout address.']);$vendor->update(['xmr_payout_address_verified_at'=>now()]);return back()->with('success','Vendor Monero payout address verified.');}
 public function destination(Request $request,MoneroSettlement $settlement){$data=$request->validate(['destination_address'=>['required','string','max:180','regex:/^(?:4|8)[1-9A-HJ-NP-Za-km-z]{94}$/']]);if($settlement->status==='completed')return back()->withErrors(['settlement'=>'Completed settlements cannot be changed.']);$settlement->update(['destination_address'=>trim($data['destination_address']),'status'=>'pending','error_message'=>null]);return back()->with('success','Monero settlement destination saved.');}
 public function submit(MoneroSettlement $settlement,MoneroEscrowService $service){try{$service->submitSettlement($settlement);return back()->with('success','SHKeeper Monero payout request submitted.');}catch(Throwable $e){return back()->withErrors(['settlement'=>$e->getMessage()]);}}
 public function approve(MoneroSettlement $settlement,MoneroEscrowService $service){try{$service->approveSettlement($settlement);return back()->with('success','SHKeeper Monero payout status synchronized.');}catch(Throwable $e){return back()->withErrors(['settlement'=>$e->getMessage()]);}}
 public function sync(MoneroSettlement $settlement,MoneroEscrowService $service){try{$service->syncSettlement($settlement);return back()->with('success','Monero settlement status synchronized with SHKeeper.');}catch(Throwable $e){return back()->withErrors(['settlement'=>$e->getMessage()]);}}
}
