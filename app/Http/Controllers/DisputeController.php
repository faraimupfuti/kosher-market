<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DisputeController extends Controller
{
    public function index(Request $request)
    {
        $guard = $request->user('vendor') ? 'vendor' : ($request->user('customer') ? 'customer' : 'web');
        $user = $request->user($guard);
        $query = Dispute::with(['order','evidence'])->latest();
        if ($guard === 'vendor') $query->where('vendor_id', $user->id);
        elseif ($guard === 'customer') $query->where('customer_id', $user->id);
        elseif ($guard !== 'web') abort(403);
        return view('disputes.index', ['disputes' => $query->paginate(20)]);
    }

    public function show(Request $request, Dispute $dispute)
    {
        $this->authorizeParticipant($request, $dispute);
        return view('disputes.show', ['dispute' => $dispute->load(['order','evidence','conversation'])]);
    }

    public function store(Request $request, Order $order)
    {
        $customer = $request->user('customer');
        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 403);
        abort_if($order->status === 'canceled', 422, 'Canceled orders cannot be disputed.');
        abort_if(Dispute::where('order_id', $order->id)->whereIn('status', ['open','seller_response','mediation','escalated'])->exists(), 422, 'This order already has an active dispute.');

        $data = $request->validate([
            'reason' => 'required|string|max:80',
            'description' => 'required|string|min:20|max:5000',
            'priority' => 'nullable|in:low,normal,high,critical',
        ]);

        $dispute = DB::transaction(function () use ($data, $order, $customer) {
            return Dispute::create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'vendor_id' => $order->vendor_id,
                'reason' => $data['reason'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'normal',
                'sla_due_at' => now()->addHours(48),
                'status' => 'seller_response',
            ]);
        });

        return redirect()->route('disputes.show', $dispute)->with('success', 'Dispute opened. The vendor has 48 hours to respond.');
    }

    public function respond(Request $request, Dispute $dispute)
    {
        $vendor = $request->user('vendor');
        abort_unless($vendor && (int) $dispute->vendor_id === (int) $vendor->id, 403);
        $data = $request->validate(['response' => 'required|string|min:20|max:5000']);
        $dispute->update(['description' => $dispute->description."\n\nVendor response:\n".$data['response'], 'status' => 'mediation']);
        return back()->with('success', 'Your response has been submitted.');
    }

    public function evidence(Request $request, Dispute $dispute)
    {
        $this->authorizeParticipant($request, $dispute);
        $data = $request->validate(['file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,txt,csv,doc,docx,xls,xlsx', 'note' => 'nullable|string|max:1000']);
        $file = $data['file'];
        $path = $file->store('dispute-evidence', 'local');
        $user = $request->user('vendor') ?: $request->user('customer');
        $dispute->evidence()->create([
            'customer_id' => $request->user('customer')?->id,
            'vendor_id' => $request->user('vendor')?->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'note' => $data['note'] ?? null,
        ]);
        return back()->with('success', 'Evidence added.');
    }

    public function downloadEvidence(Request $request, $evidence)
    {
        $evidence = \App\Models\DisputeEvidence::with('dispute')->findOrFail($evidence);
        $this->authorizeParticipant($request, $evidence->dispute);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);
        return Storage::disk('local')->download($evidence->path, $evidence->original_name ?: basename($evidence->path));
    }

    public function resolve(Request $request, Dispute $dispute)
    {
        $admin = $request->user('web');
        abort_unless($admin, 403);
        $data = $request->validate(['resolution' => 'required|in:buyer_refund,vendor_release,partial_refund,rejected', 'resolution_note' => 'required|string|min:10|max:5000']);
        $dispute->update(['status' => 'resolved', 'resolution' => $data['resolution'], 'resolution_note' => $data['resolution_note'], 'resolved_by' => $admin->id, 'resolved_at' => now()]);
        return back()->with('success', 'Dispute resolved.');
    }

    private function authorizeParticipant(Request $request, Dispute $dispute): void
    {
        $customer = $request->user('customer');
        $vendor = $request->user('vendor');
        $admin = $request->user('web');
        if (!$admin && (!$customer || (int) $dispute->customer_id !== (int) $customer->id) && (!$vendor || (int) $dispute->vendor_id !== (int) $vendor->id)) abort(403);
    }
}
