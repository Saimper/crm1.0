<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Repositories;

use App\Modules\Cobranza\Domain\Contracts\CompromisoPromesaPagoRepository;
use App\Modules\Cobranza\Domain\Entities\CompromisoPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\CompromisoPromesaPagoModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentCompromisoPromesaPagoRepository implements CompromisoPromesaPagoRepository
{
    public function save(CompromisoPromesaPago $promesa): CompromisoPromesaPago
    {
        $model = CompromisoPromesaPagoModel::query()->sinScopeProyecto()->find($promesa->compromisoId)
            ?? new CompromisoPromesaPagoModel();

        $model->compromiso_id = $promesa->compromisoId;
        $model->proyecto_id   = $promesa->proyectoId;
        $model->monto         = $promesa->monto->monto;
        $model->moneda        = $promesa->monto->moneda;
        $model->tipo_pago_id  = $promesa->tipoPagoId;

        $model->save();

        return $promesa;
    }

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoPromesaPago
    {
        $row = DB::table('compromisos_promesa_pago as cp')
            ->join('compromisos as c', 'c.id', '=', 'cp.compromiso_id')
            ->where('cp.compromiso_id', $compromisoId)
            ->select([
                'cp.compromiso_id', 'cp.proyecto_id', 'cp.monto', 'cp.moneda', 'cp.tipo_pago_id',
                'c.fecha_vencimiento',
            ])
            ->first();
        if ($row === null) {
            return null;
        }

        return CompromisoPromesaPago::reconstituir(
            compromisoId:     (int) $row->compromiso_id,
            proyectoId:       (int) $row->proyecto_id,
            monto:            new MontoPromesa((string) $row->monto, (string) $row->moneda),
            fechaVencimiento: new FechaPromesa(new DateTimeImmutable((string) $row->fecha_vencimiento)),
            tipoPagoId:       $row->tipo_pago_id === null ? null : (int) $row->tipo_pago_id,
        );
    }
}
