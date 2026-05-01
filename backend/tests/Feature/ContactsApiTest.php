<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_add_contact_and_list_it(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts', ['contact_user_id' => $target->id])
            ->assertCreated()
            ->assertJsonPath('contact.id', $target->id);

        $this->assertDatabaseHas('user_contacts', [
            'user_id' => $member->id,
            'contact_user_id' => $target->id,
        ]);

        $this->actingAs($member)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.id', $target->id);
    }

    public function test_member_cannot_add_self_as_contact(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts', ['contact_user_id' => $member->id])
            ->assertUnprocessable();

        $this->assertDatabaseCount('user_contacts', 0);
    }

    public function test_add_contact_is_idempotent_for_duplicate_additions(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts', ['contact_user_id' => $target->id])
            ->assertCreated();

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts', ['contact_user_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('contact.id', $target->id);

        $this->assertDatabaseCount('user_contacts', 1);
    }

    public function test_search_uses_exact_email_match_and_excludes_self(): void
    {
        $member = User::factory()->create([
            'user_type' => 'member',
            'email' => 'owner@example.test',
        ]);
        $target = User::factory()->create([
            'user_type' => 'member',
            'email' => 'target@example.test',
        ]);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts/search', ['email' => 'TARGET@example.test'])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts/search', ['email' => 'target@example'])
            ->assertOk()
            ->assertJsonPath('user', null);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/contacts/search', ['email' => 'owner@example.test'])
            ->assertOk()
            ->assertJsonPath('user', null);
    }
}
