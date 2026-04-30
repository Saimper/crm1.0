<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\UseCases;

use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaOutput;
use App\Modules\Cobranza\Domain\Contracts\CasoCobranzaRepository;
use App\Modules\Cobranza\Domain\Contracts\TramoMoraRepository;
use App\Modules\Cobranza\Domain\Entities\CasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\NumeroPrestamo;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Crea un Caso base + CasoCobranza (CTI) en la misma transacción.
 * Dispara CasoCreado para que los listeners del núcleo (asignaciones, etc.) reaccionen.
 */
final readonly class RegistrarCasoCobranza
{
    public function __construct(
        private CasoRepository $casoRepo,
        private CasoCobranzaRepository $cobranzaRepo,
        private TramoMoraRepository $tramosRepo,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(RegistrarCasoCobranzaInput $input): RegistrarCasoCobranzaOutput
    {
        if ($this->cobranzaRepo->existeNumeroPrestamoEnProyecto($input->proyectoId, $input->numeroPrestamo)) {
            throw new NumeroPrestamoYaRegistrado(
                "Número de préstamo '{$input->numeroPrestamo}' ya registrado en el proyecto {$input->proyectoId}."
            );
        }

        $ahora = new DateTimeImmutable;

        return $this->db->transaction(function () use ($input, $ahora): RegistrarCasoCobranzaOutput {
            $caso = Caso::registrar(
                publicId: (string) Str::ulid(),
                proyectoId: $input->proyectoId,
                carteraId: $input->carteraId,
                personaId: $input->personaId,
                tipoCaso: TipoCaso::COBRANZA,
                estadoCasoId: $input->estadoCasoId,
                fechaIngreso: $input->fechaIngreso,
                prioridad: $input->prioridad,
                creadaEn: $ahora,
            );
            $caso = $this->casoRepo->save($caso);
            $casoId = (int) $caso->id;

            $tramoMoraId = $this->tramosRepo->resolverPorDiasMora($input->proyectoId, $input->diasMora);

            $cobranza = CasoCobranza::registrar(
                casoId: $casoId,
                proyectoId: $input->proyectoId,
                numeroPrestamo: new NumeroPrestamo($input->numeroPrestamo),
                montoOriginal: new MontoCobranza($input->montoOriginal, $input->moneda),
                saldoCapital: new MontoCobranza($input->saldoCapital, $input->moneda),
                saldoInteres: new MontoCobranza($input->saldoInteres, $input->moneda),
                saldoTotal: new MontoCobranza($input->saldoTotal, $input->moneda),
                cuotaMensual: new MontoCobranza($input->cuotaMensual, $input->moneda),
                cuotasTotales: $input->cuotasTotales,
                cuotasPagadas: $input->cuotasPagadas,
                diasMora: new DiasMora($input->diasMora),
                tramoMoraId: $tramoMoraId,
                fechaDesembolso: $input->fechaDesembolso,
                fechaVencimiento: $input->fechaVencimiento,
            );
            $this->cobranzaRepo->save($cobranza);

            $this->eventos->dispatch(new CasoCreado(
                casoId: $casoId,
                publicId: $caso->publicId,
                proyectoId: $caso->proyectoId,
                carteraId: $caso->carteraId,
                personaId: $caso->personaId,
                tipoCaso: TipoCaso::COBRANZA,
                creadaEn: $ahora,
            ));

            return new RegistrarCasoCobranzaOutput(
                casoId: $casoId,
                publicId: $caso->publicId,
            );
        });
    }
}
