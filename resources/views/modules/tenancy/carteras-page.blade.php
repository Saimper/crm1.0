<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('nav.portfolios') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
        </div>
        <livewire:tenancy.configurador-pasos.paso-carteras :proyecto="$proyecto" />
    </div>
</x-app-layout>
