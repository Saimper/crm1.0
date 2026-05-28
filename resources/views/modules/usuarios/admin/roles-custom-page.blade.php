<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('usuarios.page_roles_custom_title') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.admin.matriz-permisos', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('usuarios.link_matriz_permisos') }}</a>
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('usuarios.back_to_project') }}</a>
            </div>
        </div>

        <livewire:usuarios.admin-roles-custom />
    </div>
</x-app-layout>
