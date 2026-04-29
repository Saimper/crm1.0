<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Application\Observers;

use App\Modules\Auditoria\Infrastructure\Persistence\Models\AuditoriaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Observer Eloquent genérico: registra eventos de creación, actualización y borrado
 * de cualquier modelo al que se lo registre. Omite campos sensibles en los snapshots.
 */
final class AuditoriaObserver
{
    private const CAMPOS_OMITIDOS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public function created(Model $model): void
    {
        $this->registrar($model, 'creado', null, $this->snapshot($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $original = $this->snapshot($model->getOriginal());
        $despues  = $this->snapshot($model->getAttributes());
        $cambios  = $this->cambios($model);

        if ($cambios === []) {
            return;
        }

        $this->registrar($model, 'actualizado', $original, $despues, $cambios);
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, 'eliminado', $this->snapshot($model->getAttributes()), null);
    }

    /**
     * @param array<string, mixed>|null $antes
     * @param array<string, mixed>|null $despues
     * @param array<string, mixed>      $cambios
     */
    private function registrar(
        Model $model,
        string $evento,
        ?array $antes,
        ?array $despues,
        array $cambios = [],
    ): void {
        $proyectoId = $this->proyectoIdDesdeModelo($model);

        AuditoriaModel::query()->create([
            'public_id'     => (string) Str::ulid(),
            'proyecto_id'   => $proyectoId,
            'usuario_id'    => auth()->id(),
            'entidad_tipo'  => $this->entidadTipo($model),
            'entidad_id'    => (int) $model->getKey(),
            'evento'        => $evento,
            'datos_antes'   => $antes,
            'datos_despues' => $despues,
            'cambios'       => $cambios !== [] ? $cambios : null,
            'ip'            => request()?->ip(),
            'user_agent'    => mb_substr((string) request()?->userAgent(), 0, 512) ?: null,
        ]);
    }

    private function proyectoIdDesdeModelo(Model $model): ?int
    {
        $atrs = $model->getAttributes();
        if (array_key_exists('proyecto_id', $atrs) && $atrs['proyecto_id'] !== null) {
            return (int) $atrs['proyecto_id'];
        }

        return null;
    }

    private function entidadTipo(Model $model): string
    {
        $tabla = $model->getTable();
        if ($tabla !== '') {
            return $tabla;
        }

        $clase = $model::class;
        $pedazos = explode('\\', $clase);

        return end($pedazos) ?: $clase;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>|null
     */
    private function snapshot(array $datos): ?array
    {
        if ($datos === []) {
            return null;
        }
        foreach (self::CAMPOS_OMITIDOS as $campo) {
            unset($datos[$campo]);
        }

        return $datos;
    }

    /**
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    private function cambios(Model $model): array
    {
        $cambios = [];
        $dirty = $model->getDirty();

        foreach ($dirty as $campo => $nuevo) {
            if (in_array($campo, self::CAMPOS_OMITIDOS, true)) {
                continue;
            }
            if (in_array($campo, ['actualizada_en', 'updated_at'], true)) {
                continue;
            }
            $cambios[$campo] = [
                'antes'   => $model->getOriginal($campo),
                'despues' => $nuevo,
            ];
        }

        return $cambios;
    }
}
