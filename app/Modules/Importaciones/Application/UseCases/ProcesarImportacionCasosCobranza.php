<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use App\Modules\Importaciones\Application\Services\ResolverPersonaImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResumenChunk;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa CSV de casos de cobranza con modo y chunking.
 * Match existente por (proyecto_id, numero_prestamo) en casos_cobranza.
 * UPDATE inline vía DB::table sobre columnas operativas (saldos, cuotas, fechas).
 */
final readonly class ProcesarImportacionCasosCobranza
{
    /** @var list<string> Columnas mutables vía merge/overwrite en casos_cobranza. */
    private const COLUMNAS_MUTABLES = [
        'monto_original', 'saldo_capital', 'saldo_interes', 'saldo_total',
        'cuota_mensual', 'cuotas_totales', 'cuotas_pagadas', 'dias_mora',
        'fecha_desembolso', 'fecha_vencimiento',
    ];

    /** @var list<string> Mapeo CSV→columna (mismo nombre para todos). */
    private const CAMPOS_CSV = self::COLUMNAS_MUTABLES;

    public function __construct(
        private RegistrarCasoCobranza $registrar,
        private ResolverPersonaImportacion $personaResolver,
    ) {}

    public function ejecutar(
        int $importacionId,
        bool $commit,
        ModoImportacion $modo = ModoImportacion::MERGE,
        ?int $offset = null,
        ?int $limit = null,
    ): ResumenChunk {
        /** @var ImportacionModel $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);
        $proyectoId = (int) $importacion->proyecto_id;

        $tiposIdentificacion = DB::table('tipos_identificacion')->pluck('id', 'codigo')->all();
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $estadosCaso = DB::table('estados_caso')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();

        $query = ImportacionFilaModel::query()
            ->sinScopeProyecto()
            ->where('importacion_id', $importacionId)
            ->orderBy('numero_fila');

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $filas = $query->get();
        if ($filas->isEmpty()) {
            return ResumenChunk::vacio();
        }

        $procesadas = 0;
        $validas = 0;
        $invalidas = 0;
        $duplicadas = 0;
        $ultimaFilaId = null;

        foreach ($filas as $fila) {
            $ultimaFilaId = (int) $fila->id;
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
            if ($tipoIdentId === null) {
                $errores[] = 'tipo_identificacion_codigo inválido.';
            } else {
                $personaId = $this->personaResolver->lookup(
                    $proyectoId,
                    (int) $tipoIdentId,
                    (string) ($payload['identificacion'] ?? ''),
                );
                if ($personaId === null) {
                    $errores = array_merge($errores, $this->personaResolver->validarParaCrear($payload));
                }
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
                $invalidas++;

                continue;
            }

            if (! $commit) {
                $fila->estado = 'pendiente';
                $fila->mensaje_error = null;
                $fila->save();
                $validas++;

                continue;
            }

            $existenteCasoId = $this->buscarCasoExistente($proyectoId, (string) $payload['numero_prestamo']);

            try {
                if ($personaId === null) {
                    $personaId = $this->personaResolver->resolverOCrear($proyectoId, (int) $tipoIdentId, $payload);
                }
                if ($existenteCasoId !== null) {
                    $resultado = $this->resolverExistente($existenteCasoId, $payload, $modo);
                    $fila->estado = $resultado['estado'];
                    $fila->entidad_id = $existenteCasoId;
                    $fila->razon_omision = $resultado['razon'];
                    $fila->mensaje_error = null;
                    $fila->save();

                    match ($resultado['estado']) {
                        'duplicada' => $duplicadas++,
                        'procesada' => $procesadas++,
                        default => null,
                    };

                    continue;
                }

                $out = $this->registrar->execute(new RegistrarCasoCobranzaInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $carteraId,
                    personaId: (int) $personaId,
                    estadoCasoId: (int) $estadoCasoId,
                    fechaIngreso: new DateTimeImmutable((string) $payload['fecha_ingreso']),
                    prioridad: (int) ($payload['prioridad'] ?? 3),
                    numeroPrestamo: (string) $payload['numero_prestamo'],
                    moneda: strtoupper((string) $payload['moneda']),
                    montoOriginal: (string) $payload['monto_original'],
                    saldoCapital: (string) $payload['saldo_capital'],
                    saldoInteres: (string) ($payload['saldo_interes'] ?? '0'),
                    saldoTotal: (string) $payload['saldo_total'],
                    cuotaMensual: (string) ($payload['cuota_mensual'] ?? '0'),
                    cuotasTotales: (int) ($payload['cuotas_totales'] ?? 0),
                    cuotasPagadas: (int) ($payload['cuotas_pagadas'] ?? 0),
                    diasMora: (int) ($payload['dias_mora'] ?? 0),
                    fechaDesembolso: new DateTimeImmutable((string) $payload['fecha_desembolso']),
                    fechaVencimiento: new DateTimeImmutable((string) $payload['fecha_vencimiento']),
                ));
                $fila->estado = 'procesada';
                $fila->entidad_id = $out->casoId;
                $fila->mensaje_error = null;
                $fila->save();
                $procesadas++;
            } catch (NumeroPrestamoYaRegistrado) {
                $fila->estado = 'duplicada';
                $fila->razon_omision = 'número de préstamo ya registrado (carrera con import paralelo)';
                $fila->save();
                $duplicadas++;
            } catch (Throwable $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = 'Error: '.mb_substr($e->getMessage(), 0, 400);
                $fila->save();
                $invalidas++;
            }
        }

        return new ResumenChunk(
            procesadas: $procesadas,
            validas: $validas,
            invalidas: $invalidas,
            omitidas: 0,
            duplicadas: $duplicadas,
            filasEnChunk: $filas->count(),
            ultimaFilaId: $ultimaFilaId,
        );
    }

    private function buscarCasoExistente(int $proyectoId, string $numeroPrestamo): ?int
    {
        $id = DB::table('casos_cobranza')
            ->where('proyecto_id', $proyectoId)
            ->where('numero_prestamo', $numeroPrestamo)
            ->value('caso_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{estado: string, razon: ?string}
     */
    private function resolverExistente(int $casoId, array $payload, ModoImportacion $modo): array
    {
        if ($modo === ModoImportacion::SKIP_DUPLICADOS) {
            return ['estado' => 'duplicada', 'razon' => 'ya existe en proyecto'];
        }

        $existente = DB::table('casos_cobranza')->where('caso_id', $casoId)->first();
        if ($existente === null) {
            return ['estado' => 'duplicada', 'razon' => 'caso desaparecido'];
        }

        $update = [];
        foreach (self::CAMPOS_CSV as $campo) {
            $valor = trim((string) ($payload[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }
            if ($modo === ModoImportacion::MERGE) {
                $valorActual = $existente->{$campo} ?? null;
                if ($valorActual !== null && (string) $valorActual !== '' && (float) $valorActual !== 0.0) {
                    continue;
                }
            }
            $update[$campo] = $valor;
        }

        if ($update !== []) {
            $update['actualizada_en'] = CarbonImmutable::now();
            DB::table('casos_cobranza')->where('caso_id', $casoId)->update($update);
        }

        return ['estado' => 'procesada', 'razon' => null];
    }
}
