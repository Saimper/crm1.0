<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Núcleo CRM') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="width:100%;max-width:420px;">
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:24px;">
            <a href="/" wire:navigate style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;">
                <x-ui.icon name="logo" :size="28" />
                <span style="font-weight:600;font-size:16px;letter-spacing:-0.01em;">Núcleo</span>
                <span style="font-size:11px;font-weight:500;color:var(--text-muted);border:1px solid var(--border);padding:1px 6px;border-radius:3px;">CRM</span>
            </a>
        </div>

        <div class="card card-pad" style="box-shadow:0 4px 16px rgba(16,24,40,0.06);">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
