<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Lee y guarda qué columnas ve un usuario en un listado, por proyecto.
 *
 * Es preferencia de presentación: ante cualquier dato ausente o corrupto se cae
 * en la configuración por defecto en vez de fallar.
 */
final readonly class PreferenciasColumnasCaso
{
    private const VISTA = 'casos.listado';

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @return list<string>
     */
    public function cargar(int $usuarioId, int $proyectoId, string $tipoOperacion): array
    {
        $guardado = $this->db->table('preferencias_columnas')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('vista', self::VISTA)
            ->value('columnas');

        if (! is_string($guardado)) {
            return CatalogoColumnasCaso::POR_DEFECTO;
        }

        $claves = json_decode($guardado, true);

        return is_array($claves)
            ? CatalogoColumnasCaso::sanear(array_values(array_filter($claves, 'is_string')), $tipoOperacion)
            : CatalogoColumnasCaso::POR_DEFECTO;
    }

    /**
     * @param  list<string>  $claves
     */
    public function guardar(int $usuarioId, int $proyectoId, string $tipoOperacion, array $claves): void
    {
        $saneadas = CatalogoColumnasCaso::sanear($claves, $tipoOperacion);
        $ahora = Carbon::now();

        $this->db->table('preferencias_columnas')->updateOrInsert(
            ['usuario_id' => $usuarioId, 'proyecto_id' => $proyectoId, 'vista' => self::VISTA],
            ['columnas' => json_encode($saneadas), 'actualizada_en' => $ahora, 'creada_en' => $ahora],
        );
    }

    public function olvidar(int $usuarioId, int $proyectoId): void
    {
        $this->db->table('preferencias_columnas')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('vista', self::VISTA)
            ->delete();
    }
}
