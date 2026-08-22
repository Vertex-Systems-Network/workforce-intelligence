<!doctype html>
<html lang="{{ $publicWebsiteMeta['language'] ?? 'en' }}" dir="{{ $publicWebsiteMeta['direction'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c0c11">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ $publicWebsiteMeta['title'] ?? 'Workforce Intelligence' }}</title>
    @if(!empty($publicWebsiteMeta['description']))<meta name="description" content="{{ $publicWebsiteMeta['description'] }}">@endif
    @if(!empty($publicWebsiteMeta['canonical']))<link rel="canonical" href="{{ $publicWebsiteMeta['canonical'] }}">@endif
    @if(!empty($publicWebsiteMeta['title']))<meta property="og:title" content="{{ $publicWebsiteMeta['title'] }}">@endif
    @if(!empty($publicWebsiteMeta['description']))<meta property="og:description" content="{{ $publicWebsiteMeta['description'] }}">@endif
    @if(!empty($publicWebsiteMeta['og_image']))<meta property="og:image" content="{{ $publicWebsiteMeta['og_image'] }}">@endif
    @if(!empty($publicWebsiteMeta))<meta property="og:type" content="website">@endif

    <style>
        #workintel-boot-status{min-height:100vh;display:grid;place-items:center;padding:24px;background:#0c0c11;color:#a1a1b5;font:14px/1.6 Inter,system-ui,sans-serif;text-align:center}
        .workintel-boot-card{display:flex;flex-direction:column;align-items:center;gap:11px}
        .workintel-boot-mark{width:44px;height:44px;display:grid;place-items:center;border:1px solid #252533;border-radius:12px;background:#131318;box-shadow:0 16px 48px rgba(0,0,0,.32)}
        .workintel-boot-spinner{width:18px;height:18px;border:2px solid #353549;border-top-color:#6366f1;border-radius:50%;animation:workintel-spin .8s linear infinite}
        .workintel-boot-bar{width:180px;height:3px;overflow:hidden;border-radius:999px;background:#1e1e2a}
        .workintel-boot-bar span{display:block;width:42%;height:100%;border-radius:inherit;background:#6366f1;animation:workintel-bar 1.1s ease-in-out infinite}
        @keyframes workintel-spin{to{transform:rotate(360deg)}}
        @keyframes workintel-bar{0%{transform:translateX(-110%)}100%{transform:translateX(340%)}}
        @media(prefers-reduced-motion:reduce){.workintel-boot-spinner,.workintel-boot-bar span{animation:none}}
    </style>
    @if(!empty($publicWebsiteHost))
        <script>window.__WORKINTEL_PUBLIC_WEBSITE_HOST__ = @json($publicWebsiteHost);</script>
    @endif
    <script src="{{ asset('workintel-boot.js') }}" defer></script>
    @unless(app()->runningUnitTests())
        @vite('resources/js/app.tsx')
    @endunless
</head>
<body>
    <div id="root">
        <div id="workintel-boot-status">
            <div class="workintel-boot-card">
                <div class="workintel-boot-mark"><span class="workintel-boot-spinner"></span></div>
                <div id="workintel-boot-label">Loading Workforce Intelligence…</div>
                <div class="workintel-boot-bar"><span></span></div>
            </div>
        </div>
    </div>
</body>
</html>
