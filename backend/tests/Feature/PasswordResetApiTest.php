<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Avoid hitting the HIBP API from `Password::uncompromised()` during tests.
        $this->app->bind(UncompromisedVerifier::class, fn () => new class implements UncompromisedVerifier {
            public function verify($data): bool
            {
                return true;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    private function strongPassword(): string
    {
        // Includes lower, upper, digit, symbol; >= 12 chars; deliberately
        // unique-ish so HIBP doesn't flag it during tests.
        return 'Zq7@pT3vN!8wL2bR';
    }

    public function test_request_returns_ok_for_unknown_email_without_creating_token(): void
    {
        Mail::fake();

        $response = $this->statefulJson('POST', '/api/password/forgot', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, PasswordResetToken::query()->count());
        Mail::assertNothingSent();
    }

    public function test_request_creates_token_and_sends_mail_for_existing_user(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->statefulJson('POST', '/api/password/forgot', [
            'email' => 'jane@example.com',
        ])->assertOk();

        $this->assertSame(1, PasswordResetToken::query()->where('user_id', $user->id)->count());
        Mail::assertSent(
            PasswordResetMail::class,
            fn (PasswordResetMail $mail) => $mail->hasTo($user->email)
        );
    }

    public function test_request_is_case_insensitive_on_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'mixed@example.com']);

        $this->statefulJson('POST', '/api/password/forgot', [
            'email' => 'Mixed@Example.com',
        ])->assertOk();

        $this->assertSame(1, PasswordResetToken::query()->where('user_id', $user->id)->count());
    }

    public function test_second_request_invalidates_first_token(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'first@example.com']);

        $this->statefulJson('POST', '/api/password/forgot', ['email' => $user->email])->assertOk();
        $first = PasswordResetToken::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($first->consumed_at);

        $this->statefulJson('POST', '/api/password/forgot', ['email' => $user->email])->assertOk();

        $first->refresh();
        $this->assertNotNull($first->consumed_at);

        $usable = PasswordResetToken::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->count();
        $this->assertSame(1, $usable);
    }

    public function test_reset_with_valid_token_succeeds_and_deletes_sessions(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'OldPassword123!',
            'remember_token' => 'old-remember',
        ]);

        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->addMinutes(60),
        ]);

        DB::table('sessions')->insert([
            'id' => 'sess-keep-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'sess-other',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'y',
            'last_activity' => time(),
        ]);

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertOk()->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertNotSame('old-remember', $user->remember_token);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->whereNull('user_id')->count());

        $tokenRow = PasswordResetToken::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($tokenRow->consumed_at);

        Mail::assertSent(
            PasswordChangedMail::class,
            fn (PasswordChangedMail $mail) => $mail->hasTo($user->email)
        );
    }

    public function test_reset_rejects_consumed_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->addMinutes(60),
            'consumed_at' => now(),
        ]);

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_reset_rejects_expired_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->subMinute(),
        ]);

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_reset_rejects_unknown_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => Str::random(64),
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_reset_rejects_email_mismatch(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);
        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->addMinutes(60),
        ]);

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => 'someone-else@example.com',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $user->refresh();
        $this->assertFalse(Hash::check($newPassword, $user->password));
    }

    public function test_reset_rejects_weak_password(): void
    {
        $user = User::factory()->create();
        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => $user->email,
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_reset_rejects_password_confirmation_mismatch(): void
    {
        $user = User::factory()->create();
        $plain = Str::random(64);
        PasswordResetToken::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => PasswordResetToken::hashPlainToken($plain),
            'expires_at' => now()->addMinutes(60),
        ]);

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => $plain,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword.'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_reset_rejects_malformed_token(): void
    {
        $user = User::factory()->create();

        $newPassword = $this->strongPassword();

        $this->statefulJson('POST', '/api/password/reset', [
            'token' => 'not-the-right-shape!!',
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_request_is_throttled_after_many_attempts(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->statefulJson('POST', '/api/password/forgot', [
                'email' => 'spam@example.com',
            ])->assertOk();
        }

        $this->statefulJson('POST', '/api/password/forgot', [
            'email' => 'spam@example.com',
        ])->assertStatus(429);
    }
}
