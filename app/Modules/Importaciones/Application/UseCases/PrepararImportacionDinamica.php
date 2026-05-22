<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Events\CamposPersonalizadosCreadosPorImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionConEsquemaDinamicoIniciada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionSinPermisoCamposException;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoDryRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Prepara una importación dinámica: valida el esquema, crea los campos
 * personalizados necesarios, persiste el esquema en DB y emite eventos.
 *
 * Los campos se crean ANTES de despachar el job. El job solo escribe valores.
 */
final readonly class PrepararImportacionDinamica
{
    public function __construct(
        private CampoPersonalizadoImportacionRepository $cpRepo,
        private ConnectionInterface $db,
        private EventDispatcher $events,
    ) {}

    public function execute(PrepararImportacionInput $input): PrepararImportacionOutput
    {
        return $this->db->transaction(function () use ($input): PrepararImportacionOutput {
            $input->esquema->validar();

            $columnasCP = $input->esquema->columnasParaCamposPersonalizados();

            if ($columnasCP !== []
                && $input->esquema->carteraId !== null
                && $input->esquema->modo !== ModoImportacion::UPDATE) {
                $camposNuevosReales = 0;
                foreach ($columnasCP as $columna) {
                    if (! $this->cpRepo->existeCampo(
                        $input->esquema->proyectoId,
                        $input->esquema->carteraId,
                        $columna->codigoSugerido(),
                    )) {
                        $camposNuevosReales++;
                    }
                }

                if ($camposNuevosReales > 0 && ! $input->tienePermisoCampos) {
                    throw new ImportacionSinPermisoCamposException(
                        'crear campos personalizados durante la importación',
                        $input->esquema->proyectoId,
                    );
                }
            }

            $camposCreados = 0;
            $camposReutilizados = 0;
            $camposIds = [];
            $auditoriaCampos = [];

            foreach ($columnasCP as $columna) {
                $codigo = $columna->codigoSugerido();
                $etiqueta = $columna->etiquetaSugerida();

                if ($input->esquema->carteraId === null) {
                    continue;
                }

                if ($this->cpRepo->existeCampo($input->esquema->proyectoId, $input->esquema->carteraId, $codigo)) {
                    $campoId = (int) $this->db->table('campos_personalizados')
                        ->where('proyecto_id', $input->esquema->proyectoId)
                        ->where('ambito', 'caso')
                        ->where('ambito_id', $input->esquema->carteraId)
                        ->where('codigo', $codigo)
                        ->where('activo', true)
                        ->value('id');
                    $camposReutilizados++;
                    $camposIds[] = $campoId;
                    $auditoriaCampos[] = ['campo_id' => $campoId, 'columna_original' => $columna->nombreOriginal];
                } else {
                    $campoId = $this->cpRepo->crearCampo(
                        $input->esquema->proyectoId,
                        $input->esquema->carteraId,
                        $codigo,
                        $etiqueta,
                        $columna->tipoInferido,
                    );
                    $camposCreados++;
                    $camposIds[] = $campoId;
                    $auditoriaCampos[] = ['campo_id' => $campoId, 'columna_original' => $columna->nombreOriginal];
                }
            }

            if ($auditoriaCampos !== []) {
                $this->cpRepo->registrarAuditoriaCampos($input->importacionId, $auditoriaCampos);
            }

            $esquemaJson = $input->esquema->serializar();

            $this->db->table('importaciones')
                ->where('id', $input->importacionId)
                ->update([
                    'esquema' => $esquemaJson,
                    'estado' => 'preparada',
                    'actualizada_en' => CarbonImmutable::now(),
                ]);

            if ($camposCreados > 0 && $input->esquema->carteraId !== null) {
                $this->events->dispatch(new CamposPersonalizadosCreadosPorImportacion(
                    importacionId: $input->importacionId,
                    proyectoId: $input->esquema->proyectoId,
                    carteraId: $input->esquema->carteraId,
                    camposPersonalizadosIds: $camposIds,
                ));
            }

            $this->events->dispatch(new ImportacionConEsquemaDinamicoIniciada(
                importacionId: $input->importacionId,
                proyectoId: $input->esquema->proyectoId,
                totalColumnasCP: count($columnasCP),
                totalColumnasSistema: count($input->esquema->columnasParaSistema()),
            ));

            $resultadoDryRun = new ResultadoDryRun(
                esValido: true,
                filasTotales: 0,
                filasValidas: 0,
                filasConAdvertencia: 0,
                filasInvalidas: 0,
                erroresMuestra: [],
                camposPersonalizadosACrear: array_map(
                    static fn ($c) => $c->codigoSugerido(),
                    $columnasCP,
                ),
                advertencias: [],
            );

            return new PrepararImportacionOutput(
                importacionId: $input->importacionId,
                camposCreados: $camposCreados,
                camposReutilizados: $camposReutilizados,
                resultadoDryRun: $resultadoDryRun,
            );
        });
    }
}
