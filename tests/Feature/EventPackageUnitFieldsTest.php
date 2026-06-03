<?php

use App\Enums\EventPackageStatus;
use App\Enums\EventPackageUnitType;
use App\Enums\FundCycleEventStatus;
use App\Models\EventOrder;
use App\Models\EventOrderItem;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

test('placing order snapshots unit fields on order items', function () {
    $user = User::factory()->create();

    $cycle = FundCycle::query()->create([
        'name' => 'Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Event',
        'slug' => 'unit-snapshot-'.uniqid(),
        'status' => FundCycleEventStatus::Published,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    $package = $event->packages()->create([
        'name' => 'Ghee 1kg',
        'unit_type' => EventPackageUnitType::Kg->value,
        'unit_size' => 1,
        'package_price' => 1200,
        'advance_percent' => 0,
        'min_qty_per_order' => 1,
        'status' => EventPackageStatus::Active->value,
    ]);

    $pickup = $event->pickupPoints()->create([
        'name' => 'Hub',
        'is_active' => true,
    ]);

    $response = postJson('/api/v1/orders', [
        'event_slug' => $event->slug,
        'customer_name' => 'Buyer',
        'customer_phone' => '01712345678',
        'pickup_point_id' => $pickup->id,
        'items' => [
            ['package_id' => $package->id, 'quantity' => 3],
        ],
    ]);

    $response->assertCreated();

    $order = EventOrder::query()->where('order_number', $response->json('data.order_number'))->first();
    $item = EventOrderItem::query()->where('event_order_id', $order->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->unit_type)->toBe(EventPackageUnitType::Kg)
        ->and((float) $item->unit_size)->toBe(1.0)
        ->and($item->quantity)->toBe(3)
        ->and($item->quantityLabel())->toBe('3 × 1 kg = 3 kg');
});

test('admin can create package with unit fields', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $cycle = FundCycle::query()->create([
        'name' => 'Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => now()->toDateString(),
        'created_by_user_id' => $admin->id,
    ]);

    $event = FundCycleEvent::query()->create([
        'fund_cycle_id' => $cycle->id,
        'title' => 'Event',
        'slug' => 'admin-package-'.uniqid(),
        'status' => FundCycleEventStatus::Draft,
        'order_open_at' => now()->subDay(),
        'order_close_at' => now()->addMonth(),
    ]);

    actingAs($admin)
        ->post("/admin/events/{$event->id}/packages", [
            'name' => 'Oil 2L',
            'unit_type' => EventPackageUnitType::Liter->value,
            'unit_size' => 2,
            'package_price' => 350,
            'advance_percent' => 10,
            'min_qty_per_order' => 1,
            'status' => EventPackageStatus::Draft->value,
        ])
        ->assertRedirect();

    $package = $event->packages()->first();

    expect($package)->not->toBeNull()
        ->and($package->unit_type)->toBe(EventPackageUnitType::Liter)
        ->and((float) $package->unit_size)->toBe(2.0)
        ->and($package->unitLabel())->toBe('2 L');
});
