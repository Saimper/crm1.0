<?php

declare(strict_types=1);

namespace App\Modules\Cx\Application\Listeners;

use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use App\Modules\Cx\Domain\Contracts\CompromisoResolucionTicketRepository;
use App\Modules\Cx\Domain\Entities\CompromisoResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reacciona a `GestionRegistrada`: si el proyecto es tipo CX, el resultado exige compromiso
 * y se enviaron DatosResolucionTicket, crea Compromiso (núcleo) + CompromisoResolucionTicket (CTI)
 * en la misma transacción y dispara CompromisoCreado.
 */
final readonly class CrearResolucionDesdeGestion
{
    public function __construct(
        private CompromisoRepository $compromisoRepo,
        private CompromisoResolucionTicketRepository $resolucionRepo,
        private Dispatcher $eventos,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        if (! $evento->banderas->requiereCompromiso) {
            return;
        }
        if (! $evento->datosCompromiso instanceof DatosResolucionTicket) {
            return;
        }
        if (! $this->esProyectoCx($evento->proyectoId)) {
            return;
        }

        $datos = $evento->datosCompromiso;
        $ahora = new DateTimeImmutable;

        $compromiso = Compromiso::crear(
            publicId: (string) Str::ulid(),
            proyectoId: $evento->proyectoId,
            casoId: $evento->casoId,
            gestionOrigenId: $evento->gestionId,
            usuarioId: $evento->usuarioId,
            tipo: TipoCompromiso::RESOLUCION_TICKET,
            fechaVencimiento: $datos->fechaLimite->fechaLimite,
            creadaEn: $ahora,
        );
        $persistido = $this->compromisoRepo->save($compromiso);

        $this->resolucionRepo->save(CompromisoResolucionTicket::registrar(
            compromisoId: (int) $persistido->id,
            proyectoId: $persistido->proyectoId,
            accion: $datos->accion,
            fechaLimite: $datos->fechaLimite,
            nivelEscalamientoId: $datos->nivelEscalamientoId,
        ));

        $this->eventos->dispatch(new CompromisoCreado(
            compromisoId: (int) $persistido->id,
            publicId: $persistido->publicId,
            proyectoId: $persistido->proyectoId,
            casoId: $persistido->casoId,
            gestionOrigenId: $persistido->gestionOrigenId,
            usuarioId: $persistido->usuarioId,
            tipo: TipoCompromiso::RESOLUCION_TICKET,
            fechaVencimiento: $persistido->fechaVencimiento,
            creadaEn: $persistido->creadaEn,
        ));
    }

    private function esProyectoCx(int $proyectoId): bool
    {
        return DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('tipo_operacion', 'cx')
            ->exists();
    }
}
