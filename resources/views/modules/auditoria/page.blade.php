<x-app-layout>
    @php
        $proyecto = app()->bound('tenancy.proyecto_activo') ? app('tenancy.proyecto_activo') : null;
        // Sin proyecto, la pantalla ya no corre "sin tenant": corre dentro de un
        // cliente. Que se vea cuál, o el admin no sabe qué está mirando.
        $mandante = app()->bound('tenancy.mandante_activo') ? app('tenancy.mandante_activo') : null;
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $proyecto ? __('auditoria.title') : __('auditoria.title_global') }}</h1>
                <div class="page-subtitle">
                    @if($proyecto)
                        {{ $proyecto->nombre }}
                    @elseif($mandante)
                        {{ __('auditoria.subtitle_client', ['cliente' => $mandante->nombre]) }}
                    @else
                        {{ __('auditoria.subtitle_global') }}
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                @if($proyecto)
                    <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                       wire:navigate class="btn btn-ghost btn-sm">{{ __('auditoria.back_to_project') }}</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('auditoria.back_to_admin') }}</a>
                @endif
            </div>
        </div>

        <livewire:auditoria.listado-auditoria />
    </div>
</x-app-layout>
