<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureVendorCanSell
{
    public function handle(Request $request, Closure $next)
    {
        $vendor = Auth::guard('vendor')->user();

        if (! $vendor) {
            return redirect()->route('vendor.login');
        }

        if (! $vendor->isSellingEnabled()) {
            return response()->view('vendor.suspended', [
                'vendor' => $vendor,
            ], 403);
        }

        return $next($request);
    }
}
