<?php

namespace App\Providers;

use App\Models\Place;
use App\Models\Chat;
use App\Models\Calendar;
use App\Models\Group;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;
use App\Policies\CalendarPolicy;
use App\Policies\ChatPolicy;
use App\Policies\GroupPolicy;
use App\Policies\PlacePolicy;
use App\Policies\PostPolicy;
use App\Policies\TaskPolicy;
use App\Support\CapabilityResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('place', function (string $value) {
            if (ctype_digit($value)) {
                return Place::query()->where('id', (int) $value)->firstOrFail();
            }

            return Place::query()->where('slug', $value)->firstOrFail();
        });

        Gate::policy(Place::class, PlacePolicy::class);
        Gate::policy(Chat::class, ChatPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Calendar::class, CalendarPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);

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

        RateLimiter::for('join-invitation-register', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(8)->by($request->ip().'|'.$token);
        });

        RateLimiter::for('visitor-login-request', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(6)->by(strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('visitor-login-consume', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(12)->by($request->ip().'|'.$token);
        });

        RateLimiter::for('table-access-resolve', function (Request $request) {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(120)->by($request->ip().'|'.$token);
        });
    }
}
