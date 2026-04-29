<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Usuarios globales"
            subtitle="Cuentas, ADMIN_GLOBAL y asignación de roles por proyecto"
            :back="route('admin.dashboard')"
            back-label="← Volver al panel" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <livewire:usuarios.admin-usuarios />
        </div>
    </div>
</x-app-layout>
