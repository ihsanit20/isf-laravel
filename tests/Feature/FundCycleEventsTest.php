<?php

use App\Enums\FundCycleEventStatus;
use App\Models\FundCycle;
use App\Models\FundCycleEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('admins can visit the fund cycle events page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Events Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => '2026-05-01',
        'slots' => ['May 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    $fundCycle->events()->create([
        'title' => 'Eid Grocery Event',
        'slug' => 'eid-grocery-event',
        'status' => FundCycleEventStatus::Draft,
        'description' => 'Pre-order event',
        'banner_image_path' => 'events/eid-banner.jpg',
        'order_open_at' => '2026-05-20 10:00:00',
        'order_close_at' => '2026-05-25 22:00:00',
        'expected_delivery_date' => '2026-05-28',
    ]);

    actingAs($admin)
        ->get(route('admin.fund-cycles.events.index', $fundCycle))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/FundCycleEvents')
            ->has('events', 1)
            ->where('events.0.slug', 'eid-grocery-event')
            ->where('eventStatuses.0.value', FundCycleEventStatus::Draft->value));
});

test('members cannot visit the fund cycle events page', function () {
    $member = User::factory()->create([
        'role' => 'member',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Events Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => '2026-05-01',
        'slots' => ['May 2026'],
        'created_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
    ]);

    actingAs($member)
        ->get(route('admin.fund-cycles.events.index', $fundCycle))
        ->assertForbidden();
});

test('admins can create a fund cycle event', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Events Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => '2026-05-01',
        'slots' => ['May 2026'],
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.events.store', $fundCycle), [
        'title' => 'Qurbani Package Event',
        'status' => FundCycleEventStatus::Published->value,
        'description' => 'Event for package booking',
        'banner_image_path' => 'events/qurbani-banner.jpg',
        'order_open_at' => '2026-06-01 09:00:00',
        'order_close_at' => '2026-06-10 23:00:00',
        'expected_delivery_date' => '2026-06-15',
    ])->assertRedirect(route('admin.fund-cycles.events.index', $fundCycle));

    $event = FundCycleEvent::query()->where('slug', 'qurbani-package-event')->first();

    expect($event)->not->toBeNull()
        ->and($event?->fund_cycle_id)->toBe($fundCycle->id)
        ->and($event?->status)->toBe(FundCycleEventStatus::Published);
});

test('admins can update a fund cycle event', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'Events Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'unit_amount' => 1000,
        'start_date' => '2026-05-01',
        'slots' => ['May 2026'],
        'created_by_user_id' => $admin->id,
    ]);
    $event = $fundCycle->events()->create([
        'title' => 'Initial Event',
        'slug' => 'initial-event',
        'status' => FundCycleEventStatus::Draft,
        'description' => null,
        'banner_image_path' => null,
        'order_open_at' => '2026-06-01 09:00:00',
        'order_close_at' => '2026-06-10 23:00:00',
        'expected_delivery_date' => null,
    ]);

    actingAs($admin);

    put(route('admin.fund-cycles.events.update', [$fundCycle, $event]), [
        'title' => 'Updated Event',
        'status' => FundCycleEventStatus::Cancelled->value,
        'description' => 'Updated description',
        'banner_image_path' => 'events/updated-banner.jpg',
        'order_open_at' => '2026-06-02 10:00:00',
        'order_close_at' => '2026-06-12 23:30:00',
        'expected_delivery_date' => '2026-06-18',
    ])->assertRedirect(route('admin.fund-cycles.events.index', $fundCycle));

    expect($event->refresh()->title)->toBe('Updated Event')
        ->and($event->slug)->toBe('updated-event')
        ->and($event->status)->toBe(FundCycleEventStatus::Cancelled)
        ->and($event->banner_image_path)->toBe('events/updated-banner.jpg');
});
