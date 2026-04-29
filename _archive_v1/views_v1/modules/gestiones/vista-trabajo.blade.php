<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Vista de Trabajo
        </h2>
    </x-slot>

    <div class="py-8">
        <livewire:gestiones.vista-de-trabajo :cliente="$clientePublicId" :producto="$productoPublicId" />
    </div>
</x-app-layout>
