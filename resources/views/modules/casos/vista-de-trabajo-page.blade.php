<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp
    <div class="page agent-workspace">
        <div class="workspace-navigation">
            <a href="{{ route('proyectos.personas.lista', ['proyecto_id' => $proyecto->id]) }}" wire:navigate class="btn-link">
                <x-ui.icon name="chevron-left" :size="16" /> Clientes
            </a>
            <h1 class="sr-only">{{ __('casos.title_work') }}</h1>
            <a href="#gestion-panel" class="btn btn-secondary btn-sm workspace-capture-link">Registrar gestión</a>
            <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyecto->id]) }}" wire:navigate class="btn-link">Mi bandeja</a>
        </div>
        <livewire:casos.vista-de-trabajo :persona="$persona" :caso="$caso ?? null" />
    </div>
</x-app-layout>
