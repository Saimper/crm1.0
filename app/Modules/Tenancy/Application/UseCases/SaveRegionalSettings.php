<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SaveRegionalSettings
{
    public function __construct(
        private RegionalConfiguration $configuration,
        private RegistroDeAccionesAdministrativas $audit,
    ) {}

    /** Authorization belongs to the caller; IDs are explicit for jobs and HTTP alike. */
    public function execute(int $mandanteId, ?int $projectId, ?RegionalSettings $settings): void
    {
        DB::transaction(function () use ($mandanteId, $projectId, $settings): void {
            $query = DB::table($projectId === null ? 'mandantes' : 'proyectos')->where('id', $projectId ?? $mandanteId);
            if ($projectId !== null) {
                $query->where('mandante_id', $mandanteId);
            }
            $before = $query->lockForUpdate()->first();
            if ($before === null) {
                throw new InvalidArgumentException('El proyecto no pertenece al mandante indicado.');
            }
            $effective = $settings ?? $this->configuration->forMandante($mandanteId);
            if ($effective->decimalPlaces === 2) {
                $projects = DB::table('proyectos')->where('mandante_id', $mandanteId)
                    ->when($projectId !== null, fn ($q) => $q->where('id', $projectId))
                    ->when($projectId === null, fn ($q) => $q->whereNull('decimales'))->pluck('id');
                foreach (['casos_cobranza' => ['monto_original', 'saldo_capital', 'saldo_interes', 'saldo_total', 'cuota_mensual'],
                    'casos_lead_venta' => ['valor_estimado'], 'compromisos_promesa_pago' => ['monto'],
                    'compromisos_cierre_venta' => ['monto_cierre'], 'valores_campo_personalizado' => ['valor_moneda_monto']] as $table => $columns) {
                    foreach ($columns as $column) {
                        $amounts = DB::table($table);
                        if ($table === 'valores_campo_personalizado') {
                            $amounts->whereIn('campo_personalizado_id', DB::table('campos_personalizados')->whereIn('proyecto_id', $projects)->select('id'));
                        } else {
                            $amounts->whereIn('proyecto_id', $projects);
                        }
                        if ($amounts->whereRaw("`{$column}` <> ROUND(`{$column}`, 2)")->exists()) {
                            throw new InvalidArgumentException('Hay montos con tres decimales. Conserva esa precisión para no ocultar información.');
                        }
                    }
                }
            }
            $values = $settings?->toArray() ?? array_fill_keys(array_keys($effective->toArray()), null);
            $query->update($values + ['actualizada_en' => now()]);
            $changes = [];
            foreach ($values as $field => $value) {
                if (($before->{$field} ?? null) !== $value) {
                    $changes[$field] = ['antes' => $before->{$field} ?? null, 'despues' => $value];
                }
            }
            $this->audit->cambio($projectId === null ? 'mandantes' : 'proyectos', $projectId ?? $mandanteId, $changes, $projectId, $mandanteId);
        });
        $this->configuration->clear();
    }
}
