<?php

namespace App\Http\Controllers;

use App\Models\EscrowDispute;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\ShkeeperWebhookEvent;
use App\Models\VendorRegistrationFee;
use App\Services\MoneroAmount;
use App\Services\MoneroEscrowService;
use App\Services\VendorRegistrationFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class EscrowController extends Controller
{
    public function __construct(private MoneroEscrowService $escrow,private VendorRegistrationFeeService $vendorFees){}

    public function create(Request $request,Order $order)
    {
        $customer=Auth::guard('customer')->user();abort_unless($customer&&(int)$order->customer_id===(int)$customer->id,403);
        try{return response()->json($this->escrow->createForOrder($order)->load(['order','vendor']),201);}catch(Throwable $e){report($e);return response()->json(['message'=>'Unable to create escrow for this order.'],422);}
    }

    public function show(EscrowTransaction $escrow)
    {
        $customer=Auth::guard('customer')->user();abort_unless($customer&&(int)$escrow->buyer_id===(int)$customer->id,403);
        return view('escrow.show',['escrow'=>$escrow->load(['order','vendor','disputes','settlements'])]);
    }

    public function createPayment(Request $request,EscrowTransaction $escrow)
    {
        $customer=Auth::guard('customer')->user();abort_unless($customer&&(int)$escrow->buyer_id===(int)$customer->id,403);
        if($escrow->status==='funded')return response()->json(['message'=>'Payment already confirmed.','status'=>'funded']);
        try{$invoice=$this->escrow->createMoneroInvoice($escrow);return response()->json(['escrow_id'=>$escrow->id,'currency'=>'XMR','invoice_id'=>$invoice['id']??null,'checkout_url'=>$invoice['checkoutLink']??null,'payment_address'=>$invoice['wallet']??null,'amount_xmr'=>$invoice['amount']??null,'exchange_rate'=>$invoice['exchange_rate']??null,'recalculate_after'=>$invoice['recalculate_after']??null]);}catch(Throwable $e){report($e);return response()->json(['message'=>'Unable to create the Monero payment.'],502);}
    }

    public function confirmReceipt(EscrowTransaction $escrow)
    {
        $customer=Auth::guard('customer')->user();abort_unless($customer&&(int)$escrow->buyer_id===(int)$customer->id,403);
        try{if($escrow->status!=='funded')return response()->json(['message'=>'Only funded escrow can request release.'],422);$escrow->update(['release_requested_at'=>now(),'release_requested_by_customer_id'=>$customer->id]);return response()->json(['success'=>true,'status'=>'release_requested']);}catch(Throwable $e){return response()->json(['message'=>'Unable to request escrow release.'],422);}
    }

    public function dispute(Request $request,EscrowTransaction $escrow)
    {
        $customer=Auth::guard('customer')->user();abort_unless($customer&&(int)$escrow->buyer_id===(int)$customer->id,403);
        $data=$request->validate(['reason'=>['required','string','max:100'],'description'=>['required','string','max:5000']]);
        if(!in_array($escrow->status,['funded','processing'],true))return response()->json(['message'=>'This escrow cannot be disputed.'],422);
        $dispute=DB::transaction(function()use($escrow,$customer,$data){$locked=EscrowTransaction::whereKey($escrow->id)->lockForUpdate()->firstOrFail();if(!in_array($locked->status,['funded','processing'],true))abort(422,'This escrow cannot be disputed.');$d=EscrowDispute::create(['escrow_transaction_id'=>$locked->id,'opened_by'=>$customer->id,'reason'=>$data['reason'],'description'=>$data['description'],'status'=>'open']);$locked->transitionTo('disputed');$locked->save();return $d;});
        return response()->json(['success'=>true,'dispute'=>$dispute],201);
    }

    public function webhook(Request $request)
    {
        $raw=$request->getContent();$timestamp=trim((string)$request->header('X-Shkeeper-Timestamp'));$signature=strtolower(trim((string)$request->header('X-Shkeeper-Signature')));$secret=(string)config('monero.shkeeper_api_key');
        if(!ctype_digit($timestamp)||strlen($signature)!==64||!ctype_xdigit($signature)||!$secret)return response()->json(['message'=>'Invalid SHKeeper webhook signature.'],401);
        if(abs(time()-(int)$timestamp)>=(int)config('monero.webhook_tolerance_seconds',300))return response()->json(['message'=>'Expired SHKeeper webhook.'],401);
        $expected=hash_hmac('sha256',$timestamp.'.'.$raw,$secret);if(!hash_equals($expected,$signature))return response()->json(['message'=>'Invalid SHKeeper webhook signature.'],401);
        $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$externalId=(string)($payload['external_id']??'');if($externalId==='')return response()->json(['message'=>'external_id is required.'],422);
        $fingerprint=hash('sha256',$raw);$txs=$payload['transactions']??[];$trigger=collect($txs)->firstWhere('trigger',true)??($txs[0]??[]);$txid=(string)($trigger['txid']??'');
        try{
            $event=ShkeeperWebhookEvent::firstOrCreate(['event_fingerprint'=>$fingerprint],['external_id'=>$externalId,'status'=>(string)($payload['status']??''),'txid'=>$txid]);
            if($event->processed_at)return response()->json(['received'=>true,'duplicate'=>true],202);
            $paid=in_array(strtoupper((string)($payload['status']??'')),['PAID','OVERPAID'],true);
            if(!$paid){$event->update(['processed_at'=>now()]);return response()->json(['received'=>true],202);}
            if(str_starts_with($externalId,'vendor-fee-')){
                $fee=VendorRegistrationFee::whereKey((int)substr($externalId,11))->firstOrFail();$amount=(string)($payload['balance_crypto']??'');if($amount===''||!$txid)throw new \RuntimeException('SHKeeper vendor payment callback is incomplete.');
                $this->vendorFees->markPaid($fee,$txid,(int)config('monero.required_confirmations',10),$amount);
            }else{
                $escrow=EscrowTransaction::whereKey((int)$externalId)->firstOrFail();
                if(strtoupper((string)($payload['crypto']??''))!=='XMR'||$escrow->currency!=='XMR')throw new \RuntimeException('SHKeeper payment is not XMR.');
                $amount=(string)($payload['balance_crypto']??'');$required=(string)($escrow->xmr_amount?:$escrow->amount);
                if($amount===''||MoneroAmount::cmp(MoneroAmount::toAtomic($amount),MoneroAmount::toAtomic($required))<0)throw new \RuntimeException('SHKeeper payment amount does not cover escrow amount.');
                if(!$txid)throw new \RuntimeException('SHKeeper payment callback has no transaction ID.');
                $this->escrow->markFunded($escrow,$txid,(int)config('monero.required_confirmations',10),$amount);
            }
            $event->update(['processed_at'=>now(),'processing_error'=>null]);return response()->json(['received'=>true],202);
        }catch(Throwable $e){if(isset($event))$event->update(['processing_error'=>substr($e->getMessage(),0,5000)]);report($e);return response()->json(['message'=>'Webhook processing failed; SHKeeper will retry.'],500);}
    }
}
