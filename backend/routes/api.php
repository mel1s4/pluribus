<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ChatBackupController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatMemberController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\CommunityInvitationController;
use App\Http\Controllers\Api\CommunityMembershipController;
use App\Http\Controllers\Api\CommunityProjectController;
use App\Http\Controllers\Api\CommunityCreditsController;
use App\Http\Controllers\Api\CommunityMicrositeController;
use App\Http\Controllers\Api\CommunityPublicLegalDocumentsController;
use App\Http\Controllers\Api\CommunityPlaceOfferController;
use App\Http\Controllers\Api\CommunityAdminController;
use App\Http\Controllers\Api\CommunityResolveHostController;
use App\Http\Controllers\Api\CommunitySettingsController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\JoinInvitationController;
use App\Http\Controllers\Api\MemberProfileController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PersonificationController;
use App\Http\Controllers\Api\PlaceAdministratorController;
use App\Http\Controllers\Api\PlaceAudienceController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\PlaceOfferCsvController;
use App\Http\Controllers\Api\PlaceOfferController;
use App\Http\Controllers\Api\PlaceWalletController;
use App\Http\Controllers\Api\PlaceRequirementCsvController;
use App\Http\Controllers\Api\PlaceRequirementController;
use App\Http\Controllers\Api\PlaceRequirementResponseController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TableAccessLinkController;
use App\Http\Controllers\Api\UserAdminController;
use App\Http\Controllers\Api\UserVotingIdAuditController;
use App\Http\Controllers\Api\UserFavoriteController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\MyCommunitiesController;
use App\Http\Controllers\Api\MyCommunityProjectsController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\VisitorAuthController;
use App\Http\Controllers\Api\PlaceTableController;
use App\Http\Controllers\Api\TableSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/community/branding', [CommunitySettingsController::class, 'branding']);
Route::get('/community/resolve-host', [CommunityResolveHostController::class, 'show']);

Route::get('/places/{place}/public', [PlaceController::class, 'showPublic']);

Route::get('/communities/{slug}/microsite', [CommunityMicrositeController::class, 'show']);
Route::get('/communities/{slug}/credits', [CommunityCreditsController::class, 'show']);
Route::get('/communities/{slug}/legal-documents', [CommunityPublicLegalDocumentsController::class, 'show'])
    ->middleware('throttle:join-invitation-show');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('throttle:login');
Route::post('/password/forgot', [PasswordResetController::class, 'request'])
    ->middleware('throttle:password-forgot');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:password-reset');
Route::post('/visitor-auth/request-link', [VisitorAuthController::class, 'requestLoginLink'])
    ->middleware('throttle:visitor-login-request');
Route::post('/visitor-auth/consume/{token}', [VisitorAuthController::class, 'consumeLoginLink'])
    ->middleware('throttle:visitor-login-consume');
Route::get('/table-access/{token}', [TableAccessLinkController::class, 'resolve'])
    ->middleware('throttle:table-access-resolve');

Route::get('/join-invitations/{token}/verify/{verifyToken}', [JoinInvitationController::class, 'showVerify'])
    ->middleware('throttle:join-invitation-verify-show');
Route::post('/join-invitations/{token}/verify/{verifyToken}/register', [JoinInvitationController::class, 'registerVerified'])
    ->middleware('throttle:join-invitation-register-verified');
Route::post('/join-invitations/{token}/verify-email', [JoinInvitationController::class, 'requestVerifyEmail'])
    ->middleware('throttle:join-invitation-verify-email');

Route::get('/join-invitations/{token}', [JoinInvitationController::class, 'show'])
    ->middleware('throttle:join-invitation-show');

