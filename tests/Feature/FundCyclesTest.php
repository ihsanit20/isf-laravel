<?php

use App\Models\FundCycle;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('admins can visit the fund cycle admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    FundCycle::query()->create([
        'name' => 'April 2026 Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'start_date' => '2026-04-01',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.fund-cycles.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('admin/FundCycles')
            ->has('fundCycles', 1)
            ->where('statuses.0', FundCycle::STATUS_DRAFT));
});

test('members cannot visit the fund cycle admin page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    actingAs($user)
        ->get(route('admin.fund-cycles.index'))
        ->assertForbidden();
});

test('admins can create a fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    actingAs($admin);

    post(route('admin.fund-cycles.store'), [
        'name' => 'May 2026 Cycle',
        'status' => FundCycle::STATUS_OPEN,
        'start_date' => '2026-05-01',
        'lock_date' => '2026-05-10',
        'maturity_date' => '2026-06-10',
        'settlement_date' => '2026-06-15',
        'notes' => 'Initial cycle window',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    $fundCycle = FundCycle::query()->where('name', 'May 2026 Cycle')->first();

    expect($fundCycle)->not->toBeNull()
        ->and($fundCycle?->created_by_user_id)->toBe($admin->id);
});

test('admins can update a fund cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);
    $fundCycle = FundCycle::query()->create([
        'name' => 'June 2026 Cycle',
        'status' => FundCycle::STATUS_DRAFT,
        'start_date' => '2026-06-01',
        'created_by_user_id' => $admin->id,
    ]);

    actingAs($admin);

    put(route('admin.fund-cycles.update', $fundCycle), [
        'name' => 'June 2026 Locked Cycle',
        'status' => FundCycle::STATUS_LOCKED,
        'start_date' => '2026-06-01',
        'lock_date' => '2026-06-10',
        'maturity_date' => '2026-07-10',
        'settlement_date' => '2026-07-15',
        'notes' => 'Ready for investment',
    ])->assertRedirect(route('admin.fund-cycles.index'));

    expect($fundCycle->refresh()->name)->toBe('June 2026 Locked Cycle')
        ->and($fundCycle->status)->toBe(FundCycle::STATUS_LOCKED)
        ->and($fundCycle->notes)->toBe('Ready for investment');
});
