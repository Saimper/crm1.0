<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Entidades configurables"
            subtitle="Tablas de datos definibles por proyecto/cartera (§7.7)"
            :back="route('admin.dashboard')"
            back-label="← Volver al panel" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <livewire:entidades.admin-entidades-configurables />
        </div>
    </div>
</x-app-layout>
