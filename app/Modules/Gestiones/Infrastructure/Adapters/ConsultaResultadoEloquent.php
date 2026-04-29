<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Adapters;

use App\Modules\Gestiones\Domain\Contracts\ConsultaResultado;
use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;
use App\Modules\Gestiones\Infrastructure\Persistence\Models\ResultadoModel;

final class ConsultaResultadoEloquent implements ConsultaResultado
{
    public function banderas(int $resultadoId): BanderasResultado
    {
        /** @var ResultadoModel $row */
        $row = ResultadoModel::query()->sinScopeProyecto()->findOrFail($resultadoId);

        return new BanderasResultado(
            esContactoEfectivo: (bool) $row->es_contacto_efectivo,
            requiereCompromiso: (bool) $row->requiere_compromiso,
            requiereCausa:      (bool) $row->requiere_causa,
        );
    }
}
