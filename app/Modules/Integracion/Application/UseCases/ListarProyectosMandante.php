<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use Illuminate\Database\ConnectionInterface;

/**
 * F37: lista proyectos activos del mandante para que el wrapper pueble el
 * dropdown "campaña → crm_proyecto_id" en su UI de mapeo. Se autentica
 * vía middleware HMAC con el sso_secret del mandante.
 */
final class ListarProyectosMandante
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo_operacion: string, activo: bool}>
     */
    public function execute(int $mandanteId): array
    {
        $rows = $this->db->table('proyectos')
            ->where('mandante_id', $mandanteId)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'tipo_operacion', 'activo']);

        return $rows->map(fn (object $r): array => [
            'id' => (int) $r->id,
            'codigo' => (string) $r->codigo,
            'nombre' => (string) $r->nombre,
            'tipo_operacion' => (string) $r->tipo_operacion,
            'activo' => (bool) $r->activo,
        ])->all();
    }
}
