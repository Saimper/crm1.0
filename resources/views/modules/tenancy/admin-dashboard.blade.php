<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Administración global"
            subtitle="Gestión cross-project disponible solo para ADMIN_GLOBAL"
            :back="route('dashboard')"
            back-label="← Selector de proyectos" />
    </x-slot>

    @php
        $tiles = [
            [
                'route' => 'admin.campos-personalizados',
                'title' => 'Campos personalizados',
                'desc'  => 'Definir, editar y desactivar campos por proyecto.',
                'icon'  => 'clipboard',
            ],
            [
                'route' => 'admin.entidades-configurables',
                'title' => 'Entidades configurables',
                'desc'  => 'Definir tablas de datos (pólizas, vehículos, etc.) por proyecto/cartera.',
                'icon'  => 'briefcase',
            ],
            [
                'route' => 'admin.mandantes',
                'title' => 'Mandantes',
                'desc'  => 'Empresas externas que delegan procesos al BPO.',
                'icon'  => 'briefcase',
            ],
            [
                'route' => 'admin.proyectos',
                'title' => 'Proyectos',
                'desc'  => 'Contextos operativos por mandante (cobranza, CX, venta, servicio).',
                'icon'  => 'clipboard',
            ],
            [
                'route' => 'admin.usuarios',
                'title' => 'Usuarios globales',
                'desc'  => 'Cuentas, ADMIN_GLOBAL y asignación de roles por proyecto.',
                'icon'  => 'users',
            ],
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <section>
                <x-ui.section-title
                    title="Panel administrativo"
                    hint="Configuración del sistema global. Los cambios aquí aplican a todos los proyectos." />

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($tiles as $t)
                        @if(\Illuminate\Support\Facades\Route::has($t['route']))
                            <a href="{{ route($t['route']) }}" wire:navigate
                               class="group rounded-xl border border-surface-border bg-white p-5 shadow-card hover:shadow-card-hover hover:border-brand-300 transition-all">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center group-hover:bg-brand-100 transition-colors">
                                        <x-ui.icon :name="$t['icon']" class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink-900 group-hover:text-brand-700 transition-colors">{{ $t['title'] }}</div>
                                        <p class="mt-0.5 text-xs text-ink-500">{{ $t['desc'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
