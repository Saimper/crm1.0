<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Repositories;

use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use Illuminate\Support\Facades\DB;

final class DatabaseRegionalConfiguration implements RegionalConfiguration
{
    /** @var array<string, RegionalSettings> */
    private array $cache = [];

    public function clear(): void
    {
        $this->cache = [];
    }

    public function forProject(?int $projectId = null): RegionalSettings
    {
        if ($projectId === null && app()->bound('tenancy.proyecto_activo')) {
            $projectId = (int) data_get(app('tenancy.proyecto_activo'), 'id', 0) ?: null;
        }
        if ($projectId === null) {
            $mandanteId = app()->bound('tenancy.mandante_activo') ? (int) data_get(app('tenancy.mandante_activo'), 'id', 0) : 0;

            return $this->forMandante($mandanteId ?: null);
        }
        $key = 'project:'.$projectId;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $project = DB::table('proyectos')->where('id', $projectId)->first();
        if ($project === null) {
            return $this->forMandante(null);
        }
        $values = $this->forMandante((int) $project->mandante_id)->toArray();
        foreach ($values as $field => $default) {
            if (isset($project->{$field})) {
                $values[$field] = $project->{$field};
            }
        }

        return $this->cache[$key] = RegionalSettings::fromArray($values);
    }

    public function forMandante(?int $mandanteId): RegionalSettings
    {
        $key = 'mandante:'.($mandanteId ?? 0);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $row = $mandanteId === null ? null : DB::table('mandantes')->where('id', $mandanteId)->first();
        $values = $row === null ? [] : (array) $row;
        $values['zona_horaria'] ??= config('tenancy.zona_horaria_por_defecto', 'UTC');
        $values['moneda'] ??= config('tenancy.moneda_por_defecto', 'USD');

        return $this->cache[$key] = RegionalSettings::fromArray($values);
    }
}
