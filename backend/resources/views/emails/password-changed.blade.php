<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <p>Your password was just changed.</p>
    <p style="color: #666; font-size: 12px;">
        Time: {{ $changedAt }}<br>
        @if (!empty($ip))IP: {{ $ip }}<br>@endif
        @if (!empty($userAgent))Device: {{ $userAgent }}@endif
    </p>
    <p>
        If this was you, no further action is needed. All other sessions on your
        account have been signed out for safety.
    </p>
    <p style="color: #b91c1c;">
        If you did not change your password, please contact support immediately
        and reset your password again from a trusted device.
    </p>
</body>
</html>
