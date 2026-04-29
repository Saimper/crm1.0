<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Campos personalizados"
            subtitle="Definir esquema de campos por proyecto × ámbito"
            :back="route('admin.dashboard')"
            back-label="← Volver al panel" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <livewire:campos-personalizados.admin />
        </div>
    </div>
</x-app-layout>
