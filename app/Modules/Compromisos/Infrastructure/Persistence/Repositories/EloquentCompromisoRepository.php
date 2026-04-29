<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Persistence\Repositories;

use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\ValueObjects\EstadoCompromiso;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use App\Modules\Compromisos\Infrastructure\Persistence\Models\CompromisoModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentCompromisoRepository implements CompromisoRepository
{
    public function save(Compromiso $compromiso): Compromiso
    {
        $model = $compromiso->id !== null
            ? CompromisoModel::query()->sinScopeProyecto()->findOrFail($compromiso->id)
            : new CompromisoModel();

        $model->public_id         = $compromiso->publicId;
        $model->proyecto_id       = $compromiso->proyectoId;
        $model->caso_id           = $compromiso->casoId;
        $model->gestion_origen_id = $compromiso->gestionOrigenId;
        $model->usuario_id        = $compromiso->usuarioId;
        $model->tipo_compromiso   = $compromiso->tipo->value;
        $model->estado            = $compromiso->estado->value;
        $model->fecha_vencimiento = $compromiso->fechaVencimiento;
        $model->fecha_resolucion  = $compromiso->fechaResolucion;
        if ($compromiso->id === null) {
            $model->creada_en = $compromiso->creadaEn;
        }

        $model->save();

        return $compromiso->id !== null ? $compromiso : $compromiso->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Compromiso
    {
        /** @var CompromisoModel|null $model */
        $model = CompromisoModel::query()->sinScopeProyecto()->find($id);
        if ($model === null) {
            throw new RuntimeException("Compromiso {$id} no encontrado.");
        }

        return Compromiso::reconstituir(
            id:               (int) $model->id,
            publicId:         (string) $model->public_id,
            proyectoId:       (int) $model->proyecto_id,
            casoId:           (int) $model->caso_id,
            gestionOrigenId:  $model->gestion_origen_id !== null ? (int) $model->gestion_origen_id : null,
            usuarioId:        (int) $model->usuario_id,
            tipo:             TipoCompromiso::from((string) $model->tipo_compromiso),
            estado:           EstadoCompromiso::from((string) $model->estado),
            fechaVencimiento: $model->fecha_vencimiento instanceof DateTimeImmutable
                ? $model->fecha_vencimiento
                : new DateTimeImmutable((string) $model->fecha_vencimiento),
            fechaResolucion:  $model->fecha_resolucion instanceof DateTimeImmutable
                ? $model->fecha_resolucion
                : ($model->fecha_resolucion !== null ? new DateTimeImmutable((string) $model->fecha_resolucion) : null),
            creadaEn:         $model->creada_en instanceof DateTimeImmutable
                ? $model->creada_en
                : new DateTimeImmutable((string) $model->creada_en),
        );
    }

    public function existenVigentesParaCaso(int $casoId): bool
    {
        return CompromisoModel::query()
            ->sinScopeProyecto()
            ->where('caso_id', $casoId)
            ->where('estado', EstadoCompromiso::PENDIENTE->value)
            ->whereNull('eliminada_en')
            ->exists();
    }
}
