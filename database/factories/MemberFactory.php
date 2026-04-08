<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'managed_by_user_id' => User::factory(),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'relationship_to_user' => fake()->randomElement(Member::relationshipOptions()),
            'units' => fake()->numberBetween(1, 5),
            'registration_fee_amount' => Member::REGISTRATION_FEE_AMOUNT,
            'registration_fee_payment_method' => fake()->randomElement(Member::registrationFeePaymentMethods()),
            'registration_fee_reference_no' => fake()->optional()->bothify('TXN-####??'),
            'registration_fee_proof_path' => 'member-registration-fees/' . fake()->uuid() . '.jpg',
            'status' => MemberStatus::Pending,
            'applied_at' => now(),
            'approved_at' => null,
            'approved_by_user_id' => null,
            'rejection_note' => null,
        ];
    }
}
