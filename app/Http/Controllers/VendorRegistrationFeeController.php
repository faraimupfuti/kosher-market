<?php

namespace App\Http\Controllers;

use App\Services\VendorRegistrationFeeService;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VendorRegistrationFeeController extends Controller
{
    public function __construct(private VendorRegistrationFeeService $fees) {}

    public function show()
    {
        $vendor = Auth::guard('vendor')->user();
        abort_unless($vendor, 401);
        try {
            $fee = $this->fees->createOrGet($vendor);
            return view('vendor.registration-fee', compact('vendor', 'fee'));
        } catch (Throwable $e) {
            report($e);
            return view('vendor.registration-fee', ['vendor' => $vendor, 'fee' => null, 'error' => 'Bitcoin payment is temporarily unavailable.']);
        }
    }
}
