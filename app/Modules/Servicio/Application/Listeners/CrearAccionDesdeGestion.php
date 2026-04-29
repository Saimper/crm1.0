<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Application\Listeners;

use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Servicio\Domain\Contracts\CompromisoAccionServicioRepository;
use App\Modules\Servicio\Domain\Entities\CompromisoAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reacciona a `GestionRegistrada`: si el proyecto es tipo servicio, el resultado exige compromiso
 * y se enviaron DatosAccionServicio, crea Compromiso + CompromisoAccionServicio en misma transacción
 * y dispara CompromisoCreado.
 */
final readonly class CrearAccionDesdeGestion
{
    public function __construct(
        private CompromisoRepository $compromisoRepo,
        private CompromisoAccionServicioRepository $accionRepo,
        private Dispatcher $eventos,
    ) {
    }

    public function handle(GestionRegistrada $evento): void
    {
        if (! $evento->banderas->requiereCompromiso) {
            return;
        }
        if (! $evento->datosCompromiso instanceof DatosAccionServicio) {
            return;
        }
        if (! $this->esProyectoServicio($evento->proyectoId)) {
            return;
        }

        $datos = $evento->datosCompromiso;
        $ahora = new DateTimeImmutable();

        $compromiso = Compromiso::crear(
            publicId:         (string) Str::ulid(),
            proyectoId:       $evento->proyectoId,
            casoId:           $evento->casoId,
            gestionOrigenId:  $evento->gestionId,
            usuarioId:        $evento->usuarioId,
            tipo:             TipoCompromiso::ACCION_SERVICIO,
            fechaVencimiento: $datos->fechaProgramada->fecha,
            creadaEn:         $ahora,
        );
        $persistido = $this->compromisoRepo->save($compromiso);

        $this->accionRepo->save(CompromisoAccionServicio::registrar(
            compromisoId:         (int) $persistido->id,
            proyectoId:           $persistido->proyectoId,
            descripcion:          $datos->descripcion,
            fechaProgramada:      $datos->fechaProgramada,
            tipoAccionServicioId: $datos->tipoAccionServicioId,
            tecnicoAsignado:      $datos->tecnicoAsignado,
        ));

        $this->eventos->dispatch(new CompromisoCreado(
            compromisoId:    (int) $persistido->id,
            publicId:        $persistido->publicId,
            proyectoId:      $persistido->proyectoId,
            casoId:          $persistido->casoId,
            gestionOrigenId: $persistido->gestionOrigenId,
            usuarioId:       $persistido->usuarioId,
            tipo:            TipoCompromiso::ACCION_SERVICIO,
            fechaVencimiento: $persistido->fechaVencimiento,
            creadaEn:        $persistido->creadaEn,
        ));
    }

    private function esProyectoServicio(int $proyectoId): bool
    {
        return DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('tipo_operacion', 'servicio')
            ->exists();
    }
}
