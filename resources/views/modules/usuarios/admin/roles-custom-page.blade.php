<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('usuarios.page_roles_custom_title') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('usuarios.back_to_project') }}</a>
            </div>
        </div>

        <div x-data="{ tab: 'roles' }" class="space-y-5">
            <div class="flex gap-2 border-b border-ink-200 pb-3" aria-label="Roles y permisos">
                <button type="button" class="btn btn-sm" :class="tab === 'roles' ? 'btn-primary' : 'btn-ghost'" :aria-pressed="tab === 'roles'" @click="tab = 'roles'">Editar roles</button>
                <button type="button" class="btn btn-sm" :class="tab === 'comparar' ? 'btn-primary' : 'btn-ghost'" :aria-pressed="tab === 'comparar'" @click="tab = 'comparar'">Comparar permisos</button>
            </div>
            <div x-show="tab === 'roles'" class="space-y-6">
                <livewire:usuarios.admin-roles-base />
                <livewire:usuarios.admin-roles-custom />
            </div>
            <div x-show="tab === 'comparar'" x-cloak><livewire:usuarios.matriz-permisos /></div>
        </div>
    </div>
</x-app-layout>
