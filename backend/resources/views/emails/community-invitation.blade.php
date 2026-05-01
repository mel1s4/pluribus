<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111827;">
    <p>You have been invited to join <strong>{{ $communityName }}</strong>.</p>
    <p>Open the link below. On that page, enter your email address; we will send you a second link to confirm your email and finish creating your account.</p>
    <p><a href="{{ $joinUrl }}" style="color: #2563eb;">Open your invitation</a></p>
    <p style="font-size: 0.875rem; color: #6b7280;">If the link does not work, paste this URL into your browser:<br>{{ $joinUrl }}</p>
</body>
</html>
