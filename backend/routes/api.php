<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatBackupController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatFolderController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\CommunityInvitationController;
use App\Http\Controllers\Api\CommunityPlaceOfferController;
use App\Http\Controllers\Api\CommunitySettingsController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\JoinInvitationController;
use App\Http\Controllers\Api\MemberProfileController;
use App\Http\Controllers\Api\PlaceAdministratorController;
use App\Http\Controllers\Api\PlaceAudienceController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\PlaceOfferController;
use App\Http\Controllers\Api\PlaceRequirementController;
use App\Http\Controllers\Api\PlaceRequirementResponseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/community/branding', [CommunitySettingsController::class, 'branding']);

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
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{chat}', [ChatController::class, 'show']);
    Route::patch('/chats/{chat}', [ChatController::class, 'update']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
    Route::get('/chats/{chat}/messages', [ChatMessageController::class, 'index'])->scopeBindings();
    Route::post('/chats/{chat}/messages', [ChatMessageController::class, 'store'])->scopeBindings();
    Route::get('/chat-folders', [ChatFolderController::class, 'index']);
    Route::post('/chat-folders', [ChatFolderController::class, 'store']);
    Route::patch('/chat-folders/{folder}', [ChatFolderController::class, 'update']);
    Route::delete('/chat-folders/{folder}', [ChatFolderController::class, 'destroy']);
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
