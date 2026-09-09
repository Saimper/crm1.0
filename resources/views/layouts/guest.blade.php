<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Núcleo CRM') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-[420px]">
        <div class="flex items-center justify-center gap-2 mb-6">
            <a href="/" wire:navigate class="inline-flex items-center gap-3">
                <span class="brand-mark"><x-ui.icon name="layers" :size="18" /></span>
                <span class="font-semibold text-xl tracking-tight">Núcleo <span class="text-ink-500 font-normal">CRM</span></span>
            </a>
        </div>

        <div class="card p-6 sm:p-8 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