Route::get('/discovery/map', [DiscoveryController::class, 'map'])
    ->middleware('throttle:discovery-map');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/personification/start', [PersonificationController::class, 'start'])
        ->middleware('throttle:personify');
    Route::post('/personification/stop', [PersonificationController::class, 'stop']);
    Route::get('/personification/status', [PersonificationController::class, 'status']);
    Route::post('/personification/resolve', [PersonificationController::class, 'resolve'])
        ->middleware('throttle:personify');

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware([\App\Http\Middleware\ApplyPersonification::class])->group(function () {
        Route::get('/user', [AuthController::class, 'user']);

        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar']);

        Route::get('/contacts', [ContactController::class, 'index']);
        Route::post('/contacts/search', [ContactController::class, 'search']);
        Route::post('/contacts', [ContactController::class, 'store']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('/user-favorites', [UserFavoriteController::class, 'index']);
        Route::post('/user-favorites', [UserFavoriteController::class, 'store']);
        Route::put('/user-favorites/reorder', [UserFavoriteController::class, 'reorder']);
        Route::delete('/user-favorites/{routeKey}', [UserFavoriteController::class, 'destroy'])
            ->where('routeKey', '[a-z0-9-]+');

        Route::get('/members/{user}', [MemberProfileController::class, 'show']);

        Route::get('/communities/{slug}/credits/ledger-export', [CommunityCreditsController::class, 'exportLedger']);

        Route::get('/users', [UserAdminController::class, 'index']);
        Route::get('/my-communities', [MyCommunitiesController::class, 'index']);
        Route::get('/my-projects', [MyCommunityProjectsController::class, 'index'])->name('myCommunityProjects.index');
        Route::post('/my-communities/join/{token}', [MyCommunitiesController::class, 'joinByInvitation'])
            ->middleware('throttle:join-invitation-show');
        Route::get('/communities/{slug}/projects', [CommunityProjectController::class, 'index'])
            ->where('slug', '[a-z0-9-]+');
        Route::post('/communities/{slug}/projects', [CommunityProjectController::class, 'store'])
            ->where('slug', '[a-z0-9-]+');
        Route::get('/communities/{slug}/projects/{project}', [CommunityProjectController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->whereNumber('project');
        Route::patch('/communities/{slug}/projects/{project}', [CommunityProjectController::class, 'update'])
            ->where('slug', '[a-z0-9-]+')
            ->whereNumber('project');
        Route::delete('/communities/{slug}/projects/{project}', [CommunityProjectController::class, 'destroy'])
            ->where('slug', '[a-z0-9-]+')
            ->whereNumber('project');
        Route::get('/communities', [CommunityAdminController::class, 'index']);
        Route::post('/communities', [CommunityAdminController::class, 'store']);
        Route::get('/communities/{community}', [CommunityAdminController::class, 'show']);
        Route::patch('/communities/{community}', [CommunityAdminController::class, 'update']);
        Route::get('/invitations', [CommunityInvitationController::class, 'index']);
        Route::post('/invitations', [CommunityInvitationController::class, 'store']);
        Route::delete('/invitations/{invitation}', [CommunityInvitationController::class, 'destroy']);
        Route::get('/community/memberships', [CommunityMembershipController::class, 'index']);
        Route::post('/community/memberships', [CommunityMembershipController::class, 'store']);
        Route::patch('/community/memberships/{user}', [CommunityMembershipController::class, 'update'])->scopeBindings();
        Route::delete('/community/memberships/{user}', [CommunityMembershipController::class, 'destroy'])->scopeBindings();
        Route::post('/users', [UserAdminController::class, 'store']);
        Route::get('/users/{user}', [UserAdminController::class, 'show']);
        Route::get('/users/{user}/voting-id-audits', [UserVotingIdAuditController::class, 'index']);
        Route::patch('/users/{user}', [UserAdminController::class, 'update']);
        Route::delete('/users/{user}', [UserAdminController::class, 'destroy']);

        Route::get('/community', [CommunitySettingsController::class, 'show']);
        Route::get('/community/leadership', [CommunitySettingsController::class, 'leadership']);
        Route::patch('/community', [CommunitySettingsController::class, 'update']);
        Route::patch('/community/currency', [CommunitySettingsController::class, 'updateCurrency']);
        Route::patch('/community/legal-documents', [CommunitySettingsController::class, 'updateLegalDocuments']);

        Route::get('/community-place-offers', [CommunityPlaceOfferController::class, 'index']);
        Route::get('/global-search', [GlobalSearchController::class, 'index']);
        Route::get('/community-map/places', [PlaceController::class, 'mapIndex']);
        Route::get('/discovery/calendar', [DiscoveryController::class, 'calendar']);
        Route::get('/groups', [GroupController::class, 'index']);
        Route::post('/groups', [GroupController::class, 'store']);
        Route::get('/groups/{group}', [GroupController::class, 'show']);
        Route::patch('/groups/{group}', [GroupController::class, 'update']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);
        Route::get('/groups/{group}/members', [GroupMemberController::class, 'index'])->scopeBindings();
        Route::post('/groups/{group}/members', [GroupMemberController::class, 'store'])->scopeBindings();
        Route::patch('/groups/{group}/members/{user}', [GroupMemberController::class, 'update'])->scopeBindings();
        Route::delete('/groups/{group}/members/{user}', [GroupMemberController::class, 'destroy'])->scopeBindings();
        Route::get('/calendars', [CalendarController::class, 'index']);
        Route::post('/calendars', [CalendarController::class, 'store']);
        Route::get('/calendars/{calendar}/events', [CalendarController::class, 'events'])->scopeBindings();
        Route::get('/calendars/{calendar}', [CalendarController::class, 'show']);
        Route::patch('/calendars/{calendar}', [CalendarController::class, 'update']);
        Route::delete('/calendars/{calendar}', [CalendarController::class, 'destroy']);
        Route::patch('/events/{type}/{id}/reschedule', [CalendarEventController::class, 'reschedule'])
            ->where(['type' => 'post|task', 'id' => '[0-9]+']);
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
        Route::patch('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::patch('/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
        Route::get('/chats', [ChatController::class, 'index']);
        Route::post('/chats', [ChatController::class, 'store']);
        Route::get('/chats/updates', [ChatMessageController::class, 'updates']);
        Route::get('/chats/{chat}', [ChatController::class, 'show']);
        Route::patch('/chats/{chat}', [ChatController::class, 'update']);
        Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
        Route::post('/chats/{chat}/read', [ChatController::class, 'markRead'])->scopeBindings();
        Route::post('/chats/{chat}/members', [ChatMemberController::class, 'store'])->scopeBindings();
        Route::delete('/chats/{chat}/members/{user}', [ChatMemberController::class, 'destroy'])->scopeBindings();
        Route::get('/chats/{chat}/messages', [ChatMessageController::class, 'index'])->scopeBindings();
        Route::post('/chats/{chat}/messages', [ChatMessageController::class, 'store'])->scopeBindings();
        Route::get('/folders/search', [FolderController::class, 'search']);
        Route::post('/folders/bulk-move', [FolderController::class, 'bulkMove']);
        Route::patch('/folders/reorder', [FolderController::class, 'reorder']);
        Route::get('/folders', [FolderController::class, 'index']);
        Route::post('/folders', [FolderController::class, 'store']);
        Route::get('/folders/{folder}/stats', [FolderController::class, 'stats'])->scopeBindings();
        Route::patch('/folders/{folder}', [FolderController::class, 'update']);
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
        Route::get('/notes', [NoteController::class, 'index']);
        Route::post('/notes', [NoteController::class, 'store']);
        Route::get('/notes/{note}', [NoteController::class, 'show'])->scopeBindings();
        Route::patch('/notes/{note}', [NoteController::class, 'update'])->scopeBindings();
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->scopeBindings();
        Route::get('/notes/{note}/collaborators', [NoteController::class, 'collaboratorsIndex'])->scopeBindings();
        Route::post('/notes/{note}/collaborators', [NoteController::class, 'collaboratorsStore'])->scopeBindings();
        Route::patch('/notes/{note}/collaborators/{user}', [NoteController::class, 'collaboratorsUpdate'])->scopeBindings();
        Route::delete('/notes/{note}/collaborators/{user}', [NoteController::class, 'collaboratorsDestroy'])->scopeBindings();
        Route::post('/notes/{note}/lock', [NoteController::class, 'lockAcquire'])->scopeBindings();
        Route::put('/notes/{note}/lock', [NoteController::class, 'lockRenew'])->scopeBindings();
        Route::delete('/notes/{note}/lock', [NoteController::class, 'lockRelease'])->scopeBindings();
        Route::get('/notes/{note}/revisions', [NoteController::class, 'revisionsIndex'])->scopeBindings();
        Route::get('/notes/{note}/revisions/{revision}', [NoteController::class, 'revisionsShow'])->scopeBindings();
        Route::post('/notes/{note}/revert', [NoteController::class, 'revert'])->scopeBindings();
        Route::get('/chats/{chat}/backups', [ChatBackupController::class, 'index'])->scopeBindings();
        Route::post('/chats/{chat}/backups', [ChatBackupController::class, 'store'])->scopeBindings();
        Route::get('/chat-backups/{backup}/download', [ChatBackupController::class, 'download'])
            ->name('chat-backups.download');

        Route::get('/places', [PlaceController::class, 'index']);
        Route::post('/places', [PlaceController::class, 'store']);
        Route::get('/places/{place}', [PlaceController::class, 'show']);
        Route::patch('/places/{place}', [PlaceController::class, 'update']);
        Route::delete('/places/{place}', [PlaceController::class, 'destroy']);

        Route::get('/places/{place}/offers', [PlaceOfferController::class, 'index'])->scopeBindings();
        Route::get('/places/{place}/offers/{offer}', [PlaceOfferController::class, 'show'])->scopeBindings();
        Route::post('/places/{place}/offers', [PlaceOfferController::class, 'store'])->scopeBindings();
        Route::patch('/places/{place}/offers/{offer}', [PlaceOfferController::class, 'update'])->scopeBindings();
        Route::delete('/places/{place}/offers/{offer}', [PlaceOfferController::class, 'destroy'])->scopeBindings();
        Route::get('/places/{place}/offers/export.csv', [PlaceOfferCsvController::class, 'export'])->scopeBindings();
        Route::post('/places/{place}/offers/import.csv', [PlaceOfferCsvController::class, 'import'])->scopeBindings();

        Route::get('/places/{place}/requirements', [PlaceRequirementController::class, 'index'])->scopeBindings();
        Route::post('/places/{place}/requirements', [PlaceRequirementController::class, 'store'])->scopeBindings();
        Route::patch('/places/{place}/requirements/{requirement}', [PlaceRequirementController::class, 'update'])->scopeBindings();
        Route::delete('/places/{place}/requirements/{requirement}', [PlaceRequirementController::class, 'destroy'])->scopeBindings();
        Route::get('/places/{place}/requirements/export.csv', [PlaceRequirementCsvController::class, 'export'])->scopeBindings();
        Route::post('/places/{place}/requirements/import.csv', [PlaceRequirementCsvController::class, 'import'])->scopeBindings();

        Route::post('/places/{place}/requirements/{requirement}/responses', [PlaceRequirementResponseController::class, 'store'])->scopeBindings();
        Route::delete('/places/{place}/requirements/{requirement}/responses/{response}', [PlaceRequirementResponseController::class, 'destroy'])->scopeBindings();

        Route::get('/places/{place}/audience-members', [PlaceAudienceController::class, 'pickableMembers'])->scopeBindings();
        Route::get('/places/{place}/audiences', [PlaceAudienceController::class, 'index'])->scopeBindings();
        Route::post('/places/{place}/audiences', [PlaceAudienceController::class, 'store'])->scopeBindings();
        Route::patch('/places/{place}/audiences/{audience}', [PlaceAudienceController::class, 'update'])->scopeBindings();
        Route::delete('/places/{place}/audiences/{audience}', [PlaceAudienceController::class, 'destroy'])->scopeBindings();

        Route::get('/places/{place}/administrators', [PlaceAdministratorController::class, 'index'])->scopeBindings();
        Route::post('/places/{place}/administrators', [PlaceAdministratorController::class, 'store'])->scopeBindings();
        Route::patch('/places/{place}/administrators/{user}', [PlaceAdministratorController::class, 'update'])->scopeBindings();
        Route::delete('/places/{place}/administrators/{user}', [PlaceAdministratorController::class, 'destroy'])->scopeBindings();
        Route::get('/places/{place}/tables/{table}', [PlaceTableController::class, 'show'])->scopeBindings();
        Route::get('/places/{place}/tables', [PlaceTableController::class, 'index'])->scopeBindings();
        Route::post('/places/{place}/tables', [PlaceTableController::class, 'store'])->scopeBindings();
        Route::patch('/places/{place}/tables/{table}', [PlaceTableController::class, 'update'])->scopeBindings();
        Route::delete('/places/{place}/tables/{table}', [PlaceTableController::class, 'destroy'])->scopeBindings();
        Route::post('/places/{place}/tables/{table}/access-links', [TableAccessLinkController::class, 'store'])->scopeBindings();
        Route::post('/places/{place}/tables/{table}/access-links/rotate', [TableAccessLinkController::class, 'rotate'])->scopeBindings();
        Route::delete('/places/{place}/tables/{table}/access-links', [TableAccessLinkController::class, 'revoke'])->scopeBindings();
        Route::post('/table-access/{token}/consume', [TableAccessLinkController::class, 'consume']);

        Route::post('/table-session/ping', [TableSessionController::class, 'ping'])
            ->middleware('throttle:table-session-ping');
        Route::delete('/table-session', [TableSessionController::class, 'destroy']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'upsertItem']);
        Route::delete('/cart/items/{placeOffer}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);

        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::get('/places/{place}/orders', [OrderController::class, 'placeIndex']);
        Route::get('/places/{place}/orders/{order}', [OrderController::class, 'placeShow']);
        Route::patch('/places/{place}/orders/{order}', [OrderController::class, 'updatePlaceOrder']);
        Route::patch('/places/{place}/orders/{order}/items/{item}/table', [OrderController::class, 'reassignTable']);

        Route::get('/places/{place}/wallet', [PlaceWalletController::class, 'show'])->scopeBindings();
        Route::post('/places/{place}/wallet/transfer', [PlaceWalletController::class, 'transfer'])->scopeBindings();

        Route::get('/wallet/audit-ledger', [WalletController::class, 'auditLedger']);
        Route::get('/wallet/ledger/public-key', [WalletController::class, 'ledgerPublicKey']);
        Route::get('/wallet/ledger/tip', [WalletController::class, 'ledgerTip']);
        Route::get('/wallet/community-stats', [WalletController::class, 'communityStats']);
        Route::get('/wallet/audit-identity/{transaction}', [WalletController::class, 'auditIdentity'])
            ->scopeBindings();
        Route::get('/wallet/transactions/{transaction}', [WalletController::class, 'showTransaction'])
            ->scopeBindings();
        Route::get('/wallet', [WalletController::class, 'index']);
        Route::post('/wallet/transfer', [WalletController::class, 'transfer']);
        Route::post('/wallet/grants', [WalletController::class, 'grant']);
    });
});
