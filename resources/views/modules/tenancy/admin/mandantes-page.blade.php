<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Mandantes"
            subtitle="Empresas externas que delegan procesos al BPO"
            :back="route('admin.dashboard')"
            back-label="← Volver al panel" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <livewire:tenancy.admin-mandantes />
        </div>
    </div>
</x-app-layout>
