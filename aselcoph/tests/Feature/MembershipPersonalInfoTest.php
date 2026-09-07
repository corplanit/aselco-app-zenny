<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPersonalInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_stays_gated_until_personal_info_is_saved(): void
    {
        $user = $this->member();
        AccountLink::query()->create([
            'user_id' => $user->id,
            'account_number' => '550006052',
            'owner_name' => 'ABA-A, GEMMA C.',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/membership/status')
            ->assertOk()
            ->assertJsonPath('needs_membership_stepper', true)
            ->assertJsonPath('has_personal_info', false)
            ->assertJsonPath('link_count', 1);
    }

    public function test_member_can_save_personal_information_after_account_link(): void
    {
        $user = $this->member();
        AccountLink::query()->create([
            'user_id' => $user->id,
            'account_number' => '550006052',
            'owner_name' => 'ABA-A, GEMMA C.',
        ]);

        $payload = $this->validProfile();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/membership/profile', $payload)
            ->assertOk()
            ->assertJsonPath('has_personal_info', true)
            ->assertJsonPath('needs_membership_stepper', false)
            ->assertJsonPath('data.barangay_name', 'Poblacion')
            ->assertJsonPath('data.civil_status', 'married');

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $user->id,
            'city_municipality_name' => 'San Francisco',
            'sex' => 'female',
        ]);

        $this->assertSame('09171234567', $user->fresh()?->contact_no);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/membership/status')
            ->assertOk()
            ->assertJsonPath('needs_membership_stepper', false)
            ->assertJsonPath('has_personal_info', true);
    }

    public function test_seminar_date_is_optional_and_staff_only_fields_are_ignored(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/membership/profile', [
                ...$this->validProfile(),
                'date_of_seminar' => null,
                'membership_or' => 'OR-123',
                'area_manager' => 'Should ignore',
                'date_issued' => '2026-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.date_of_seminar', null);

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/membership/profile')
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('membership_or', $data);
        $this->assertArrayNotHasKey('area_manager', $data);
        $this->assertArrayNotHasKey('date_issued', $data);
    }

    public function test_profile_requires_address_civil_status_sex_and_contact(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/membership/profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'region_name',
                'city_municipality_name',
                'barangay_name',
                'civil_status',
                'sex',
                'contact_no',
            ]);
    }

    private function member(): User
    {
        return User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validProfile(): array
    {
        return [
            'region_code' => '160000000',
            'region_name' => 'Caraga',
            'province_code' => '160300000',
            'province_name' => 'Agusan del Sur',
            'city_municipality_code' => '160301000',
            'city_municipality_name' => 'San Francisco',
            'barangay_code' => '160301001',
            'barangay_name' => 'Poblacion',
            'street' => 'Rizal Street',
            'sitio' => 'Purok 1',
            'civil_status' => 'married',
            'sex' => 'female',
            'contact_no' => '09171234567',
            'date_of_seminar' => '2026-03-15',
            'remarks' => 'New connection applicant',
        ];
    }
}
