<?php

namespace App\Providers;

use App\Models\Calendar;
use App\Models\Chat;
use App\Models\Community;
use App\Models\CommunityDomain;
use App\Models\CommunityProject;
use App\Models\Group;
use App\Models\Note;
use App\Models\Place;
use App\Models\Post;
use App\Models\Survey;
use App\Models\Task;
use App\Models\User;
use App\Policies\CalendarPolicy;
use App\Policies\CommunityProjectPolicy;
use App\Policies\ChatPolicy;
use App\Policies\GroupPolicy;
use App\Policies\PlacePolicy;
use App\Policies\NotePolicy;
use App\Policies\PostPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\TaskPolicy;
use App\Support\CapabilityResolver;
use App\Support\WalletLedger\LedgerAppender;
use App\Support\WalletLedger\LedgerSigner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LedgerSigner::class, function () {
            return new LedgerSigner;
        });

        $this->app->singleton(LedgerAppender::class, function ($app) {
            return new LedgerAppender(
                $app->make(LedgerSigner::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->mergeCommunityHostsIntoSanctumStatefulDomains();

        Route::bind('place', function (string $value) {
            if (ctype_digit($value)) {
                return Place::query()->where('id', (int) $value)->firstOrFail();
            }

            return Place::query()->where('slug', $value)->firstOrFail();
        });

        Route::bind('note', function (string $value) {
            $userId = auth()->id();
            abort_unless($userId, 404);

            return Note::query()
                ->visibleToUser((int) $userId)
                ->whereKey((int) $value)
                ->firstOrFail();
        });

        Route::bind('project', function (string $value, \Illuminate\Routing\Route $route): CommunityProject {
            $slug = $route->parameter('slug');
            if (! is_string($slug) || $slug === '') {
                abort(404);
            }
            $community = Community::query()->where('slug', $slug)->first();
            if ($community === null) {
                abort(404);
            }

            $project = CommunityProject::query()
                ->where('community_id', $community->id)
                ->whereKey((int) $value)
                ->first();
            if ($project === null) {
                abort(404);
            }

            return $project;
        });

        Gate::policy(Place::class, PlacePolicy::class);
        Gate::policy(Chat::class, ChatPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Calendar::class, CalendarPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Survey::class, SurveyPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(CommunityProject::class, CommunityProjectPolicy::class);

        Gate::before(function ($user, string $_ability) {
            if ($user instanceof User && $user->isRoot()) {
                return true;
            }

            return null;
        });

        $resolver = $this->app->make(CapabilityResolver::class);
        foreach ($resolver->allCatalogCapabilityIds() as $capabilityId) {
            Gate::define($capabilityId, function (User $user) use ($capabilityId, $resolver): bool {
                return $resolver->userHasCapability($user, $capabilityId);
            });
        }

        RateLimiter::for('login', function (Request $request) {
            $key = (string) $request->input('email', '').'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('join-invitation-show', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('join-invitation-share', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('discovery-map', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('join-invitation-verify-email', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(6)->by($request->ip().'|'.$token);
        });

        RateLimiter::for('join-invitation-verify-show', function (Request $request) {
            $token = (string) $request->route('token', '');
            $verify = (string) $request->route('verifyToken', '');

            return Limit::perMinute(60)->by($request->ip().'|'.$token.'|'.$verify);
        });

        RateLimiter::for('join-invitation-register-verified', function (Request $request) {
            $token = (string) $request->route('token', '');
            $verify = (string) $request->route('verifyToken', '');

            return Limit::perMinute(8)->by($request->ip().'|'.$token.'|'.$verify);
        });

        RateLimiter::for('visitor-login-request', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(6)->by(strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('password-forgot', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $token = (string) $request->input('token', '');

            return Limit::perMinute(10)->by($request->ip().'|'.substr($token, 0, 8));
        });

        RateLimiter::for('visitor-login-consume', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(12)->by($request->ip().'|'.$token);
        });

        RateLimiter::for('table-access-resolve', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(120)->by($request->ip().'|'.$token);
        });

        RateLimiter::for('table-session-ping', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(60)->by($user ? (string) $user->id : $request->ip());
        });

        RateLimiter::for('personify', function (Request $request) {
            $user = $request->user();

            return Limit::perHour(30)->by($user ? (string) $user->id : $request->ip());
        });
    }

    private function mergeCommunityHostsIntoSanctumStatefulDomains(): void
    {
        try {
            if (! Schema::hasTable('community_domains')) {
                return;
            }
            $hosts = CommunityDomain::query()->pluck('host')->filter()->all();
            if ($hosts === []) {
                return;
            }
            $existing = config('sanctum.stateful', []);
            if (! is_array($existing)) {
                $existing = [];
            }
            config([
                'sanctum.stateful' => array_values(array_unique(array_merge($existing, $hosts))),
            ]);

            $origins = config('cors.allowed_origins', []);
            if (! is_array($origins)) {
                $origins = [];
            }
            foreach ($hosts as $host) {
                $origins[] = 'https://'.$host;
                $origins[] = 'http://'.$host;
            }
            config([
                'cors.allowed_origins' => array_values(array_unique(array_filter($origins))),
            ]);
        } catch (\Throwable) {
            // Migrations may not have run yet (e.g. package discovery).
        }
    }
}
