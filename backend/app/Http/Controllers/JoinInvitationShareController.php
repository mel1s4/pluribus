<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Support\LocaleOptions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JoinInvitationShareController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $frontendBase = rtrim((string) config('app.frontend_url'), '/');

        if (! $this->tokenLooksValid($token)) {
            return $this->genericShareView($request, $frontendBase, $token);
        }

        $invitation = CommunityInvitation::findByPlainToken($token);
        if ($invitation === null) {
            return $this->genericShareView($request, $frontendBase, $token);
        }

        $invitation->loadMissing('community');
        $community = $invitation->community;

        $ogLocale = $this->communityInviteLocale($community);
        app()->setLocale($ogLocale);

        $redirectLocale = $this->resolveRedirectLocale($request, $community);
        $redirectPath = $redirectLocale === 'es' ? 'invitacion' : 'join';
        $redirectUrl = $frontendBase.'/'.$redirectPath.'/'.$token;

        $communityName = (string) ($community?->name ?? '');
        if ($communityName === '') {
            $communityName = __('join_invitation.share_title_generic');
        }

        $pageTitle = __('join_invitation.share_title', ['community' => $communityName]);
        $pageDescription = __('join_invitation.share_description');
        $ogImage = $community instanceof Community ? $community->publicLogoUrl() : null;

        return view('join-invitation-share', [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'ogImage' => $ogImage,
            'redirectUrl' => $redirectUrl,
            'htmlLang' => $ogLocale,
        ]);
    }

    private function genericShareView(Request $request, string $frontendBase, string $token): View
    {
        $redirectLocale = $this->normalizeLocaleCode((string) $request->query('redirect_locale', ''));
        if ($redirectLocale === null) {
            $fallback = (string) config('app.locale', 'en');
            $redirectLocale = in_array($fallback, LocaleOptions::codes(), true)
                ? $fallback
                : LocaleOptions::default();
        }

        app()->setLocale($redirectLocale);

        $redirectPath = $redirectLocale === 'es' ? 'invitacion' : 'join';
        $redirectUrl = $frontendBase.'/'.$redirectPath.'/'.$token;

        return view('join-invitation-share', [
            'pageTitle' => __('join_invitation.share_title_generic'),
            'pageDescription' => __('join_invitation.share_description'),
            'ogImage' => null,
            'redirectUrl' => $redirectUrl,
            'htmlLang' => $redirectLocale,
        ]);
    }

    private function resolveRedirectLocale(Request $request, ?Community $community): string
    {
        $fromQuery = $this->normalizeLocaleCode((string) $request->query('redirect_locale', ''));
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        return $this->communityInviteLocale($community);
    }

    private function normalizeLocaleCode(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        return in_array($raw, LocaleOptions::codes(), true) ? $raw : null;
    }

    private function communityInviteLocale(?Community $community): string
    {
        if ($community === null) {
            return LocaleOptions::default();
        }
        $code = (string) $community->default_language;

        return in_array($code, LocaleOptions::codes(), true) ? $code : LocaleOptions::default();
    }

    private function tokenLooksValid(string $token): bool
    {
        $len = strlen($token);

        return $len >= 16 && $len <= 200 && preg_match('/^[A-Za-z0-9]+$/', $token) === 1;
    }
}
