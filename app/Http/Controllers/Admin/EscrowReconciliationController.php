<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EscrowReconciliationService;
use Illuminate\Http\JsonResponse;

class EscrowReconciliationController extends Controller
{
    public function __construct(private EscrowReconciliationService $reconciliation) {}

    public function index(): JsonResponse
    {
        return response()->json($this->reconciliation->audit());
    }
}
