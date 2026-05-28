@php $proyectoBadge = app()->bound('tenancy.proyecto_activo') ? app('tenancy.proyecto_activo') : null; @endphp
@if($proyectoBadge !== null)
    <a href="{{ route('proyectos.notificaciones', ['proyecto_id' => $proyectoBadge->id]) }}"
       wire:navigate
       class="icon-btn"
       title="{{ __('notificaciones.badge_title') }}">
        <x-ui.icon name="bell" :size="16" />
        @if($noLeidas > 0)
            <span class="pip">{{ $noLeidas > 99 ? '99+' : $noLeidas }}</span>
        @endif
    </a>
@endif
