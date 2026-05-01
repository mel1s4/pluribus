<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <p>We received a request to reset your password.</p>
    <p>
        <a href="{{ $resetUrl }}">Reset your password</a>
    </p>
    <p>This link expires in {{ $expiresInMinutes }} minutes and can be used once.</p>
    @if (!empty($ip))
        <p style="color: #666; font-size: 12px;">Request originated from IP {{ $ip }}.</p>
    @endif
    <p style="color: #666; font-size: 12px;">
        If you did not request a password reset, you can safely ignore this email.
        Your password will remain unchanged.
    </p>
</body>
</html>
