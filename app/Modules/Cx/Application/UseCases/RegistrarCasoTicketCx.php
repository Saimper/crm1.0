<?php

declare(strict_types=1);

namespace App\Modules\Cx\Application\UseCases;

use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxOutput;
use App\Modules\Cx\Domain\Contracts\CasoTicketCxRepository;
use App\Modules\Cx\Domain\Entities\CasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use App\Modules\Cx\Domain\ValueObjects\AsuntoTicket;
use App\Modules\Cx\Domain\ValueObjects\CodigoTicket;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Crea un Caso base + CasoTicketCx (CTI) en la misma transacción.
 * Dispara CasoCreado para que los listeners del núcleo reaccionen.
 */
final readonly class RegistrarCasoTicketCx
{
    public function __construct(
        private CasoRepository $casoRepo,
        private CasoTicketCxRepository $cxRepo,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(RegistrarCasoTicketCxInput $input): RegistrarCasoTicketCxOutput
    {
        if ($this->cxRepo->existeCodigoEnProyecto($input->proyectoId, $input->codigoTicket)) {
            throw new CodigoTicketYaRegistrado(
                "Código de ticket '{$input->codigoTicket}' ya registrado en el proyecto {$input->proyectoId}."
            );
        }

        $ahora = new DateTimeImmutable;

        return $this->db->transaction(function () use ($input, $ahora): RegistrarCasoTicketCxOutput {
            $caso = Caso::registrar(
                publicId: (string) Str::ulid(),
                proyectoId: $input->proyectoId,
                carteraId: $input->carteraId,
                personaId: $input->personaId,
                tipoCaso: TipoCaso::TICKET_CX,
                estadoCasoId: $input->estadoCasoId,
                fechaIngreso: $input->fechaIngreso,
                prioridad: $input->prioridad,
                creadaEn: $ahora,
            );
            $caso = $this->casoRepo->save($caso);
            $casoId = (int) $caso->id;

            $ticket = CasoTicketCx::registrar(
                casoId: $casoId,
                proyectoId: $input->proyectoId,
                codigoTicket: new CodigoTicket($input->codigoTicket),
                asunto: $input->asunto !== null && $input->asunto !== '' ? new AsuntoTicket($input->asunto) : null,
                descripcion: $input->descripcion,
                categoriaTicketId: $input->categoriaTicketId,
                prioridadTicketId: $input->prioridadTicketId,
                nivelSlaId: $input->nivelSlaId,
                nivelEscalamientoId: $input->nivelEscalamientoId,
                fechaReporte: $input->fechaReporte,
                fechaLimiteSla: $input->fechaLimiteSla,
            );
            $this->cxRepo->save($ticket);

            $this->eventos->dispatch(new CasoCreado(
                casoId: $casoId,
                publicId: $caso->publicId,
                proyectoId: $caso->proyectoId,
                carteraId: $caso->carteraId,
                personaId: $caso->personaId,
                tipoCaso: TipoCaso::TICKET_CX,
                creadaEn: $ahora,
            ));

            return new RegistrarCasoTicketCxOutput(
                casoId: $casoId,
                publicId: $caso->publicId,
            );
        });
    }
}
