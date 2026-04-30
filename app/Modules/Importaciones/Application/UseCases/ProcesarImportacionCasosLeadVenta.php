<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\CodigoLeadYaRegistrado;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa importación CSV de casos lead_venta.
 * Columnas esperadas: cartera_codigo, tipo_identificacion_codigo, identificacion,
 * codigo_lead, producto_codigo, etapa_codigo, valor_estimado_monto, moneda, origen_lead,
 * fecha_primer_contacto, fecha_estimada_cierre, estado_caso_codigo, prioridad, fecha_ingreso.
 */
final readonly class ProcesarImportacionCasosLeadVenta
{
    public function __construct(private RegistrarCasoLeadVenta $registrar) {}

    public function ejecutar(int $importacionId, bool $commit): void
    {
        /** @var ImportacionModel $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);
        $proyectoId = (int) $importacion->proyecto_id;

        $tiposIdentificacion = DB::table('tipos_identificacion')->pluck('id', 'codigo')->all();
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $estadosCaso = DB::table('estados_caso')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $productos = DB::table('productos_venta')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $etapas = DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();

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
                    $errores[] = 'persona no encontrada en el proyecto.';
                    $personaId = null;
                }
            } else {
                $errores[] = 'tipo_identificacion_codigo inválido.';
            }

            foreach (['codigo_lead', 'valor_estimado_monto', 'moneda', 'fecha_primer_contacto', 'fecha_ingreso'] as $campo) {
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
                $fechaEstCierre = trim((string) ($payload['fecha_estimada_cierre'] ?? '')) !== ''
                    ? new DateTimeImmutable((string) $payload['fecha_estimada_cierre'])
                    : null;
                $out = $this->registrar->execute(new RegistrarCasoLeadVentaInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $carteraId,
                    personaId: (int) $personaId,
                    estadoCasoId: (int) $estadoCasoId,
                    fechaIngreso: new DateTimeImmutable((string) $payload['fecha_ingreso']),
                    prioridad: (int) ($payload['prioridad'] ?? 3),
                    codigoLead: (string) $payload['codigo_lead'],
                    productoVentaId: $productos[strtoupper((string) ($payload['producto_codigo'] ?? ''))] ?? null,
                    etapaEmbudoId: $etapas[strtoupper((string) ($payload['etapa_codigo'] ?? ''))] ?? null,
                    valorEstimadoMonto: (string) $payload['valor_estimado_monto'],
                    moneda: strtoupper((string) $payload['moneda']),
                    origenLead: $this->opcional($payload, 'origen_lead'),
                    fechaPrimerContacto: new DateTimeImmutable((string) $payload['fecha_primer_contacto']),
                    fechaEstimadaCierre: $fechaEstCierre,
                ));
                $fila->estado = 'importada';
                $fila->entidad_id = $out->casoId;
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;
                $importadasCount++;
            } catch (CodigoLeadYaRegistrado $e) {
                $fila->estado = 'omitida';
                $fila->mensaje_error = 'Código de lead ya registrado.';
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

    /** @param array<string, mixed> $p */
    private function opcional(array $p, string $k): ?string
    {
        $v = trim((string) ($p[$k] ?? ''));

        return $v === '' ? null : $v;
    }
}
