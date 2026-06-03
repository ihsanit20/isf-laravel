<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundCycleEvent;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    /**
     * List all published events with order window open.
     */
    public function index(): JsonResponse
    {
        $events = FundCycleEvent::query()
            ->where('status', 'published')
            ->orderByDesc('order_open_at')
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'banner_image_url' => $event->bannerUrl(),
                'order_open_at' => $event->order_open_at?->toIso8601String(),
                'order_close_at' => $event->order_close_at?->toIso8601String(),
                'expected_delivery_date' => $event->expected_delivery_date?->toDateString(),
                'is_order_open' => $this->isOrderOpen($event),
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Show a single event with packages and pickup points.
     */
    public function show(string $slug): JsonResponse
    {
        $event = FundCycleEvent::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'packages' => fn ($q) => $q
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'pickupPoints' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'banner_image_url' => $event->bannerUrl(),
                'order_open_at' => $event->order_open_at?->toIso8601String(),
                'order_close_at' => $event->order_close_at?->toIso8601String(),
                'expected_delivery_date' => $event->expected_delivery_date?->toDateString(),
                'is_order_open' => $this->isOrderOpen($event),
                'packages' => $event->packages->map(fn ($pkg) => [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'description' => $pkg->description,
                    'unit_type' => $pkg->unit_type->value,
                    'unit_size' => (float) $pkg->unit_size,
                    'unit_label' => $pkg->unitLabel(),
                    'package_price' => (float) $pkg->package_price,
                    'advance_percent' => (float) $pkg->advance_percent,
                    'advance_amount' => round((float) $pkg->package_price * (float) $pkg->advance_percent / 100, 2),
                    'min_qty_per_order' => $pkg->min_qty_per_order,
                    'max_qty_per_order' => $pkg->max_qty_per_order,
                    'remaining_qty' => $pkg->remainingQty(),
                ]),
                'pickup_points' => $event->pickupPoints->map(fn ($point) => [
                    'id' => $point->id,
                    'name' => $point->name,
                    'area' => $point->area,
                    'address' => $point->address,
                    'contact_person' => $point->contact_person,
                    'phone' => $point->phone,
                ]),
            ],
        ]);
    }

    private function isOrderOpen(FundCycleEvent $event): bool
    {
        $now = now();

        if ($event->order_open_at && $now->lt($event->order_open_at)) {
            return false;
        }

        if ($event->order_close_at && $now->gt($event->order_close_at)) {
            return false;
        }

        return true;
    }
}
