<!doctype html>
<html lang="{{ e($htmlLang) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ e($pageTitle) }}</title>
    <meta name="description" content="{{ e($pageDescription) }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ e($pageTitle) }}" />
    <meta property="og:description" content="{{ e($pageDescription) }}" />
    <meta property="og:url" content="{{ e(url()->current()) }}" />
@if (!empty($ogImage))
    <meta property="og:image" content="{{ e($ogImage) }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ e($ogImage) }}" />
@else
    <meta name="twitter:card" content="summary" />
@endif
    <meta name="twitter:title" content="{{ e($pageTitle) }}" />
    <meta name="twitter:description" content="{{ e($pageDescription) }}" />
    <meta http-equiv="refresh" content="0;url={{ e($redirectUrl) }}" />
    <script>location.replace(@json($redirectUrl));</script>
</head>
<body>
    <p><a href="{{ e($redirectUrl) }}">{{ e($pageTitle) }}</a></p>
</body>
</html>
