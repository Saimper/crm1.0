<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Infrastructure\Persistence\Repositories;

use App\Modules\Clientes\Domain\Contracts\ClienteRepository;
use App\Modules\Clientes\Domain\Entities\Cliente;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Infrastructure\Persistence\Models\ClienteModel;

final class EloquentClienteRepository implements ClienteRepository
{
    public function save(Cliente $cliente): Cliente
    {
        $model = new ClienteModel();
        $model->public_id              = $cliente->publicId;
        $model->tipo_persona           = $cliente->tipoPersona->value;
        $model->tipo_identificacion_id = $cliente->tipoIdentificacionId;
        $model->identificacion         = $cliente->identificacion->asString();
        $model->nombres                = $cliente->nombres;
        $model->apellidos              = $cliente->apellidos;
        $model->razon_social           = $cliente->razonSocial;
        $model->fecha_nacimiento       = $cliente->fechaNacimiento;
        $model->creada_en              = $cliente->creadaEn;

        $model->save();

        return $cliente->conId((int) $model->id);
    }

    public function existePorIdentificacion(Identificacion $identificacion): bool
    {
        return ClienteModel::query()
            ->where('identificacion', $identificacion->asString())
            ->whereNull('eliminada_en')
            ->exists();
    }
}
