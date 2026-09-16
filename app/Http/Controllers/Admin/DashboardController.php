<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BitcoinSettlement;
use App\Models\Customer;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\PlatformRevenueEntry;
use App\Models\Vendor;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $start = Carbon::now()->startOfMonth();
        $revenue = PlatformRevenueEntry::where('status', 'earned');

        $data = [
            'totalSales' => Order::where('status', 'completed')->sum('total_amount'),
            'todaySales' => Order::whereDate('created_at', today())->where('status', 'completed')->sum('total_amount'),
            'totalOrders' => Order::count(),
            'completedOrders' => Order::where('status', 'completed')->count(),
            'totalVendors' => Vendor::where('status', 'active')->count(),
            'totalCustomers' => Customer::where('status', 'active')->count(),
            'platformRevenueBtc' => $revenue->sum('amount_btc'),
            'monthPlatformRevenueBtc' => (clone $revenue)->where('earned_at', '>=', $start)->sum('amount_btc'),
            'saleCommissionBtc' => (clone $revenue)->where('type', 'sale_commission')->sum('amount_btc'),
            'vendorOnboardingBtc' => (clone $revenue)->where('type', 'vendor_onboarding')->sum('amount_btc'),
            'pendingEscrows' => EscrowTransaction::where('status', 'funded')->count(),
            'pendingSettlements' => BitcoinSettlement::whereIn('status', ['pending', 'awaiting_approval', 'awaiting_payment', 'in_progress'])->count(),
        ];

        return view('admin.dashboard.index', compact('data'));
    }
}
