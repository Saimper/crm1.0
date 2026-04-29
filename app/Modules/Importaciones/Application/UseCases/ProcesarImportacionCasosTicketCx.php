<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa importación CSV de casos ticket_cx.
 * Columnas esperadas: cartera_codigo, tipo_identificacion_codigo, identificacion,
 * codigo_ticket, asunto, descripcion, categoria_codigo, prioridad_codigo, sla_codigo,
 * escalamiento_codigo, fecha_reporte, fecha_limite_sla, estado_caso_codigo, prioridad,
 * fecha_ingreso.
 */
final readonly class ProcesarImportacionCasosTicketCx
{
    public function __construct(private RegistrarCasoTicketCx $registrar)
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
        $categorias = DB::table('categorias_ticket')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $prioridadesT = DB::table('prioridades_ticket')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $slas = DB::table('niveles_sla')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $escalas = DB::table('niveles_escalamiento')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();

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

            foreach (['codigo_ticket', 'asunto', 'fecha_reporte', 'fecha_ingreso'] as $campo) {
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
                $fechaSla = trim((string) ($payload['fecha_limite_sla'] ?? '')) !== ''
                    ? new DateTimeImmutable((string) $payload['fecha_limite_sla'])
                    : null;
                $out = $this->registrar->execute(new RegistrarCasoTicketCxInput(
                    proyectoId:          $proyectoId,
                    carteraId:           (int) $carteraId,
                    personaId:           (int) $personaId,
                    estadoCasoId:        (int) $estadoCasoId,
                    fechaIngreso:        new DateTimeImmutable((string) $payload['fecha_ingreso']),
                    prioridad:           (int) ($payload['prioridad'] ?? 3),
                    codigoTicket:        (string) $payload['codigo_ticket'],
                    asunto:              (string) $payload['asunto'],
                    descripcion:         $this->opcional($payload, 'descripcion'),
                    categoriaTicketId:   $categorias[strtoupper((string) ($payload['categoria_codigo'] ?? ''))] ?? null,
                    prioridadTicketId:   $prioridadesT[strtoupper((string) ($payload['prioridad_codigo'] ?? ''))] ?? null,
                    nivelSlaId:          $slas[strtoupper((string) ($payload['sla_codigo'] ?? ''))] ?? null,
                    nivelEscalamientoId: $escalas[strtoupper((string) ($payload['escalamiento_codigo'] ?? ''))] ?? null,
                    fechaReporte:        new DateTimeImmutable((string) $payload['fecha_reporte']),
                    fechaLimiteSla:      $fechaSla,
                ));
                $fila->estado = 'importada';
                $fila->entidad_id = $out->casoId;
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;
                $importadasCount++;
            } catch (CodigoTicketYaRegistrado $e) {
                $fila->estado = 'omitida';
                $fila->mensaje_error = 'Código de ticket ya registrado.';
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
