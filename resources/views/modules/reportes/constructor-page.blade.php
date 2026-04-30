<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">Constructor de reportes</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.reportes.custom', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">← Volver al listado</a>
            </div>
        </div>

        <livewire:reportes.constructor-reporte :definicion-id="$definicionId ?? null" />
    </div>
</x-app-layout>
