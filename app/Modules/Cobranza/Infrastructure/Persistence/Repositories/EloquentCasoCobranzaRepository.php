<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Repositories;

use App\Modules\Cobranza\Domain\Contracts\CasoCobranzaRepository;
use App\Modules\Cobranza\Domain\Entities\CasoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\NumeroPrestamo;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\CasoCobranzaModel;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use DateTimeImmutable;

final class EloquentCasoCobranzaRepository implements CasoCobranzaRepository
{
    public function __construct(private readonly RelojDelMandante $reloj) {}

    public function save(CasoCobranza $caso): CasoCobranza
    {
        $regional = app(RegionalConfiguration::class)->forProject($caso->proyectoId);
        if ($caso->montoOriginal !== null) {
            $regional->validateCanonical($caso->montoOriginal->monto);
        }
        if ($caso->saldoCapital !== null) {
            $regional->validateCanonical($caso->saldoCapital->monto);
        }
        if ($caso->saldoInteres !== null) {
            $regional->validateCanonical($caso->saldoInteres->monto);
        }
        if ($caso->saldoTotal !== null) {
            $regional->validateCanonical($caso->saldoTotal->monto);
        }
        if ($caso->cuotaMensual !== null) {
            $regional->validateCanonical($caso->cuotaMensual->monto);
        }

        $model = CasoCobranzaModel::query()->sinScopeProyecto()->find($caso->casoId)
            ?? new CasoCobranzaModel;

        $model->caso_id = $caso->casoId;
        $model->proyecto_id = $caso->proyectoId;
        $model->numero_prestamo = $caso->numeroPrestamo->valor;
        $model->moneda = $caso->montoOriginal?->moneda
            ?? $caso->saldoTotal?->moneda
            ?? $caso->saldoCapital?->moneda
            ?? ($model->exists ? (string) $model->moneda : $regional->currency);
        $model->monto_original = $caso->montoOriginal?->monto;
        $model->saldo_capital = $caso->saldoCapital?->monto;
        $model->saldo_interes = $caso->saldoInteres?->monto;
        $model->saldo_total = $caso->saldoTotal?->monto;
        $model->cuota_mensual = $caso->cuotaMensual?->monto;
        $model->cuotas_totales = $caso->cuotasTotales;
        $model->cuotas_pagadas = $caso->cuotasPagadas;
        $model->dias_mora = $caso->diasMora?->dias;
        $model->tramo_mora_id = $caso->tramoMoraId;
        $model->fecha_desembolso = $caso->fechaDesembolso;
        $model->fecha_vencimiento = $caso->fechaVencimiento;

        // Quien afirma una mora la ancla al día en que la afirma. Este
        // repositorio es una FUENTE (alta a mano, edición por dominio), así que
        // mueve las dos fechas: el ancla que envejece `cobranza:avanzar-dias-mora`
        // y la confirmación que dice «esto lo dijo alguien, no el reloj». Sólo
        // cuando el valor cambia —en un alta todo valor es cambio—: volver a
        // guardar la misma mora no es afirmarla de nuevo, y no debe mover el
        // ancla hacia hoy sin sumar los días que van del ancla vieja a hoy.
        // En el calendario del mandante, no del servidor (§RelojDelMandante).
        if ($model->dias_mora !== null && $model->isDirty('dias_mora')) {
            $hoy = $this->reloj->hoy(proyectoId: $caso->proyectoId);
            $model->dias_mora_actualizado_en = $hoy;
            $model->dias_mora_confirmado_en = $hoy;
        }

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
        $monto = static fn (mixed $v): ?MontoCobranza => $v === null ? null : new MontoCobranza((string) $v, $moneda);

        return CasoCobranza::reconstituir(
            casoId: (int) $model->caso_id,
            proyectoId: (int) $model->proyecto_id,
            numeroPrestamo: new NumeroPrestamo((string) $model->numero_prestamo),
            montoOriginal: $monto($model->monto_original),
            saldoCapital: $monto($model->saldo_capital),
            saldoInteres: $monto($model->saldo_interes),
            saldoTotal: $monto($model->saldo_total),
            cuotaMensual: $monto($model->cuota_mensual),
            cuotasTotales: $model->cuotas_totales === null ? null : (int) $model->cuotas_totales,
            cuotasPagadas: $model->cuotas_pagadas === null ? null : (int) $model->cuotas_pagadas,
            diasMora: $model->dias_mora === null ? null : new DiasMora((int) $model->dias_mora),
            tramoMoraId: $model->tramo_mora_id === null ? null : (int) $model->tramo_mora_id,
            fechaDesembolso: $this->hidratarFecha($model->fecha_desembolso),
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

    private function hidratarFecha(mixed $valor): ?DateTimeImmutable
    {
        if ($valor === null) {
            return null;
        }
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
