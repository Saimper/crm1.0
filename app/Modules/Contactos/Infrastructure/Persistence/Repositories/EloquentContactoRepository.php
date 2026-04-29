<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Persistence\Repositories;

use App\Modules\Contactos\Domain\Contracts\ContactoRepository;
use App\Modules\Contactos\Domain\Entities\Contacto;
use App\Modules\Contactos\Infrastructure\Persistence\Models\ContactoModel;

final class EloquentContactoRepository implements ContactoRepository
{
    public function save(Contacto $contacto): Contacto
    {
        $model = new ContactoModel();
        $model->proyecto_id  = $contacto->proyectoId;
        $model->persona_id   = $contacto->personaId;
        $model->tipo         = $contacto->tipo->value;
        $model->valor        = $contacto->valor;
        $model->etiqueta     = $contacto->etiqueta;
        $model->es_principal = $contacto->esPrincipal;
        $model->activo       = $contacto->activo;
        $model->creada_en    = $contacto->creadaEn;

        $model->save();

        return $contacto->conId((int) $model->id);
    }

    public function existeValorParaPersona(int $proyectoId, int $personaId, string $valor): bool
    {
        return ContactoModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('persona_id', $personaId)
            ->where('valor', $valor)
            ->whereNull('eliminada_en')
            ->exists();
    }
}
