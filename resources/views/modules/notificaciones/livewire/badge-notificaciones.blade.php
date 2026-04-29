@php $proyectoBadge = app()->bound('tenancy.proyecto_activo') ? app('tenancy.proyecto_activo') : null; @endphp
@if($proyectoBadge !== null)
    <a href="{{ route('proyectos.notificaciones', ['proyecto_id' => $proyectoBadge->id]) }}"
       wire:navigate
       class="relative inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-700"
       title="Notificaciones">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if($noLeidas > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1.5 h-4 min-w-[1rem] text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                {{ $noLeidas > 99 ? '99+' : $noLeidas }}
            </span>
        @endif
    </a>
@endif
