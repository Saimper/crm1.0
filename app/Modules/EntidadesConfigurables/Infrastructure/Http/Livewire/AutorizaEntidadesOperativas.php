<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire;

use App\Support\Database\CarterasOperativas;
use Illuminate\Support\Facades\DB;

/** Recheck the URL context, portfolio and linked owner on every Livewire action. */
trait AutorizaEntidadesOperativas
{
    private function autorizarContextoEntidad(string $permission, ?int $caseId, ?int $personId, ?int $portfolioId): ?int
    {
        abort_unless(app()->bound('tenancy.proyecto_activo')
            && (int) app('tenancy.proyecto_activo')->id === $this->proyectoId, 403);
        abort_unless(auth()->user()?->activo === true
            && auth()->user()->tienePermiso($permission, $this->proyectoId), 403);
        abort_unless(DB::table('proyectos as p')->join('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->where('p.id', $this->proyectoId)->where('p.activo', true)->whereNull('p.eliminada_en')
            ->where('m.activo', true)->whereNull('m.eliminada_en')->exists(), 404);

        if ($caseId !== null) {
            try {
                $case = CarterasOperativas::exigirCaso(DB::connection(), $this->proyectoId, $caseId);
            } catch (\DomainException) {
                abort(404);
            }
            abort_if(($portfolioId !== null && (int) $case->cartera_id !== $portfolioId)
                || ($personId !== null && (int) $case->persona_id !== $personId), 404);
            $portfolioId = (int) $case->cartera_id;
        }
        if ($portfolioId !== null) {
            abort_unless(CarterasOperativas::carteraDisponible(DB::connection(), $this->proyectoId, $portfolioId), 404);
            abort_unless(auth()->user()->tienePermiso($permission, $this->proyectoId, $portfolioId), 403);
        }
        if ($personId !== null) {
            abort_unless(DB::table('personas')->where('proyecto_id', $this->proyectoId)
                ->where('id', $personId)->whereNull('eliminada_en')->exists(), 404);
            $portfolios = CarterasOperativas::casos(DB::connection(), $this->proyectoId)
                ->where('c.persona_id', $personId)
                ->when($portfolioId !== null, fn ($q) => $q->where('c.cartera_id', $portfolioId))
                ->distinct()->pluck('c.cartera_id');
            abort_unless($portfolios->contains(fn ($id) => auth()->user()->tienePermiso($permission, $this->proyectoId, (int) $id)), 404);
        }

        return $portfolioId;
    }

    private function autorizarDefinicionEntidad(string $permission, int $entityId, ?int $caseId, ?int $personId, ?int $portfolioId = null): object
    {
        $portfolioId = $this->autorizarContextoEntidad($permission, $caseId, $personId, $portfolioId);
        $entity = DB::table('entidades_configurables')->where('proyecto_id', $this->proyectoId)
            ->where('id', $entityId)->where('activo', true)->whereNull('eliminada_en')->first();
        abort_if($entity === null, 404);
        abort_if(($entity->relacion_con === 'caso' && $caseId === null)
            || ($entity->relacion_con === 'persona' && $personId === null)
            || ($entity->relacion_con === 'ninguna' && ($caseId !== null || $personId !== null)), 404);
        if ($entity->cartera_id !== null) {
            abort_if($portfolioId !== null && (int) $entity->cartera_id !== $portfolioId, 404);
            $this->autorizarContextoEntidad($permission, $caseId, $personId, (int) $entity->cartera_id);
        }

        return $entity;
    }

    private function autorizarRegistroEntidad(string $permission, int $recordId, ?int $entityId, ?int $caseId, ?int $personId, ?int $portfolioId = null): object
    {
        $this->autorizarContextoEntidad($permission, $caseId, $personId, $portfolioId);
        $record = DB::table('entidades_registros')->where('proyecto_id', $this->proyectoId)
            ->where('id', $recordId)->whereNull('eliminado_en')
            ->when($entityId !== null, fn ($q) => $q->where('entidad_configurable_id', $entityId))
            ->where('caso_id', $caseId)->where('persona_id', $personId)->first();
        abort_if($record === null, 404);
        $this->autorizarDefinicionEntidad($permission, (int) $record->entidad_configurable_id, $caseId, $personId, $portfolioId);

        return $record;
    }
}
