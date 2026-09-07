<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipApplicationPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_print_membership_application_with_personal_information(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@aselco.ph',
            'role' => 'Administrator',
            'user_type' => 'support',
        ]);
        $customer = User::factory()->create([
            'name' => 'Gemma Aba-a',
            'role' => 'User',
            'user_type' => 'customer',
            'contact_no' => '09171234567',
        ]);
        AccountLink::query()->create([
            'user_id' => $customer->id,
            'account_number' => '550006052',
            'owner_name' => 'ABA-A, GEMMA C.',
        ]);
        MemberProfile::query()->create([
            'user_id' => $customer->id,
            'region_code' => '160000000',
            'region_name' => 'Caraga',
            'province_name' => 'Agusan del Sur',
            'city_municipality_name' => 'San Francisco',
            'barangay_name' => 'Poblacion',
            'street' => 'Rizal Street',
            'sitio' => 'Purok 1',
            'civil_status' => 'married',
            'sex' => 'female',
            'contact_no' => '09171234567',
            'remarks' => 'New connection applicant',
            'address' => 'Rizal Street, Purok 1, Poblacion, San Francisco, Agusan del Sur, Caraga',
        ]);

        $this->actingAs($admin)
            ->get(route('access.customers.membership-application', $customer))
            ->assertOk()
            ->assertSee('Application for Juridical, Joint, and Single Membership')
            ->assertSee('ABA-A, GEMMA C.')
            ->assertSee('Poblacion')
            ->assertSee('San Francisco')
            ->assertSee('Rizal Street')
            ->assertSee('Married')
            ->assertSee('Female')
            ->assertSee('09171234567')
            ->assertSee('550006052')
            ->assertSee('Print / Save as PDF')
            ->assertSee('Sign here')
            ->assertSee('Upload')
            ->assertSee('Camera')
            ->assertSee('id="applicantPad"', false)
            ->assertSee('id="consentPad"', false)
            ->assertSee('capture="user"', false);
    }

    public function test_member_cannot_open_another_customers_application_form(): void
    {
        $member = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
        ]);
        $other = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
        ]);

        $this->actingAs($member)
            ->get(route('access.customers.membership-application', $other))
            ->assertForbidden();
    }
}
