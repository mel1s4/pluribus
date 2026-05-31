<?php

namespace Tests\Feature;

use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPublicLegalDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_community_legal_documents_by_slug(): void
    {
        $community = Community::current();
        $community->update([
            'slug' => 'riverbend',
            'terms_markdown' => '# T\n\nHello',
            'privacy_policy_markdown' => 'We care.',
        ]);

        $this->getJson('/api/communities/riverbend/legal-documents')
            ->assertOk()
            ->assertJsonPath('community.slug', 'riverbend')
            ->assertJsonPath('community.terms_markdown', '# T\n\nHello')
            ->assertJsonPath('community.privacy_policy_markdown', 'We care.');
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/communities/does-not-exist-yet/legal-documents')
            ->assertNotFound();
    }
}
