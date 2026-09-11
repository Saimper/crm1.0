<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title">{{ __('casos.title_work') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-sm text-ink-500 bg-surface-100 border border-ink-200 rounded-md px-2 py-1">{{ $proyecto->codigo }}</span>
                <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-secondary btn-sm">{{ __('casos.back_to_tray') }}</a>
            </div>
        </div>

        <livewire:casos.vista-de-trabajo :persona="$persona" :caso="$caso ?? null" />
    </div>
</x-app-layout>
