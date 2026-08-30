<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EscrowDispute;
use App\Models\EscrowTransaction;
use App\Services\BitcoinEscrowService;
use Illuminate\Http\Request;
use Throwable;

class EscrowController extends Controller
{
    public function index()
    {
        return response()->json(EscrowTransaction::with(['order', 'buyer', 'vendor', 'disputes'])->latest()->paginate(25));
    }

    public function release(Request $request, EscrowTransaction $escrow, BitcoinEscrowService $service)
    {
        try {
            return response()->json($service->release($escrow, $request->input('note', 'Released by administrator.')));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refund(Request $request, EscrowTransaction $escrow, BitcoinEscrowService $service)
    {
        try {
            return response()->json($service->refund($escrow, $request->input('note', 'Refund approved by administrator.')));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resolveDispute(Request $request, EscrowDispute $dispute, BitcoinEscrowService $service)
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:buyer,seller'],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $escrow = $dispute->escrowTransaction;
        if ($dispute->status !== 'open' && $dispute->status !== 'under_review') {
            return response()->json(['message' => 'Dispute is already resolved.'], 422);
        }

        try {
            if ($data['resolution'] === 'buyer') {
                $service->refund($escrow, $data['note']);
                $status = 'resolved_buyer';
            } else {
                $service->release($escrow, $data['note']);
                $status = 'resolved_seller';
            }

            $dispute->update([
                'status' => $status,
                'resolved_by' => auth()->id(),
                'resolution_note' => $data['note'],
                'resolved_at' => now(),
            ]);

            return response()->json(['success' => true, 'dispute' => $dispute->fresh()]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
