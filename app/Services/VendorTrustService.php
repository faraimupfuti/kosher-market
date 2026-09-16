<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class VendorTrustService
{
    public function score(Vendor $vendor): int
    {
        $orders = (int) $vendor->orders()->where('orders.status', 'completed')->count();
        $reviews = (int) $vendor->approvedReviews()->count();
        $rating = (float) ($vendor->approvedReviews()->avg('product_reviews.rating') ?? 0);
        $disputes = (int) DB::table('disputes')->where('vendor_id', $vendor->id)->count();
        $refunds = (int) EscrowTransaction::where('vendor_id', $vendor->id)->whereIn('status', ['refund_pending', 'refunded'])->count();
        $ratingScore = $reviews > 0 ? ($rating / 5) * 30 : 15;
        $volumeScore = min(20, $orders * 2);
        $historyScore = min(15, max(0, 15 - ($disputes * 3)));
        $refundScore = $orders > 0 ? max(0, 15 - (($refunds / $orders) * 15)) : 10;
        $verificationScore = $vendor->verification_status === 'verified' ? 20 : ($vendor->verification_status === 'established' ? 12 : 5);

        return max(0, min(100, (int) round($ratingScore + $volumeScore + $historyScore + $refundScore + $verificationScore)));
    }

    public function refresh(Vendor $vendor): int
    {
        $score = $this->score($vendor);
        $vendor->forceFill(['trust_score' => $score])->saveQuietly();

        return $score;
    }

    public function badge(Vendor $vendor): string
    {
        if ($vendor->verification_status === 'verified' && $vendor->trust_score >= 85) {
            return 'Verified Vendor';
        }
        if ($vendor->trust_score >= 75) {
            return 'Established Vendor';
        }
        if ($vendor->trust_score >= 60) {
            return 'Developing Vendor';
        }

        return 'New Vendor';
    }
}
