<?php

namespace App\Support;

use Closure;

final class PostVideoUrl
{
    /**
     * @param  Closure(string): void  $fail
     */
    public static function validate(mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            $fail('The video url must be a string.');

            return;
        }
        if (strlen($value) > 2048) {
            $fail('The video url may not be greater than 2048 characters.');

            return;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The video url must be a valid URL.');

            return;
        }
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if ($host === '') {
            $fail('The video url must include a host.');

            return;
        }
        if ($host === 'youtu.be' || $host === 'drive.google.com') {
            return;
        }
        if (preg_match('/(^|\.)youtube\.com$/', $host) === 1) {
            return;
        }
        $fail('The video url must be a YouTube or Google Drive link.');
    }
}
