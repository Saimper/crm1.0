<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

final class VistaDeTrabajo extends Component
{
    public string $clientePublicId = '';

    #[Url(as: 'producto')]
    public ?string $productoPublicIdSeleccionado = null;

    public function mount(string $cliente, ?string $producto = null): void
    {
        $this->clientePublicId = $cliente;
        $this->productoPublicIdSeleccionado = $producto;
    }

    public function seleccionarProducto(string $publicId): void
    {
        $this->productoPublicIdSeleccionado = $publicId;
    }

    #[On('gestion-registrada')]
    #[On('promesa-resuelta')]
    public function refrescar(): void
    {
        // Livewire re-renderiza automáticamente al recibir el evento.
    }

    public function render(): View
    {
        $cliente = DB::table('clientes')
            ->where('public_id', $this->clientePublicId)
            ->whereNull('eliminada_en')
            ->first();
        abort_unless($cliente, 404);

        $productos = DB::table('productos as p')
            ->leftJoin('estados_producto as ep', 'ep.id', '=', 'p.estado_producto_id')
            ->leftJoin('tramos_mora as tm', 'tm.id', '=', 'p.tramo_mora_id')
            ->leftJoin('carteras as ca', 'ca.id', '=', 'p.cartera_id')
            ->leftJoin('resultados as ru', 'ru.id', '=', 'p.resultado_ultima_gestion_id')
            ->where('p.cliente_id', $cliente->id)
            ->whereNull('p.eliminada_en')
            ->select([
                'p.id', 'p.public_id', 'p.numero_prestamo',
                'p.saldo_capital', 'p.saldo_total', 'p.cuota_mensual',
                'p.monto_original', 'p.dias_mora',
                'p.cuotas_totales', 'p.cuotas_pagadas',
                'p.moneda', 'p.fecha_desembolso', 'p.fecha_vencimiento',
                'p.fecha_ultima_gestion', 'p.tiene_promesa_vigente',
                'ep.nombre as estado_nombre', 'ep.codigo as estado_codigo',
                'tm.nombre as tramo_nombre',
                'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo_nombre',
            ])
            ->orderByDesc('p.dias_mora')
            ->orderBy('p.numero_prestamo')
            ->get();

        if (
            $productos->isNotEmpty()
            && ($this->productoPublicIdSeleccionado === null
                || ! $productos->contains('public_id', $this->productoPublicIdSeleccionado))
        ) {
            $this->productoPublicIdSeleccionado = (string) $productos->first()->public_id;
        }

        $productoActivo = $productos->firstWhere('public_id', $this->productoPublicIdSeleccionado);

        $historial      = collect();
        $promesaVigente = null;

        if ($productoActivo !== null) {
            $historial = DB::table('gestiones as g')
                ->leftJoin('resultados as r',      'r.id',  '=', 'g.resultado_id')
                ->leftJoin('tipos_gestion as tg',  'tg.id', '=', 'g.tipo_gestion_id')
                ->leftJoin('canales as cn',        'cn.id', '=', 'g.canal_id')
                ->leftJoin('causas_mora as cm',    'cm.id', '=', 'g.causa_mora_id')
                ->leftJoin('users as u',           'u.id',  '=', 'g.usuario_id')
                ->where('g.producto_id', $productoActivo->id)
                ->whereNull('g.eliminada_en')
                ->select([
                    'g.id', 'g.public_id', 'g.creada_en', 'g.notas', 'g.duracion_segundos',
                    'r.nombre as resultado_nombre', 'r.codigo as resultado_codigo', 'r.metadata as resultado_metadata',
                    'tg.nombre as tipo_gestion_nombre',
                    'cn.nombre as canal_nombre',
                    'cm.nombre as causa_mora_nombre',
                    'u.name as usuario_nombre',
                ])
                ->orderByDesc('g.creada_en')
                ->limit(30)
                ->get();

            $promesaVigente = DB::table('promesas')
                ->where('producto_id', $productoActivo->id)
                ->where('estado', 'pendiente')
                ->whereNull('eliminada_en')
                ->orderBy('fecha_promesa')
                ->first();
        }

        $contactos = DB::table('contactos')
            ->where('cliente_id', $cliente->id)
            ->where('activo', true)
            ->orderByDesc('es_principal')
            ->orderBy('tipo')
            ->get();

        $nombreCliente = $cliente->tipo_persona === 'juridica'
            ? (string) ($cliente->razon_social ?? '')
            : trim((string) ($cliente->nombres ?? '').' '.(string) ($cliente->apellidos ?? ''));

        return view('gestiones::livewire.vista-trabajo', [
            'cliente'         => $cliente,
            'nombreCliente'   => $nombreCliente,
            'productos'       => $productos,
            'productoActivo'  => $productoActivo,
            'historial'       => $historial,
            'promesaVigente'  => $promesaVigente,
            'contactos'       => $contactos,
        ]);
    }
}
