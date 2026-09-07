<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Adapters;

use App\Modules\Gestiones\Domain\Contracts\ConsultaCompatibilidadResultado;
use Illuminate\Database\ConnectionInterface;

final readonly class ConsultaCompatibilidadResultadoEloquent implements ConsultaCompatibilidadResultado
{
    public function __construct(private ConnectionInterface $db) {}

    public function admite(int $proyectoId, int $tipoGestionId, int $resultadoId): bool
    {
        $declarados = $this->db->table('resultado_tipo_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_gestion_id', $tipoGestionId)
            ->count();

        if ($declarados === 0) {
            return true;
        }

        return $this->db->table('resultado_tipo_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_gestion_id', $tipoGestionId)
            ->where('resultado_id', $resultadoId)
            ->exists();
    }
}
