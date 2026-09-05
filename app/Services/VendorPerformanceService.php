<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorPerformanceService
{
    private const COMPLETED_STATUSES = ['completed'];
    private const SALES_WINDOW_DAYS = 90;
    private const RATING_PRIOR_REVIEWS = 5;

    public function rankings(int $limit = 6): array
    {
        $vendors = $this->allScored();

        return [
            'bestSelling' => $vendors->sortBy([
                ['sales_90d', 'desc'],
                ['completed_sales', 'desc'],
                ['completed_orders_count', 'desc'],
                ['id', 'desc'],
            ])->take($limit)->values(),
            'bestRated' => $vendors
                ->filter(fn ($vendor) => $vendor->approved_reviews_count >= 3)
                ->sortBy([
                    ['rating_confidence', 'desc'],
                    ['approved_reviews_count', 'desc'],
                    ['id', 'desc'],
                ])->take($limit)->values(),
            'worstSelling' => $vendors->sortBy([
                ['sales_90d', 'asc'],
                ['completed_sales', 'asc'],
                ['completed_orders_count', 'asc'],
                ['id', 'asc'],
            ])->take($limit)->values(),
            'topPerformers' => $vendors->sortBy([
                ['performance_score', 'desc'],
                ['completed_sales', 'desc'],
                ['id', 'desc'],
            ])->take($limit)->values(),
        ];
    }

    public function allScored(): Collection
    {
        $vendors = $this->baseQuery()->get();
        $this->score($vendors);

        return $vendors;
    }

    private function baseQuery()
    {
        $since = now()->subDays(self::SALES_WINDOW_DAYS);

        return Vendor::query()
            ->where('vendors.status', 'active')
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->where('products.status', 1),
                'approvedReviews as approved_reviews_count',
                'orders as completed_orders_count' => fn ($query) => $query->whereIn('status', self::COMPLETED_STATUSES),
                'orders as orders_90d_count' => fn ($query) => $query
                    ->whereIn('status', self::COMPLETED_STATUSES)
                    ->where('orders.created_at', '>=', $since),
            ])
            ->withAvg('approvedReviews', 'rating')
            ->withSum([
                'orders as completed_sales' => fn ($query) => $query->whereIn('status', self::COMPLETED_STATUSES),
                'orders as sales_90d' => fn ($query) => $query
                    ->whereIn('status', self::COMPLETED_STATUSES)
                    ->where('orders.created_at', '>=', $since),
            ], 'total_price')
            ->selectSub(
                DB::table('orders')
                    ->selectRaw('COUNT(DISTINCT orders.customer_id)')
                    ->whereIn('orders.status', self::COMPLETED_STATUSES)
                    ->whereColumn('orders.vendor_id', 'vendors.id')
                    ->whereNotNull('orders.customer_id'),
                'unique_customers_count'
            );
    }

    private function score(Collection $vendors): void
    {
        $globalRating = $vendors->sum(fn ($vendor) =>
            (float) ($vendor->approved_reviews_avg_rating ?? 0) * (int) $vendor->approved_reviews_count
        );
        $globalReviews = $vendors->sum('approved_reviews_count');
        $globalRating = $globalReviews > 0 ? $globalRating / $globalReviews : 0;

        $maxSales90d = max(1.0, (float) $vendors->max('sales_90d'));
        $maxOrders90d = max(1, (int) $vendors->max('orders_90d_count'));
        $maxProducts = max(1, (int) $vendors->max('active_products_count'));
        $maxCustomers = max(1, (int) $vendors->max('unique_customers_count'));
        $maxOrders = max(1, (int) $vendors->max('completed_orders_count'));

        $vendors->each(function (Vendor $vendor) use (
            $globalRating,
            $maxSales90d,
            $maxOrders90d,
            $maxProducts,
            $maxCustomers,
            $maxOrders
        ) {
            $reviews = (int) $vendor->approved_reviews_count;
            $averageRating = (float) ($vendor->approved_reviews_avg_rating ?? 0);
            $confidence = (($reviews / ($reviews + self::RATING_PRIOR_REVIEWS)) * $averageRating)
                + ((self::RATING_PRIOR_REVIEWS / ($reviews + self::RATING_PRIOR_REVIEWS)) * $globalRating);

            $orders = (int) $vendor->completed_orders_count;
            $uniqueCustomers = (int) $vendor->unique_customers_count;
            $repeatOrderRate = $orders > 0
                ? max(0, min(1, ($orders - min($orders, $uniqueCustomers)) / $orders))
                : 0;

            $salesVelocityScore = min(1, (float) ($vendor->sales_90d ?? 0) / $maxSales90d);
            $orderConsistencyScore = min(1, (int) $vendor->orders_90d_count / $maxOrders90d);
            $catalogScore = min(1, (int) $vendor->active_products_count / $maxProducts);
            $customerReachScore = min(1, $uniqueCustomers / $maxCustomers);
            $orderVolumeScore = min(1, $orders / $maxOrders);
            $ratingScore = min(1, $confidence / 5);

            $performanceScore = round((
                ($salesVelocityScore * 30) +
                ($ratingScore * 25) +
                ($orderConsistencyScore * 15) +
                ($catalogScore * 10) +
                ($customerReachScore * 10) +
                ($repeatOrderRate * 5) +
                ($orderVolumeScore * 5)
            ), 1);

            $vendor->setAttribute('rating_confidence', round($confidence, 2));
            $vendor->setAttribute('repeat_order_rate', round($repeatOrderRate * 100, 1));
            $vendor->setAttribute('performance_score', $performanceScore);
            $vendor->setAttribute('sales_velocity_90d', (float) ($vendor->sales_90d ?? 0));
        });
    }
}
