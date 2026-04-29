<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Repositories;

use App\Modules\Venta\Domain\Contracts\CompromisoCierreVentaRepository;
use App\Modules\Venta\Domain\Entities\CompromisoCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use App\Modules\Venta\Infrastructure\Persistence\Models\CompromisoCierreVentaModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentCompromisoCierreVentaRepository implements CompromisoCierreVentaRepository
{
    public function save(CompromisoCierreVenta $cierre): CompromisoCierreVenta
    {
        $model = CompromisoCierreVentaModel::query()->sinScopeProyecto()->find($cierre->compromisoId)
            ?? new CompromisoCierreVentaModel();

        $model->compromiso_id   = $cierre->compromisoId;
        $model->proyecto_id     = $cierre->proyectoId;
        $model->monto_cierre    = $cierre->monto->monto;
        $model->moneda          = $cierre->monto->moneda;
        $model->etapa_embudo_id = $cierre->etapaEmbudoId;

        $model->save();

        return $cierre;
    }

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoCierreVenta
    {
        $row = DB::table('compromisos_cierre_venta as cc')
            ->join('compromisos as c', 'c.id', '=', 'cc.compromiso_id')
            ->where('cc.compromiso_id', $compromisoId)
            ->select([
                'cc.compromiso_id', 'cc.proyecto_id', 'cc.monto_cierre', 'cc.moneda', 'cc.etapa_embudo_id',
                'c.fecha_vencimiento',
            ])
            ->first();
        if ($row === null) {
            return null;
        }

        return CompromisoCierreVenta::reconstituir(
            compromisoId:  (int) $row->compromiso_id,
            proyectoId:    (int) $row->proyecto_id,
            monto:         new MontoCierre((string) $row->monto_cierre, (string) $row->moneda),
            fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable((string) $row->fecha_vencimiento)),
            etapaEmbudoId: $row->etapa_embudo_id === null ? null : (int) $row->etapa_embudo_id,
        );
    }
}
