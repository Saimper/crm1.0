<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Application\Services;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models\EntidadConfigurableModel;
use App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models\EntidadRegistroModel;
use App\Support\Codigo\GeneradorCodigo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orquesta el ciclo de vida de entidades configurables y sus registros.
 *
 * - Definición: gestionada vía `campos_personalizados` con ámbito `entidad_configurable`.
 * - Valores: reutiliza `ServicioCamposPersonalizados` de §7 (no duplica lógica).
 * - Reglas de validación: heredadas del evaluador existente.
 */
final readonly class ServicioEntidades
{
    public function __construct(
        private ServicioCamposPersonalizados $serviciosCampos,
    ) {}

    /**
     * Crea una entidad configurable en el proyecto (o cartera).
     * Código único por proyecto.
     */
    public function crearEntidad(
        int $proyectoId,
        string $codigo,
        string $nombre,
        RelacionEntidad $relacion = RelacionEntidad::NINGUNA,
        ?int $carteraId = null,
        ?string $descripcion = null,
        ?string $icono = null,
    ): int {
        // Defensa en profundidad: aunque el Livewire ya normaliza, otras vías
        // (importación masiva, tests directos) pueden pasar entrada cruda.
        $codigo = GeneradorCodigo::normalizar($codigo, 80);

        $existe = EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->exists();
        if ($existe) {
            throw new RuntimeException("Ya existe una entidad con código {$codigo} en el proyecto.");
        }

        $model = new EntidadConfigurableModel;
        $model->public_id = (string) Str::ulid();
        $model->proyecto_id = $proyectoId;
        $model->cartera_id = $carteraId;
        $model->codigo = $codigo;
        $model->nombre = $nombre;
        $model->descripcion = $descripcion;
        $model->icono = $icono;
        $model->relacion_con = $relacion->value;
        $model->activo = true;
        $model->save();

        return (int) $model->id;
    }

    public function actualizarEntidad(
        int $entidadId,
        string $nombre,
        ?string $descripcion,
        ?string $icono,
        bool $activo,
    ): void {
        EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('id', $entidadId)
            ->update([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'icono' => $icono,
                'activo' => $activo,
            ]);
    }

    public function eliminarEntidad(int $entidadId): void
    {
        EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('id', $entidadId)
            ->update(['activo' => false, 'eliminada_en' => now()]);
    }

    /**
     * Lista entidades activas de un proyecto, opcionalmente filtradas por cartera.
     *
     * @return Collection<int, EntidadConfigurableModel>
     */
    public function entidadesDelProyecto(int $proyectoId, ?int $carteraId = null): Collection
    {
        $q = EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en');

        if ($carteraId !== null) {
            $q->where(function ($w) use ($carteraId): void {
                $w->whereNull('cartera_id')->orWhere('cartera_id', $carteraId);
            });
        }

        return $q->orderBy('nombre')->get();
    }

    /**
     * Crea un registro para la entidad dada, persistiendo valores en `valores_campo_personalizado`.
     *
     * @param  array<string, mixed>  $valoresPorCodigo
     */
    public function crearRegistro(
        int $proyectoId,
        int $entidadId,
        string $titulo,
        array $valoresPorCodigo,
        ?int $casoId = null,
        ?int $personaId = null,
        ?int $usuarioId = null,
    ): int {
        $entidad = EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('id', $entidadId)
            ->whereNull('eliminada_en')
            ->first();

        if ($entidad === null) {
            throw new RuntimeException('Entidad configurable no encontrada.');
        }

        return DB::transaction(function () use (
            $proyectoId, $entidad, $titulo, $valoresPorCodigo, $casoId, $personaId, $usuarioId,
        ): int {
            $registro = new EntidadRegistroModel;
            $registro->public_id = (string) Str::ulid();
            $registro->proyecto_id = $proyectoId;
            $registro->entidad_configurable_id = (int) $entidad->id;
            $registro->caso_id = $casoId;
            $registro->persona_id = $personaId;
            $registro->titulo = $titulo;
            $registro->creado_por_id = $usuarioId;
            $registro->save();

            $this->serviciosCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: (int) $entidad->id,
                entidadId: (int) $registro->id,
                valoresPorCodigo: $valoresPorCodigo,
            );

            return (int) $registro->id;
        });
    }

    /** @param array<string, mixed> $valoresPorCodigo */
    public function actualizarRegistro(
        int $proyectoId,
        int $entidadId,
        int $registroId,
        string $titulo,
        array $valoresPorCodigo,
    ): void {
        DB::transaction(function () use ($proyectoId, $entidadId, $registroId, $titulo, $valoresPorCodigo): void {
            EntidadRegistroModel::query()
                ->sinScopeProyecto()
                ->where('proyecto_id', $proyectoId)
                ->where('id', $registroId)
                ->whereNull('eliminado_en')
                ->update(['titulo' => $titulo]);

            $this->serviciosCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: $entidadId,
                entidadId: $registroId,
                valoresPorCodigo: $valoresPorCodigo,
            );
        });
    }

    public function eliminarRegistro(int $proyectoId, int $registroId): void
    {
        EntidadRegistroModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('id', $registroId)
            ->update(['eliminado_en' => now()]);
    }

    /**
     * Lista registros no eliminados de una entidad, opcionalmente filtrados por
     * caso o persona.
     *
     * @return Collection<int, EntidadRegistroModel>
     */
    public function registros(
        int $proyectoId,
        int $entidadId,
        ?int $casoId = null,
        ?int $personaId = null,
    ): Collection {
        $q = EntidadRegistroModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('entidad_configurable_id', $entidadId)
            ->whereNull('eliminado_en');

        if ($casoId !== null) {
            $q->where('caso_id', $casoId);
        }
        if ($personaId !== null) {
            $q->where('persona_id', $personaId);
        }

        return $q->orderByDesc('creado_en')->get();
    }
}
