<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Persistence\Repositories;

use App\Modules\Gestiones\Domain\Contracts\GestionRepository;
use App\Modules\Gestiones\Domain\Entities\Gestion;
use App\Modules\Gestiones\Infrastructure\Persistence\Models\GestionModel;

final class EloquentGestionRepository implements GestionRepository
{
    public function save(Gestion $gestion): Gestion
    {
        $model = new GestionModel;
        $model->public_id = $gestion->publicId;
        $model->proyecto_id = $gestion->proyectoId;
        $model->caso_id = $gestion->casoId;
        $model->persona_id = $gestion->personaId;
        $model->contacto_id = $gestion->contactoId;
        $model->canal_id = $gestion->canalId;
        $model->tipo_gestion_id = $gestion->tipoGestionId;
        $model->resultado_id = $gestion->resultadoId;
        $model->motivo_no_contacto_id = $gestion->motivoNoContactoId;
        $model->causa_id = $gestion->causaId;
        $model->usuario_id = $gestion->usuarioId;
        $model->notas = $gestion->notas;
        $model->duracion_segundos = $gestion->duracion?->valor;
        $model->creada_en = $gestion->creadaEn;

        $model->save();

        return $gestion->conId((int) $model->id);
    }
}
