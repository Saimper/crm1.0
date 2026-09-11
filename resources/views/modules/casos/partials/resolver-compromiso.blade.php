{{-- Los botones de resolver un compromiso pendiente, según su tipo. Cada
     módulo trae su componente; `$clave` distingue dos instancias del mismo
     compromiso en la misma pantalla (resumen y pestaña de compromisos). --}}
@php
    /** @var object $compromiso */
    $clave = $clave ?? 'resolver';
@endphp

@if($compromiso->tipo_compromiso === 'promesa_pago')
    <livewire:cobranza.resolver-promesa :compromisoId="(int) $compromiso->id" :key="$clave.'-promesa-'.$compromiso->id" />
@elseif($compromiso->tipo_compromiso === 'resolucion_ticket')
    <livewire:cx.resolver-resolucion :compromisoId="(int) $compromiso->id" :key="$clave.'-resolucion-'.$compromiso->id" />
@elseif($compromiso->tipo_compromiso === 'cierre_venta')
    <livewire:venta.resolver-cierre :compromisoId="(int) $compromiso->id" :key="$clave.'-cierre-'.$compromiso->id" />
@elseif($compromiso->tipo_compromiso === 'accion_servicio')
    <livewire:servicio.resolver-accion :compromisoId="(int) $compromiso->id" :key="$clave.'-accion-'.$compromiso->id" />
@endif
