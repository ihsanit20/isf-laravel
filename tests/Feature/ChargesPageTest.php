<?php

use App\Enums\MemberStatus;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('members page lists each member charges', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $member = Member::factory()->for($user, 'manager')->create([
        'full_name' => 'Primary Member',
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);
    $otherMember = Member::factory()->for($otherUser, 'manager')->create([
        'status' => MemberStatus::Approved,
        'approved_at' => now(),
        'activated_at' => now(),
    ]);

    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();

    $postedCharge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 100,
        'status' => Charge::STATUS_POSTED,
        'effective_at' => now()->subDay(),
    ]);

    $pendingCharge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 150,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $otherMember->id,
        'amount' => 500,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    ChargeAllocation::query()->create([
        'charge_id' => $postedCharge->id,
        'amount' => 100,
        'confirmed_at' => now(),
    ]);

    ChargeAllocation::query()->create([
        'charge_id' => $pendingCharge->id,
        'amount' => 50,
        'confirmed_at' => now(),
        'reversed_at' => now(),
    ]);

    actingAs($user);

    get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Members')
            ->has('members', 1)
            ->where('members.0.full_name', 'Primary Member')
            ->has('members.0.charges', 2)
            ->where('members.0.charges.0.id', $pendingCharge->id)
            ->where('members.0.charges.0.status', Charge::STATUS_PENDING)
            ->where('members.0.charges.0.paid_at', null)
            ->where('members.0.charges.1.id', $postedCharge->id)
            ->where('members.0.charges.1.title', $category->title)
            ->where('members.0.charges.1.status', Charge::STATUS_POSTED));
});

test('old my charges page redirects to members', function () {
    actingAs(User::factory()->create());

    get(route('charges.index'))
        ->assertRedirect('/my-membership');
});

test('guests are redirected from my charges', function () {
    get(route('charges.index'))
        ->assertRedirect(route('login'));
});
