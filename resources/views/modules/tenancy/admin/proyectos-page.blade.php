<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Proyectos"
            subtitle="Contextos operativos por mandante"
            :back="route('admin.dashboard')"
            back-label="← Volver al panel" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:tenancy.admin-proyectos />
        </div>
    </div>
</x-app-layout>
