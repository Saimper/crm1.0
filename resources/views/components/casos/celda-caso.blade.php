@props(['caso', 'columna', 'nombre'])

@php
    $alias = $columna->alias();
    $valor = $caso->{$alias} ?? null;

    $tipoTone = match ($caso->tipo_caso) {
        'cobranza'   => 'warning',
        'ticket_cx'  => 'info',
        'lead_venta' => 'success',
        'servicio'   => 'primary',
        default      => 'neutral',
    };

    // Columnas monetarias: el catálogo las marca como numéricas y viven en el CTI.
    $esMonto = in_array($columna->clave, [
        'saldo_total', 'saldo_capital', 'saldo_interes', 'cuota_mensual',
        'monto_original', 'valor_estimado',
    ], true);

    $esFecha = str_starts_with($columna->clave, 'fecha_')
        || in_array($columna->clave, ['ultima_gestion'], true);
@endphp

@switch($columna->clave)
    @case('tipo')
        <td>
            <x-ui.badge :tone="$tipoTone" size="sm">
                {{ ucfirst(str_replace('_', ' ', $caso->tipo_caso)) }}
            </x-ui.badge>
        </td>
        @break

    @case('persona')
        <td><span style="font-weight:500;">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
        @break

    @case('identificacion')
        <td><span class="font-mono" style="font-size:12px;">{{ $valor ?? '—' }}</span></td>
        @break

    @case('compromiso')
        <td>
            @if($valor)
                <x-ui.badge tone="success" size="sm">{{ __('casos.commitment_active') }}</x-ui.badge>
            @else
                <span style="font-size:11px;color:var(--text-tertiary);">—</span>
            @endif
        </td>
        @break

    @case('cuenta')
    @case('codigo_ticket')
    @case('codigo_lead')
    @case('codigo_servicio')
        <td><span class="font-mono" style="font-size:12px;">{{ $valor ?? '—' }}</span></td>
        @break

    @default
        <td @class(['num' => $columna->numerica])
            style="font-size:12px;color:var(--text-secondary);">
            @if($valor === null || $valor === '')
                —
            @elseif($esMonto)
                {{ number_format((float) $valor, 2) }}
            @elseif($esFecha)
                {{ \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') }}
            @else
                {{ $valor }}
            @endif
        </td>
@endswitch
