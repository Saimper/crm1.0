<?php

declare(strict_types=1);

namespace App\Modules\Venta\Application\Listeners;

use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Venta\Domain\Contracts\CompromisoCierreVentaRepository;
use App\Modules\Venta\Domain\Entities\CompromisoCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reacciona a `GestionRegistrada`: si el proyecto es tipo venta, el resultado exige compromiso
 * y se enviaron DatosCierreVenta, crea Compromiso + CompromisoCierreVenta en la misma transacción
 * y dispara CompromisoCreado.
 */
final readonly class CrearCierreDesdeGestion
{
    public function __construct(
        private CompromisoRepository $compromisoRepo,
        private CompromisoCierreVentaRepository $cierreRepo,
        private Dispatcher $eventos,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        if (! $evento->banderas->requiereCompromiso) {
            return;
        }
        if (! $evento->datosCompromiso instanceof DatosCierreVenta) {
            return;
        }
        if (! $this->esProyectoVenta($evento->proyectoId)) {
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
            tipo: TipoCompromiso::CIERRE_VENTA,
            fechaVencimiento: $datos->fechaEstimada->fecha,
            creadaEn: $ahora,
        );
        $persistido = $this->compromisoRepo->save($compromiso);

        $this->cierreRepo->save(CompromisoCierreVenta::registrar(
            compromisoId: (int) $persistido->id,
            proyectoId: $persistido->proyectoId,
            monto: $datos->monto,
            fechaEstimada: $datos->fechaEstimada,
            etapaEmbudoId: $datos->etapaEmbudoId,
        ));

        $this->eventos->dispatch(new CompromisoCreado(
            compromisoId: (int) $persistido->id,
            publicId: $persistido->publicId,
            proyectoId: $persistido->proyectoId,
            casoId: $persistido->casoId,
            gestionOrigenId: $persistido->gestionOrigenId,
            usuarioId: $persistido->usuarioId,
            tipo: TipoCompromiso::CIERRE_VENTA,
            fechaVencimiento: $persistido->fechaVencimiento,
            creadaEn: $persistido->creadaEn,
        ));
    }

    private function esProyectoVenta(int $proyectoId): bool
    {
        return DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('tipo_operacion', 'venta')
            ->exists();
    }
}
