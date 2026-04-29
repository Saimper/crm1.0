@props([
    'hover' => true,
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-surface-border bg-white shadow-card']) }}>
    <table class="min-w-full divide-y divide-surface-border text-sm">
        @isset($head)
            <thead class="bg-surface-50">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-surface-border {{ $hover ? '[&_tr:hover]:bg-surface-50' : '' }}">
            {{ $slot }}
        </tbody>
    </table>
    @isset($footer)
        <div class="px-4 py-3 border-t border-surface-border bg-surface-50">
            {{ $footer }}
        </div>
    @endisset
</div>
