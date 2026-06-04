<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventExpenseCategory;
use App\Enums\EventPackageStatus;
use App\Enums\EventPackageUnitType;
use App\Enums\FundCycleEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundCycleEventRequest;
use App\Http\Requests\Admin\UpdateFundCycleEventRequest;
use App\Http\Requests\Admin\UploadFundCycleEventBannerRequest;
use App\Models\EventExpense;
use App\Models\EventPackage;
use App\Models\EventPickupPoint;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
                ->map(fn (FundCycleEvent $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'description' => $event->description,
                    'banner_image_path' => $event->banner_image_path,
                    'banner_image_url' => $event->bannerUrl(),
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

    public function all(): Response
    {
        return Inertia::render('admin/Events', [
            'events' => FundCycleEvent::query()
                ->with('fundCycle:id,name,status')
                ->withCount('orders')
                ->latest('order_open_at')
                ->latest('id')
                ->get()
                ->map(fn (FundCycleEvent $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'description' => $event->description,
                    'banner_image_url' => $event->bannerUrl(),
                    'order_open_at' => $event->order_open_at?->format('Y-m-d H:i'),
                    'order_close_at' => $event->order_close_at?->format('Y-m-d H:i'),
                    'expected_delivery_date' => $event->expected_delivery_date?->format('Y-m-d'),
                    'fund_cycle' => [
                        'id' => $event->fund_cycle_id,
                        'name' => $event->fundCycle?->name,
                        'status' => $event->fundCycle?->status,
                    ],
                    'orders_count' => $event->orders_count,
                    'created_at' => $event->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function show(FundCycleEvent $fundCycleEvent): Response
    {
        $fundCycleEvent->load([
            'fundCycle:id,name,status,start_date,lock_date,maturity_date,settlement_date',
            'packages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'pickupPoints' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'expenses' => fn ($q) => $q->with('createdBy:id,name')->orderByDesc('expense_date')->orderByDesc('id'),
        ]);

        $expenses = $fundCycleEvent->expenses;

        return Inertia::render('admin/EventDetails', [
            'eventStatuses' => FundCycleEventStatus::options(),
            'packageStatuses' => EventPackageStatus::options(),
            'packageUnitTypes' => EventPackageUnitType::options(),
            'expenseCategories' => EventExpenseCategory::options(),
            'event' => [
                'id' => $fundCycleEvent->id,
                'title' => $fundCycleEvent->title,
                'slug' => $fundCycleEvent->slug,
                'status' => $fundCycleEvent->status->value,
                'status_label' => $fundCycleEvent->status->label(),
                'description' => $fundCycleEvent->description,
                'banner_image_url' => $fundCycleEvent->bannerUrl(),
                'order_open_at' => $fundCycleEvent->order_open_at?->format('Y-m-d\\TH:i'),
                'order_close_at' => $fundCycleEvent->order_close_at?->format('Y-m-d\\TH:i'),
                'expected_delivery_date' => $fundCycleEvent->expected_delivery_date?->format('Y-m-d'),
                'created_at' => $fundCycleEvent->created_at?->format('d M Y, h:i A'),
                'updated_at' => $fundCycleEvent->updated_at?->format('d M Y, h:i A'),
                'fund_cycle' => [
                    'id' => $fundCycleEvent->fund_cycle_id,
                    'name' => $fundCycleEvent->fundCycle?->name,
                    'status' => $fundCycleEvent->fundCycle?->status,
                    'status_label' => $fundCycleEvent->fundCycle?->status
                        ? FundCycle::statusLabel($fundCycleEvent->fundCycle->status)
                        : null,
                    'start_date' => $fundCycleEvent->fundCycle?->start_date?->format('Y-m-d'),
                    'lock_date' => $fundCycleEvent->fundCycle?->lock_date?->format('Y-m-d'),
                    'maturity_date' => $fundCycleEvent->fundCycle?->maturity_date?->format('Y-m-d'),
                    'settlement_date' => $fundCycleEvent->fundCycle?->settlement_date?->format('Y-m-d'),
                ],
                'packages' => $fundCycleEvent->packages
                    ->map(fn (EventPackage $pkg): array => [
                        'id' => $pkg->id,
                        'name' => $pkg->name,
                        'description' => $pkg->description,
                        'unit_type' => $pkg->unit_type->value,
                        'unit_type_label' => $pkg->unit_type->label(),
                        'unit_size' => (string) $pkg->unit_size,
                        'unit_label' => $pkg->unitLabel(),
                        'package_price' => $pkg->package_price,
                        'advance_percent' => $pkg->advance_percent,
                        'min_qty_per_order' => $pkg->min_qty_per_order,
                        'max_qty_per_order' => $pkg->max_qty_per_order,
                        'stock_qty' => $pkg->stock_qty,
                        'sold_qty' => $pkg->sold_qty,
                        'remaining_qty' => $pkg->remainingQty(),
                        'sort_order' => $pkg->sort_order,
                        'status' => $pkg->status->value,
                        'status_label' => $pkg->status->label(),
                    ])
                    ->values(),
                'pickup_points' => $fundCycleEvent->pickupPoints
                    ->map(fn (EventPickupPoint $point): array => [
                        'id' => $point->id,
                        'name' => $point->name,
                        'area' => $point->area,
                        'address' => $point->address,
                        'contact_person' => $point->contact_person,
                        'phone' => $point->phone,
                        'sort_order' => $point->sort_order,
                        'is_active' => $point->is_active,
                    ])
                    ->values(),
                'expenses' => $expenses
                    ->map(fn (EventExpense $expense): array => [
                        'id' => $expense->id,
                        'expense_date' => $expense->expense_date?->format('Y-m-d'),
                        'category' => $expense->category->value,
                        'category_label' => $expense->category->label(),
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                        'receipt_path' => $expense->receipt_path,
                        'receipt_url' => $expense->receiptUrl(),
                        'created_by_name' => $expense->createdBy?->name,
                        'created_at' => $expense->created_at?->format('d M Y, h:i A'),
                    ])
                    ->values(),
                'expense_summary' => [
                    'total_amount' => (int) $expenses->sum('amount'),
                    'entry_count' => $expenses->count(),
                ],
            ],
        ]);
    }

    public function updateFromDetails(
        UpdateFundCycleEventRequest $request,
        FundCycleEvent $fundCycleEvent,
    ): RedirectResponse {
        $attributes = $request->validated();
        $attributes['slug'] = $this->generateUniqueSlug($attributes['title'], $fundCycleEvent->id);

        $fundCycleEvent->update($attributes);

        return to_route('admin.events.show', $fundCycleEvent);
    }

    public function uploadCover(
        UploadFundCycleEventBannerRequest $request,
        FundCycleEvent $fundCycleEvent,
    ): RedirectResponse {
        $newPath = $request->file('cover_image')?->store('event-banners', FundCycleEvent::bannerDisk());

        if (! is_string($newPath) || $newPath === '') {
            return back()->withErrors([
                'cover_image' => 'Cover image upload failed. Please check storage configuration and try again.',
            ]);
        }

        if ($newPath !== null) {
            if ($fundCycleEvent->banner_image_path !== null) {
                Storage::disk(FundCycleEvent::bannerDisk())->delete($fundCycleEvent->banner_image_path);
            }

            $fundCycleEvent->update([
                'banner_image_path' => $newPath,
            ]);
        }

        return to_route('admin.events.show', $fundCycleEvent);
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
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
