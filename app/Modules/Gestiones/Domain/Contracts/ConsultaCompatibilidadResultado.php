<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Contracts;

interface ConsultaCompatibilidadResultado
{
    /**
     * ¿Admite ese tipo de gestión ese resultado, dentro de ese proyecto?
     *
     * Arranque en abierto por tipo: un tipo sin ninguna combinación declarada
     * admite todos los resultados del proyecto. Con la regla al revés, los
     * proyectos que aún no lo han configurado se quedarían sin poder registrar
     * una gestión.
     */
    public function admite(int $proyectoId, int $tipoGestionId, int $resultadoId): bool;
}
