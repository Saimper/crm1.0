<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Listeners;

use App\Modules\Casos\Application\DTOs\CerrarCasoInput;
use App\Modules\Casos\Application\UseCases\CerrarCaso;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use Illuminate\Database\ConnectionInterface;

/**
 * Cierra el caso cuando la gestión se registra con un resultado que lo da por
 * terminado (`resultados.estado_caso_cierre_id`).
 *
 * Es el cable que faltaba entre Gestiones y Casos: el UseCase CerrarCaso, la
 * entidad y el evento CasoCerrado existían desde el principio, pero nadie los
 * invocaba, así que ningún caso podía salir de su estado inicial.
 *
 * Se ejecuta dentro de la transacción de RegistrarGestion, igual que
 * ActualizarDesnormalizadosDesdeGestion.
 */
final readonly class CerrarCasoDesdeGestion
{
    public function __construct(
        private CerrarCaso $cerrarCaso,
        private ConnectionInterface $db,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        $estadoCierreId = $this->db->table('resultados')
            ->where('id', $evento->resultadoId)
            ->value('estado_caso_cierre_id');

        if ($estadoCierreId === null) {
            return;
        }

        // Un caso ya cerrado que recibe otra gestión no es un error de negocio:
        // se sigue gestionando un caso cerrado. La entidad lanzaría
        // TransicionCasoInvalida y tumbaría el registro de la gestión, así que
        // se comprueba antes y se sale en silencio.
        $yaCerrado = $this->db->table('casos')
            ->where('id', $evento->casoId)
            ->value('cerrado_en');

        if ($yaCerrado !== null) {
            return;
        }

        $this->cerrarCaso->execute(new CerrarCasoInput(
            casoId: $evento->casoId,
            estadoCasoTerminalId: (int) $estadoCierreId,
            cerradoEn: $evento->creadaEn,
        ));
    }
}
