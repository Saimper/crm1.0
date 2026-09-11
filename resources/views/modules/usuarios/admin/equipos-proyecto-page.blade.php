<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">Equipos de gestores</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }} · Grupos opcionales de asesores para reparto y supervisión.</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('usuarios.back_to_project') }}</a>
            </div>
        </div>

        <p class="text-sm text-ink-600 mb-4">Una cartera agrupa cuentas; un equipo agrupa a quienes las trabajan. Puedes asignar cuentas directamente a un asesor sin crear un equipo.</p>
        <livewire:usuarios.admin-equipos-proyecto />
    </div>
</x-app-layout>
