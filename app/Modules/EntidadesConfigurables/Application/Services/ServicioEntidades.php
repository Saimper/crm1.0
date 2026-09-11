<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Application\Services;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models\EntidadConfigurableModel;
use App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models\EntidadRegistroModel;
use App\Support\Codigo\GeneradorCodigo;
use App\Support\Database\CarterasOperativas;
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
        if ($carteraId !== null) {
            CarterasOperativas::exigirCartera(DB::connection(), $proyectoId, $carteraId);
        }
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

    /**
     * `sinScopeProyecto()` apaga el global scope porque estas escrituras llegan
     * desde /admin, donde no hay proyecto activo. Apagarlo obliga a poner el
     * proyecto A MANO: sin el `where('proyecto_id', …)` el UPDATE alcanzaba la
     * entidad de cualquier cliente con solo cambiar el id del payload. Por eso el
     * proyecto es un parámetro obligatorio y no algo que se deduzca de la fila.
     */
    public function actualizarEntidad(
        int $proyectoId,
        int $entidadId,
        string $nombre,
        ?string $descripcion,
        ?string $icono,
        bool $activo,
    ): void {
        EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('id', $entidadId)
            ->update([
                // El proyecto de la entidad no aparece aquí: identifica a su dueño
                // y no se reescribe desde una edición.
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'icono' => $icono,
                'activo' => $activo,
            ]);
    }

    /** Borrado lógico acotado al proyecto dueño de la entidad (ver actualizarEntidad). */
    public function eliminarEntidad(int $proyectoId, int $entidadId): void
    {
        EntidadConfigurableModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
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
            ->whereNull('eliminada_en')
            ->where(fn ($q) => $q->whereNull('cartera_id')->orWhereExists(fn ($portfolio) => $portfolio
                ->selectRaw('1')->from('carteras')
                ->whereColumn('carteras.id', 'entidades_configurables.cartera_id')
                ->whereColumn('carteras.proyecto_id', 'entidades_configurables.proyecto_id')
                ->where('carteras.activo', true)->whereNull('carteras.eliminada_en')));

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
        return DB::transaction(function () use (
            $proyectoId, $entidadId, $titulo, $valoresPorCodigo, $casoId, $personaId, $usuarioId,
        ): int {
            $entidad = EntidadConfigurableModel::query()
                ->sinScopeProyecto()
                ->where('proyecto_id', $proyectoId)
                ->where('id', $entidadId)
                ->whereNull('eliminada_en')
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if ($entidad === null) {
                throw new RuntimeException('Entidad configurable no encontrada.');
            }

            $this->validarVinculo($proyectoId, $entidad, $casoId, $personaId);

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
                permitirVaciar: true,
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
            $entidad = EntidadConfigurableModel::query()->sinScopeProyecto()->where('proyecto_id', $proyectoId)
                ->where('id', $entidadId)->where('activo', true)->whereNull('eliminada_en')->lockForUpdate()->first();
            $registro = EntidadRegistroModel::query()->sinScopeProyecto()->where('proyecto_id', $proyectoId)
                ->where('entidad_configurable_id', $entidadId)->where('id', $registroId)->whereNull('eliminado_en')->lockForUpdate()->first();
            if ($entidad === null || $registro === null) {
                throw new RuntimeException('El registro o la entidad ya no están disponibles.');
            }
            $this->validarVinculo($proyectoId, $entidad, $registro->caso_id, $registro->persona_id);
            EntidadRegistroModel::query()
                ->sinScopeProyecto()
                ->where('proyecto_id', $proyectoId)
                ->where('id', $registroId)
                ->where('entidad_configurable_id', $entidadId)
                ->whereNull('eliminado_en')
                ->update(['titulo' => $titulo]);

            $this->serviciosCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: $entidadId,
                entidadId: $registroId,
                valoresPorCodigo: $valoresPorCodigo,
                // El gestor de registros edita la ficha entera.
                permitirVaciar: true,
            );
        });
    }

    public function eliminarRegistro(int $proyectoId, int $registroId): void
    {
        DB::transaction(function () use ($proyectoId, $registroId): void {
            $registro = EntidadRegistroModel::query()
                ->sinScopeProyecto()
                ->where('proyecto_id', $proyectoId)
                ->where('id', $registroId)
                ->whereNull('eliminado_en')->first();
            if ($registro === null) {
                throw new RuntimeException('El registro ya no está disponible.');
            }
            $entidad = EntidadConfigurableModel::query()->sinScopeProyecto()->where('proyecto_id', $proyectoId)
                ->where('id', $registro->entidad_configurable_id)->where('activo', true)
                ->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($entidad === null) {
                throw new RuntimeException('La entidad ya no está disponible.');
            }
            // Use the same definition-before-record lock order as updates.
            $registro = EntidadRegistroModel::query()->sinScopeProyecto()->where('proyecto_id', $proyectoId)
                ->where('id', $registroId)->where('entidad_configurable_id', $entidad->id)
                ->whereNull('eliminado_en')->lockForUpdate()->first();
            if ($registro === null) {
                throw new RuntimeException('El registro ya no está disponible.');
            }
            $this->validarVinculo($proyectoId, $entidad, $registro->caso_id, $registro->persona_id);
            $registro->eliminado_en = now()->toImmutable();
            $registro->save();
        });
    }

    private function validarVinculo(int $proyectoId, EntidadConfigurableModel $entidad, ?int $casoId, ?int $personaId): void
    {
        if (! DB::table('proyectos as p')->join('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->where('p.id', $proyectoId)->where('p.activo', true)->whereNull('p.eliminada_en')
            ->where('m.activo', true)->whereNull('m.eliminada_en')->exists()) {
            throw new RuntimeException('El proyecto ya no está disponible.');
        }
        if ($entidad->cartera_id !== null) {
            CarterasOperativas::exigirCartera(DB::connection(), $proyectoId, (int) $entidad->cartera_id);
        }
        if ($entidad->relacion_con === 'persona' && $personaId === null) {
            throw new RuntimeException('Crea este registro desde la ficha de la persona correspondiente.');
        }
        if ($entidad->relacion_con === 'caso' && $casoId === null) {
            throw new RuntimeException('Crea este registro desde la ficha de la cuenta correspondiente.');
        }
        if ($personaId !== null && ! DB::table('personas')->where('proyecto_id', $proyectoId)->where('id', $personaId)->whereNull('eliminada_en')->exists()) {
            throw new RuntimeException('La persona no pertenece a este proyecto.');
        }
        if ($casoId !== null) {
            $caso = CarterasOperativas::exigirCaso(DB::connection(), $proyectoId, $casoId);
            if (($personaId !== null && (int) $caso->persona_id !== $personaId)
                || ($entidad->cartera_id !== null && (int) $caso->cartera_id !== (int) $entidad->cartera_id)) {
                throw new RuntimeException('La cuenta no pertenece al proyecto, la persona o la cartera de esta entidad.');
            }
        }
        if ($personaId !== null && $entidad->cartera_id !== null
            && ! CarterasOperativas::casos(DB::connection(), $proyectoId)->where('c.persona_id', $personaId)
                ->where('c.cartera_id', (int) $entidad->cartera_id)->exists()) {
            throw new RuntimeException('La persona no tiene cuentas activas en la cartera de esta entidad.');
        }
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
