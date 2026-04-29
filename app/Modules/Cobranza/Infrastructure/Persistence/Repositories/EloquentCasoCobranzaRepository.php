<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Repositories;

use App\Modules\Cobranza\Domain\Contracts\CasoCobranzaRepository;
use App\Modules\Cobranza\Domain\Entities\CasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\NumeroPrestamo;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\CasoCobranzaModel;
use DateTimeImmutable;

final class EloquentCasoCobranzaRepository implements CasoCobranzaRepository
{
    public function save(CasoCobranza $caso): CasoCobranza
    {
        $model = CasoCobranzaModel::query()->sinScopeProyecto()->find($caso->casoId)
            ?? new CasoCobranzaModel();

        $model->caso_id          = $caso->casoId;
        $model->proyecto_id      = $caso->proyectoId;
        $model->numero_prestamo  = $caso->numeroPrestamo->valor;
        $model->moneda           = $caso->montoOriginal->moneda;
        $model->monto_original   = $caso->montoOriginal->monto;
        $model->saldo_capital    = $caso->saldoCapital->monto;
        $model->saldo_interes    = $caso->saldoInteres->monto;
        $model->saldo_total      = $caso->saldoTotal->monto;
        $model->cuota_mensual    = $caso->cuotaMensual->monto;
        $model->cuotas_totales   = $caso->cuotasTotales;
        $model->cuotas_pagadas   = $caso->cuotasPagadas;
        $model->dias_mora        = $caso->diasMora->dias;
        $model->tramo_mora_id    = $caso->tramoMoraId;
        $model->fecha_desembolso = $caso->fechaDesembolso;
        $model->fecha_vencimiento = $caso->fechaVencimiento;

        $model->save();

        return $caso;
    }

    public function buscarPorCasoId(int $casoId): ?CasoCobranza
    {
        /** @var CasoCobranzaModel|null $model */
        $model = CasoCobranzaModel::query()->sinScopeProyecto()->find($casoId);
        if ($model === null) {
            return null;
        }

        $moneda = (string) $model->moneda;

        return CasoCobranza::reconstituir(
            casoId:           (int) $model->caso_id,
            proyectoId:       (int) $model->proyecto_id,
            numeroPrestamo:   new NumeroPrestamo((string) $model->numero_prestamo),
            montoOriginal:    new MontoCobranza((string) $model->monto_original, $moneda),
            saldoCapital:     new MontoCobranza((string) $model->saldo_capital, $moneda),
            saldoInteres:     new MontoCobranza((string) $model->saldo_interes, $moneda),
            saldoTotal:       new MontoCobranza((string) $model->saldo_total, $moneda),
            cuotaMensual:     new MontoCobranza((string) $model->cuota_mensual, $moneda),
            cuotasTotales:    (int) $model->cuotas_totales,
            cuotasPagadas:    (int) $model->cuotas_pagadas,
            diasMora:         new DiasMora((int) $model->dias_mora),
            tramoMoraId:      $model->tramo_mora_id === null ? null : (int) $model->tramo_mora_id,
            fechaDesembolso:  $this->hidratarFecha($model->fecha_desembolso),
            fechaVencimiento: $this->hidratarFecha($model->fecha_vencimiento),
        );
    }

    public function existeNumeroPrestamoEnProyecto(int $proyectoId, string $numeroPrestamo): bool
    {
        return CasoCobranzaModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('numero_prestamo', $numeroPrestamo)
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
