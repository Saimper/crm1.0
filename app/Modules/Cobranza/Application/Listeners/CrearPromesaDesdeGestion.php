<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\Listeners;

use App\Modules\Cobranza\Domain\Contracts\CompromisoPromesaPagoRepository;
use App\Modules\Cobranza\Domain\Entities\CompromisoPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reacciona a `GestionRegistrada`: si el proyecto es tipo cobranza, el resultado exige
 * compromiso y se enviaron DatosPromesaPago, crea Compromiso (núcleo) + CompromisoPromesaPago (CTI)
 * en la misma transacción y dispara CompromisoCreado (→ Casos activa la bandera vigente).
 */
final readonly class CrearPromesaDesdeGestion
{
    public function __construct(
        private CompromisoRepository $compromisoRepo,
        private CompromisoPromesaPagoRepository $promesaRepo,
        private Dispatcher $eventos,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        if (! $evento->banderas->requiereCompromiso) {
            return;
        }
        if (! $evento->datosCompromiso instanceof DatosPromesaPago) {
            return;
        }
        if (! $this->esProyectoCobranza($evento->proyectoId)) {
            return;
        }

        $datos = $evento->datosCompromiso;
        $ahora = new DateTimeImmutable;

        // El VO traía esta regla desde el principio y no la invocaba nadie: se
        // podía registrar una promesa con vencimiento ya pasado, y la pantalla la
        // pintaba acto seguido como «compromiso vigente».
        //
        // Se valida contra la fecha DE LA GESTIÓN y no contra hoy: una promesa a
        // quince días tomada hace dos meses tiene el vencimiento en el pasado
        // visto desde ahora, y era perfectamente válida cuando se tomó. Con
        // `hoy` como referencia, cargar un histórico sería imposible.
        $datos->fechaVencimiento->validarNoPasada($evento->creadaEn);

        $compromiso = Compromiso::crear(
            publicId: (string) Str::ulid(),
            proyectoId: $evento->proyectoId,
            casoId: $evento->casoId,
            gestionOrigenId: $evento->gestionId,
            usuarioId: $evento->usuarioId,
            tipo: TipoCompromiso::PROMESA_PAGO,
            fechaVencimiento: $datos->fechaVencimiento->fecha,
            creadaEn: $ahora,
        );
        $persistido = $this->compromisoRepo->save($compromiso);

        $this->promesaRepo->save(CompromisoPromesaPago::registrar(
            compromisoId: (int) $persistido->id,
            proyectoId: $persistido->proyectoId,
            monto: $datos->monto,
            fechaVencimiento: $datos->fechaVencimiento,
            tipoPagoId: $datos->tipoPagoId,
        ));

        $this->eventos->dispatch(new CompromisoCreado(
            compromisoId: (int) $persistido->id,
            publicId: $persistido->publicId,
            proyectoId: $persistido->proyectoId,
            casoId: $persistido->casoId,
            gestionOrigenId: $persistido->gestionOrigenId,
            usuarioId: $persistido->usuarioId,
            tipo: TipoCompromiso::PROMESA_PAGO,
            fechaVencimiento: $persistido->fechaVencimiento,
            creadaEn: $persistido->creadaEn,
        ));
    }

    private function esProyectoCobranza(int $proyectoId): bool
    {
        return DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('tipo_operacion', 'cobranza')
            ->exists();
    }
}
