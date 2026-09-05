<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Yajra\DataTables\Facades\DataTables;

class VendorController extends Controller
{
    private array $completedStatuses = ['completed', 'delivered', 'released'];

    public function index()
    {
        return view('admin.vendors.index');
    }

    public function getVendorData()
    {
        $vendors = Vendor::query()
            ->withCount([
                'orders as completed_orders_count' => fn ($query) => $query->whereIn('status', $this->completedStatuses),
                'approvedReviews as approved_reviews_count',
            ])
            ->withAvg('approvedReviews', 'rating')
            ->withSum([
                'orders as completed_sales' => fn ($query) => $query->whereIn('status', $this->completedStatuses),
            ], 'total_price')
            ->select(['id', 'name', 'email', 'phone', 'status', 'banned_at', 'ban_reason']);

        return DataTables::of($vendors)
            ->addColumn('performance', function ($vendor) {
                return sprintf(
                    '<div><strong>%s BTC</strong><br><small>%d completed orders</small></div>',
                    number_format((float) ($vendor->completed_sales ?? 0), 8),
                    $vendor->completed_orders_count
                );
            })
            ->addColumn('rating', function ($vendor) {
                if ($vendor->approved_reviews_count < 1) {
                    return '<span class="text-muted">No reviews</span>';
                }

                return sprintf(
                    '<span class="fw-semibold">★ %s</span><br><small class="text-muted">%d approved reviews</small>',
                    number_format((float) ($vendor->approved_reviews_avg_rating ?? 0), 2),
                    $vendor->approved_reviews_count
                );
            })
            ->addColumn('action', function ($vendor) {
                $statusAction = match ($vendor->status) {
                    'banned' => '<button class="btn btn-sm btn-success" onclick="setVendorStatus('.$vendor->id.', \'active\')">Unban</button>',
                    'inactive' => '<button class="btn btn-sm btn-success" onclick="setVendorStatus('.$vendor->id.', \'active\')">Unblock</button>',
                    default => '<button class="btn btn-sm btn-warning" onclick="setVendorStatus('.$vendor->id.', \'inactive\')">Block</button> <button class="btn btn-sm btn-danger" onclick="banVendor('.$vendor->id.')">Ban</button>',
                };

                return '<div class="d-flex gap-1 flex-wrap">'.$statusAction.'</div>';
            })
            ->editColumn('status', function ($vendor) {
                return match ($vendor->status) {
                    'active' => '<span class="badge bg-success">Active</span>',
                    'inactive' => '<span class="badge bg-warning text-dark">Blocked</span>',
                    'banned' => '<span class="badge bg-danger">Banned</span>',
                    default => '<span class="badge bg-secondary">'.e($vendor->status).'</span>',
                };
            })
            ->rawColumns(['performance', 'rating', 'action', 'status'])
            ->make(true);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,banned'],
            'ban_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,banned'],
        ]);

        $vendor = Vendor::findOrFail($id);
        $vendor->status = $validated['status'];

        if ($validated['status'] === 'banned') {
            $vendor->banned_at = $vendor->banned_at ?: now();
            $vendor->ban_reason = trim($validated['ban_reason'] ?? '');
        } else {
            $vendor->banned_at = null;
            $vendor->ban_reason = null;
        }

        $vendor->save();

        $message = match ($validated['status']) {
            'banned' => 'Vendor has been banned and can no longer sell on the marketplace.',
            'inactive' => 'Vendor has been blocked from selling on the marketplace.',
            default => 'Vendor has been restored and can sell again.',
        };

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function create()
    {
        return view('admin.vendors.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:vendors,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->symbols(),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]+$/'],
            'status' => ['required', 'in:active,inactive,banned'],
        ]);

        Vendor::create([
            'name' => trim($validatedData['name']),
            'email' => strtolower(trim($validatedData['email'])),
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'] ?? null,
            'status' => $validatedData['status'],
        ]);

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor registered successfully!');
    }

    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => __('cms.vendors.success_delete'),
        ]);
    }
}
