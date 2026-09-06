<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\VendorTrustService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request, VendorTrustService $trust)
    {
        $vendor = $request->user('vendor');
        $orders = $vendor->orders()->where('status','completed');
        $sales = (float) $orders->sum('total_amount');
        $last30 = (float) $vendor->orders()->where('status','completed')->where('created_at','>=',now()->subDays(30))->sum('total_amount');
        $last90 = (float) $vendor->orders()->where('status','completed')->where('created_at','>=',now()->subDays(90))->sum('total_amount');
        $reviews = $vendor->approvedReviews();
        $disputes = Dispute::where('vendor_id',$vendor->id)->count();
        $openDisputes = Dispute::where('vendor_id',$vendor->id)->whereIn('status',['open','seller_response','mediation','escalated'])->count();
        $score = $trust->refresh($vendor->fresh());
        $daily = $vendor->orders()->where('status','completed')->where('created_at','>=',now()->subDays(30))->selectRaw('DATE(created_at) day, SUM(total_amount) sales, COUNT(*) orders')->groupBy('day')->orderBy('day')->get();
        return view('vendor.analytics', compact('vendor','sales','last30','last90','reviews','disputes','openDisputes','score','daily'));
    }
}
