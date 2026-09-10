<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class ConfigurarCanalesTipoGestion
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param list<int> $canalIds Empty means every enabled project channel. */
    public function execute(int $proyectoId, int $tipoId, array $canalIds): void
    {
        $this->db->transaction(function () use ($proyectoId, $tipoId, $canalIds): void {
            $tipo = $this->db->table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('id', $tipoId)->lockForUpdate()->first();
            $ids = array_values(array_unique($canalIds));
            $validos = $this->db->table('canal_proyecto')->where('proyecto_id', $proyectoId)->whereIn('canal_id', $ids)->count();
            if ($tipo === null || $validos !== count($ids)) {
                throw ValidationException::withMessages(['form.canales' => 'Selecciona canales de este proyecto.']);
            }
            $this->db->table('canal_tipo_gestion')->where('proyecto_id', $proyectoId)->where('tipo_gestion_id', $tipoId)->delete();
            foreach ($ids as $canalId) {
                $this->db->table('canal_tipo_gestion')->insert([
                    'proyecto_id' => $proyectoId, 'tipo_gestion_id' => $tipoId, 'canal_id' => $canalId,
                ]);
            }
        });
    }
}
