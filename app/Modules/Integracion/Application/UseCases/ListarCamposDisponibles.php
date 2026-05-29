<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use Illuminate\Database\ConnectionInterface;

/**
 * Lista los campos del CRM mapeables a campos del lead de ViciDial, para que el
 * wrapper pueble su UI de mapeo "campo CRM → campo ViciDial". Autenticado vía
 * HMAC con el sso_secret del mandante.
 *
 * Devuelve un array plano de fuentes: persona (identidad), contactos, y campos
 * personalizados de caso (dedupe por código entre carteras del proyecto).
 */
final class ListarCamposDisponibles
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return list<array{source: string, label: string, tipo: string, grupo: string}>
     */
    public function execute(int $mandanteId, int $proyectoId): array
    {
        $perteneceAlMandante = $this->db->table('proyectos')
            ->where('id', $proyectoId)
            ->where('mandante_id', $mandanteId)
            ->whereNull('eliminada_en')
            ->exists();

        if (! $perteneceAlMandante) {
            return [];
        }

        return [
            ...$this->camposPersona(),
            ...$this->tiposContacto(),
            ...$this->camposCaso($proyectoId),
        ];
    }

    /**
     * Campos de identidad de persona que el CRM emite al guardar (EditarPersona).
     *
     * @return list<array{source: string, label: string, tipo: string, grupo: string}>
     */
    private function camposPersona(): array
    {
        return [
            ['source' => 'persona.nombres', 'label' => 'Nombres', 'tipo' => 'texto_corto', 'grupo' => 'persona'],
            ['source' => 'persona.apellidos', 'label' => 'Apellidos', 'tipo' => 'texto_corto', 'grupo' => 'persona'],
            ['source' => 'persona.identificacion', 'label' => 'Identificación', 'tipo' => 'texto_corto', 'grupo' => 'persona'],
        ];
    }

    /**
     * @return list<array{source: string, label: string, tipo: string, grupo: string}>
     */
    private function tiposContacto(): array
    {
        return [
            ['source' => 'contacto.telefono', 'label' => 'Teléfono', 'tipo' => 'texto_corto', 'grupo' => 'contacto'],
            ['source' => 'contacto.correo', 'label' => 'Correo', 'tipo' => 'texto_corto', 'grupo' => 'contacto'],
            ['source' => 'contacto.direccion', 'label' => 'Dirección', 'tipo' => 'texto_largo', 'grupo' => 'contacto'],
        ];
    }

    /**
     * Campos personalizados de caso del proyecto (unión de carteras, dedupe por código).
     *
     * @return list<array{source: string, label: string, tipo: string, grupo: string}>
     */
    private function camposCaso(int $proyectoId): array
    {
        $rows = $this->db->table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', 'caso')
            ->where('activo', true)
            ->orderBy('orden')
            ->get(['codigo', 'etiqueta', 'tipo']);

        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $codigo = (string) $r->codigo;
            if (isset($seen[$codigo])) {
                continue;
            }
            $seen[$codigo] = true;
            $out[] = [
                'source' => 'caso_cp.'.$codigo,
                'label' => (string) $r->etiqueta,
                'tipo' => (string) $r->tipo,
                'grupo' => 'caso',
            ];
        }

        return $out;
    }
}
