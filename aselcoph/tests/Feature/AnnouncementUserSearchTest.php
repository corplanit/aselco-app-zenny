<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementUserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_users_matches_name_email_and_contact(): void
    {
        $staff = User::factory()->create(['name' => 'Staff User']);
        User::factory()->create([
            'name' => 'Helen Santos',
            'email' => 'helen@example.com',
            'contact_no' => '09171234567',
        ]);
        User::factory()->create([
            'name' => 'Other Member',
            'email' => 'other@example.com',
            'contact_no' => '09998887777',
        ]);

        $byName = $this->actingAs($staff)->getJson(route('announcements.search-users', ['q' => 'helen']));
        $byName->assertOk();
        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.name', 'Helen Santos');

        $byEmail = $this->actingAs($staff)->getJson(route('announcements.search-users', ['q' => 'helen@']));
        $byEmail->assertOk();
        $byEmail->assertJsonPath('data.0.email', 'helen@example.com');

        $byContact = $this->actingAs($staff)->getJson(route('announcements.search-users', ['q' => '0917123']));
        $byContact->assertOk();
        $byContact->assertJsonPath('data.0.contact_no', '09171234567');

        $tooShort = $this->actingAs($staff)->getJson(route('announcements.search-users', ['q' => 'h']));
        $tooShort->assertOk();
        $tooShort->assertJsonCount(0, 'data');
    }
}
