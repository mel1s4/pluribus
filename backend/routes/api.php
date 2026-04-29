<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatBackupController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\CommunityInvitationController;
use App\Http\Controllers\Api\CommunityPlaceOfferController;
use App\Http\Controllers\Api\CommunitySettingsController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\JoinInvitationController;
use App\Http\Controllers\Api\MemberProfileController;
use App\Http\Controllers\Api\PlaceAdministratorController;
use App\Http\Controllers\Api\PlaceAudienceController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\PlaceOfferController;
use App\Http\Controllers\Api\PlaceRequirementController;
use App\Http\Controllers\Api\PlaceRequirementResponseController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserAdminController;
use App\Http\Controllers\Api\UserFavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/community/branding', [CommunitySettingsController::class, 'branding']);

Route::get('/places/{place}/public', [PlaceController::class, 'showPublic']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::get('/join-invitations/{token}', [JoinInvitationController::class, 'show'])
    ->middleware('throttle:join-invitation-show');

Route::post('/join-invitations/{token}/register', [JoinInvitationController::class, 'register'])
    ->middleware('throttle:join-invitation-register');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar']);

    Route::get('/user-favorites', [UserFavoriteController::class, 'index']);
    Route::post('/user-favorites', [UserFavoriteController::class, 'store']);
    Route::put('/user-favorites/reorder', [UserFavoriteController::class, 'reorder']);
    Route::delete('/user-favorites/{routeKey}', [UserFavoriteController::class, 'destroy'])
        ->where('routeKey', '[a-z0-9-]+');

    Route::get('/members/{user}', [MemberProfileController::class, 'show']);

    Route::get('/users', [UserAdminController::class, 'index']);
    Route::get('/invitations', [CommunityInvitationController::class, 'index']);
    Route::post('/invitations', [CommunityInvitationController::class, 'store']);
    Route::delete('/invitations/{invitation}', [CommunityInvitationController::class, 'destroy']);
    Route::post('/users', [UserAdminController::class, 'store']);
    Route::get('/users/{user}', [UserAdminController::class, 'show']);
    Route::patch('/users/{user}', [UserAdminController::class, 'update']);
    Route::delete('/users/{user}', [UserAdminController::class, 'destroy']);

    Route::get('/community', [CommunitySettingsController::class, 'show']);
    Route::get('/community/leadership', [CommunitySettingsController::class, 'leadership']);
    Route::patch('/community', [CommunitySettingsController::class, 'update']);
    Route::patch('/community/currency', [CommunitySettingsController::class, 'updateCurrency']);

    Route::get('/community-place-offers', [CommunityPlaceOfferController::class, 'index']);
    Route::get('/global-search', [GlobalSearchController::class, 'index']);
    Route::get('/community-map/places', [PlaceController::class, 'mapIndex']);
    Route::get('/discovery/calendar', [DiscoveryController::class, 'calendar']);
    Route::get('/discovery/map', [DiscoveryController::class, 'map']);
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
    Route::get('/chats/{chat}', [ChatController::class, 'show']);
    Route::patch('/chats/{chat}', [ChatController::class, 'update']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
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
    Route::post('/places/{place}/offers', [PlaceOfferController::class, 'store'])->scopeBindings();
    Route::patch('/places/{place}/offers/{offer}', [PlaceOfferController::class, 'update'])->scopeBindings();
    Route::delete('/places/{place}/offers/{offer}', [PlaceOfferController::class, 'destroy'])->scopeBindings();

    Route::get('/places/{place}/requirements', [PlaceRequirementController::class, 'index'])->scopeBindings();
    Route::post('/places/{place}/requirements', [PlaceRequirementController::class, 'store'])->scopeBindings();
    Route::patch('/places/{place}/requirements/{requirement}', [PlaceRequirementController::class, 'update'])->scopeBindings();
    Route::delete('/places/{place}/requirements/{requirement}', [PlaceRequirementController::class, 'destroy'])->scopeBindings();

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
});
