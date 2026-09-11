<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('nav.portfolios') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }} · Agrupaciones de cuentas de la operación.</div>
            </div>
        </div>
        <p class="text-sm text-ink-600 mb-4">Reparte las cuentas entre asesores desde Asignación. Al retirar una cartera, sus cuentas dejan la operación y sus registros permanecen disponibles en Histórico para usuarios autorizados.</p>
        <livewire:tenancy.configurador-pasos.paso-carteras :proyecto="$proyecto" />
    </div>
</x-app-layout>
