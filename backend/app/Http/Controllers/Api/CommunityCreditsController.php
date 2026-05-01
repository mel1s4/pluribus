<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use App\Models\User;
use App\Models\WalletLedgerBlock;
use App\Models\WalletLedgerEntry;
use App\Support\WalletLedger\LedgerAuditRowFormatter;
use App\Support\WalletLedger\LedgerSigner;
use App\Support\WalletLedger\WalletLedgerTipPayload;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CommunityCreditsController extends Controller
{
    public function __construct(
        private LedgerSigner $ledgerSigner,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $raw = (string) (WalletLedgerEntry::query()
            ->where('community_id', $community->id)
            ->where('type', WalletLedgerEntry::TYPE_GRANT)
            ->where('actor_kind', WalletLedgerEntry::ACTOR_COMMUNITY_GRANT)
            ->sum('amount') ?? '0');

        $user = $request->user();
        $isMember = $user instanceof User
            && $user->communities()->where('communities.id', $community->id)->exists();

        return response()->json([
            'community' => (new CommunityResource($community))->toArray($request),
            'credits_granted_total' => WalletMoney::normalize($raw),
            'is_member' => $isMember,
        ]);
    }

    public function exportLedger(Request $request, string $slug): Response|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $user->communities()->where('communities.id', $community->id)->exists()) {
            abort(403, __('You are not a member of this community.'));
        }

        $communityId = (int) $community->id;

        $raw = (string) (WalletLedgerEntry::query()
            ->where('community_id', $communityId)
            ->where('type', WalletLedgerEntry::TYPE_GRANT)
            ->where('actor_kind', WalletLedgerEntry::ACTOR_COMMUNITY_GRANT)
            ->sum('amount') ?? '0');

        $tip = WalletLedgerTipPayload::latestSealedBlock($communityId);

        $blocks = WalletLedgerBlock::query()
            ->where('community_id', $communityId)
            ->orderBy('height')
            ->get()
            ->map(fn (WalletLedgerBlock $b): array => [
                'id' => $b->id,
                'community_id' => $b->community_id,
                'height' => $b->height,
                'prev_commitment' => $b->prev_commitment,
                'merkle_root' => $b->merkle_root,
                'first_entry_id' => $b->first_entry_id,
                'last_entry_id' => $b->last_entry_id,
                'entry_count' => $b->entry_count,
                'block_commitment' => $b->block_commitment,
                'operator_signature' => $b->operator_signature,
                'sealed_at' => $b->sealed_at?->toIso8601String(),
                'created_at' => $b->created_at?->toIso8601String(),
                'updated_at' => $b->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $entries = WalletLedgerEntry::query()
            ->where('community_id', $communityId)
            ->with('ledgerBlock')
            ->orderBy('id')
            ->get()
            ->map(fn (WalletLedgerEntry $e): array => LedgerAuditRowFormatter::format($e))
            ->values()
            ->all();

        $payload = [
            'export_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'community' => [
                'id' => $community->id,
                'slug' => $community->slug,
                'name' => $community->name,
            ],
            'credits_granted_total' => WalletMoney::normalize($raw),
            'wallet_ledger' => [
                'algorithm' => 'ed25519',
                'operator_public_key_b64' => $this->ledgerSigner->publicKeyBase64(),
                'tip' => WalletLedgerTipPayload::tipPayload($tip),
            ],
            'blocks' => $blocks,
            'entries' => $entries,
        ];

        $safeSlug = Str::slug((string) $community->slug) ?: 'community';
        $filename = 'community-'.$safeSlug.'-ledger-export.json';
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
