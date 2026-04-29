<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $titulo ?? 'En construcción' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-800">
                    <p>Esta vista aún no está implementada. Usa <kbd class="rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs">⌘K</kbd> para buscar un cliente o préstamo.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
