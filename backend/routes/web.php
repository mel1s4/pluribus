<?php

use App\Http\Controllers\JoinInvitationShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/join-invitation-share/{token}', [JoinInvitationShareController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{16,200}')
    ->middleware('throttle:join-invitation-share');
