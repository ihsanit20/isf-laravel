<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundCycleEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundCycleEventRequest;
use App\Http\Requests\Admin\UpdateFundCycleEventRequest;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FundCycleEventController extends Controller
{
    public function index(FundCycle $fundCycle): Response
    {
        return Inertia::render('admin/FundCycleEvents', [
            'fundCycle' => [
                'id' => $fundCycle->id,
                'name' => $fundCycle->name,
                'status' => $fundCycle->status,
                'status_label' => FundCycle::statusLabel($fundCycle->status),
                'start_date' => $fundCycle->start_date?->format('Y-m-d'),
                'lock_date' => $fundCycle->lock_date?->format('Y-m-d'),
                'maturity_date' => $fundCycle->maturity_date?->format('Y-m-d'),
                'settlement_date' => $fundCycle->settlement_date?->format('Y-m-d'),
            ],
            'eventStatuses' => FundCycleEventStatus::options(),
            'events' => $fundCycle->events()
                ->latest('order_open_at')
                ->latest('id')
                ->get()
                ->map(fn(FundCycleEvent $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'description' => $event->description,
                    'banner_image_path' => $event->banner_image_path,
                    'order_open_at' => $event->order_open_at?->format('Y-m-d\\TH:i'),
                    'order_close_at' => $event->order_close_at?->format('Y-m-d\\TH:i'),
                    'expected_delivery_date' => $event->expected_delivery_date?->format('Y-m-d'),
                    'created_at' => $event->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function store(StoreFundCycleEventRequest $request, FundCycle $fundCycle): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['slug'] = $this->generateUniqueSlug($attributes['title']);

        $fundCycle->events()->create($attributes);

        return to_route('admin.fund-cycles.events.index', $fundCycle);
    }

    public function update(
        UpdateFundCycleEventRequest $request,
        FundCycle $fundCycle,
        FundCycleEvent $fundCycleEvent,
    ): RedirectResponse {
        abort_unless($fundCycleEvent->fund_cycle_id === $fundCycle->id, 404);

        $attributes = $request->validated();
        $attributes['slug'] = $this->generateUniqueSlug($attributes['title'], $fundCycleEvent->id);

        $fundCycleEvent->update($attributes);

        return to_route('admin.fund-cycles.events.index', $fundCycle);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'event';
        $slug = $baseSlug;
        $counter = 2;

        while (
            FundCycleEvent::query()
            ->when($ignoreId !== null, fn($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
