<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Repositories;

use App\Modules\Venta\Domain\Contracts\CasoLeadVentaRepository;
use App\Modules\Venta\Domain\Entities\CasoLeadVenta;
use App\Modules\Venta\Domain\ValueObjects\CodigoLead;
use App\Modules\Venta\Domain\ValueObjects\ValorEstimadoVenta;
use App\Modules\Venta\Infrastructure\Persistence\Models\CasoLeadVentaModel;
use DateTimeImmutable;

final class EloquentCasoLeadVentaRepository implements CasoLeadVentaRepository
{
    public function save(CasoLeadVenta $lead): CasoLeadVenta
    {
        $model = CasoLeadVentaModel::query()->sinScopeProyecto()->find($lead->casoId)
            ?? new CasoLeadVentaModel();

        $model->caso_id               = $lead->casoId;
        $model->proyecto_id           = $lead->proyectoId;
        $model->codigo_lead           = $lead->codigoLead->valor;
        $model->producto_venta_id     = $lead->productoVentaId;
        $model->etapa_embudo_id       = $lead->etapaEmbudoId;
        $model->valor_estimado        = $lead->valorEstimado->monto;
        $model->moneda                = $lead->valorEstimado->moneda;
        $model->origen_lead           = $lead->origenLead;
        $model->fecha_primer_contacto = $lead->fechaPrimerContacto;
        $model->fecha_estimada_cierre = $lead->fechaEstimadaCierre;

        $model->save();

        return $lead;
    }

    public function buscarPorCasoId(int $casoId): ?CasoLeadVenta
    {
        /** @var CasoLeadVentaModel|null $model */
        $model = CasoLeadVentaModel::query()->sinScopeProyecto()->find($casoId);
        if ($model === null) {
            return null;
        }

        return CasoLeadVenta::reconstituir(
            casoId:              (int) $model->caso_id,
            proyectoId:          (int) $model->proyecto_id,
            codigoLead:          new CodigoLead((string) $model->codigo_lead),
            productoVentaId:     $model->producto_venta_id === null ? null : (int) $model->producto_venta_id,
            etapaEmbudoId:       $model->etapa_embudo_id === null ? null : (int) $model->etapa_embudo_id,
            valorEstimado:       new ValorEstimadoVenta((string) $model->valor_estimado, (string) $model->moneda),
            origenLead:          $model->origen_lead,
            fechaPrimerContacto: $this->hidratarFecha($model->fecha_primer_contacto),
            fechaEstimadaCierre: $model->fecha_estimada_cierre === null ? null : $this->hidratarFecha($model->fecha_estimada_cierre),
        );
    }

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoLead): bool
    {
        return CasoLeadVentaModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('codigo_lead', $codigoLead)
            ->exists();
    }

    private function hidratarFecha(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
