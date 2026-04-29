<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa importación CSV de casos de cobranza.
 * Columnas esperadas: cartera_codigo, tipo_identificacion_codigo, identificacion, numero_prestamo,
 * moneda, monto_original, saldo_capital, saldo_interes, saldo_total, cuota_mensual,
 * cuotas_totales, cuotas_pagadas, dias_mora, fecha_desembolso, fecha_vencimiento,
 * estado_caso_codigo, prioridad, fecha_ingreso.
 */
final readonly class ProcesarImportacionCasosCobranza
{
    public function __construct(private RegistrarCasoCobranza $registrar)
    {
    }

    public function ejecutar(int $importacionId, bool $commit): void
    {
        /** @var ImportacionModel $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);
        $proyectoId = (int) $importacion->proyecto_id;

        $tiposIdentificacion = DB::table('tipos_identificacion')->pluck('id', 'codigo')->all();
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $estadosCaso = DB::table('estados_caso')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();

        $okCount = 0;
        $errorCount = 0;
        $importadasCount = 0;

        $filas = ImportacionFilaModel::query()
            ->sinScopeProyecto()
            ->where('importacion_id', $importacionId)
            ->orderBy('numero_fila')
            ->get();

        foreach ($filas as $fila) {
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $errores = [];

            $carteraId = $carteras[strtoupper((string) ($payload['cartera_codigo'] ?? ''))] ?? null;
            if ($carteraId === null) {
                $errores[] = 'cartera_codigo no existe en el proyecto.';
            }

            $estadoCasoId = $estadosCaso[strtoupper((string) ($payload['estado_caso_codigo'] ?? ''))] ?? null;
            if ($estadoCasoId === null) {
                $errores[] = 'estado_caso_codigo no existe en el proyecto.';
            }

            $tipoIdentId = $tiposIdentificacion[strtoupper((string) ($payload['tipo_identificacion_codigo'] ?? ''))] ?? null;
            $personaId = null;
            if ($tipoIdentId !== null) {
                $personaId = (int) DB::table('personas')
                    ->where('proyecto_id', $proyectoId)
                    ->where('tipo_identificacion_id', $tipoIdentId)
                    ->where('identificacion', (string) ($payload['identificacion'] ?? ''))
                    ->value('id');
                if (! $personaId) {
                    $errores[] = 'persona no encontrada en el proyecto (importar persona primero).';
                    $personaId = null;
                }
            } else {
                $errores[] = 'tipo_identificacion_codigo inválido.';
            }

            foreach (['numero_prestamo', 'moneda', 'monto_original', 'saldo_capital', 'saldo_total', 'fecha_desembolso', 'fecha_vencimiento', 'fecha_ingreso'] as $campo) {
                if (trim((string) ($payload[$campo] ?? '')) === '') {
                    $errores[] = "{$campo} es obligatorio.";
                }
            }

            if ($errores !== []) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = implode(' | ', $errores);
                $fila->save();
                $errorCount++;
                continue;
            }

            if (! $commit) {
                $fila->estado = 'valida';
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;
                continue;
            }

            try {
                $out = $this->registrar->execute(new RegistrarCasoCobranzaInput(
                    proyectoId:       $proyectoId,
                    carteraId:        (int) $carteraId,
                    personaId:        (int) $personaId,
                    estadoCasoId:     (int) $estadoCasoId,
                    fechaIngreso:     new DateTimeImmutable((string) $payload['fecha_ingreso']),
                    prioridad:        (int) ($payload['prioridad'] ?? 3),
                    numeroPrestamo:   (string) $payload['numero_prestamo'],
                    moneda:           strtoupper((string) $payload['moneda']),
                    montoOriginal:    (string) $payload['monto_original'],
                    saldoCapital:     (string) $payload['saldo_capital'],
                    saldoInteres:     (string) ($payload['saldo_interes'] ?? '0'),
                    saldoTotal:       (string) $payload['saldo_total'],
                    cuotaMensual:     (string) ($payload['cuota_mensual'] ?? '0'),
                    cuotasTotales:    (int) ($payload['cuotas_totales'] ?? 0),
                    cuotasPagadas:    (int) ($payload['cuotas_pagadas'] ?? 0),
                    diasMora:         (int) ($payload['dias_mora'] ?? 0),
                    fechaDesembolso:  new DateTimeImmutable((string) $payload['fecha_desembolso']),
                    fechaVencimiento: new DateTimeImmutable((string) $payload['fecha_vencimiento']),
                ));
                $fila->estado = 'importada';
                $fila->entidad_id = $out->casoId;
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;
                $importadasCount++;
            } catch (NumeroPrestamoYaRegistrado $e) {
                $fila->estado = 'omitida';
                $fila->mensaje_error = 'Número de préstamo ya registrado.';
                $fila->save();
                $errorCount++;
            } catch (Throwable $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = 'Error: '.mb_substr($e->getMessage(), 0, 400);
                $fila->save();
                $errorCount++;
            }
        }

        $importacion->total_filas = $filas->count();
        $importacion->filas_ok = $okCount;
        $importacion->filas_error = $errorCount;
        $importacion->filas_importadas = $importadasCount;
        $importacion->estado = $commit
            ? ($errorCount === $filas->count() ? 'fallida' : 'completada')
            : 'validada';
        $importacion->save();
    }
}
