<?php

declare(strict_types=1);

namespace App\Modules\Venta\Application\UseCases;

use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaOutput;
use App\Modules\Venta\Domain\Contracts\CasoLeadVentaRepository;
use App\Modules\Venta\Domain\Entities\CasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\CodigoLeadYaRegistrado;
use App\Modules\Venta\Domain\ValueObjects\CodigoLead;
use App\Modules\Venta\Domain\ValueObjects\ValorEstimadoVenta;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Crea un Caso base + CasoLeadVenta (CTI) en la misma transacción.
 */
final readonly class RegistrarCasoLeadVenta
{
    public function __construct(
        private CasoRepository $casoRepo,
        private CasoLeadVentaRepository $ventaRepo,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(RegistrarCasoLeadVentaInput $input): RegistrarCasoLeadVentaOutput
    {
        if ($this->ventaRepo->existeCodigoEnProyecto($input->proyectoId, $input->codigoLead)) {
            throw new CodigoLeadYaRegistrado(
                "Código de lead '{$input->codigoLead}' ya registrado en el proyecto {$input->proyectoId}."
            );
        }

        $ahora = new DateTimeImmutable();

        return $this->db->transaction(function () use ($input, $ahora): RegistrarCasoLeadVentaOutput {
            $caso = Caso::registrar(
                publicId:     (string) Str::ulid(),
                proyectoId:   $input->proyectoId,
                carteraId:    $input->carteraId,
                personaId:    $input->personaId,
                tipoCaso:     TipoCaso::LEAD_VENTA,
                estadoCasoId: $input->estadoCasoId,
                fechaIngreso: $input->fechaIngreso,
                prioridad:    $input->prioridad,
                creadaEn:     $ahora,
            );
            $caso = $this->casoRepo->save($caso);
            $casoId = (int) $caso->id;

            $lead = CasoLeadVenta::registrar(
                casoId:              $casoId,
                proyectoId:          $input->proyectoId,
                codigoLead:          new CodigoLead($input->codigoLead),
                productoVentaId:     $input->productoVentaId,
                etapaEmbudoId:       $input->etapaEmbudoId,
                valorEstimado:       new ValorEstimadoVenta($input->valorEstimadoMonto, $input->moneda),
                origenLead:          $input->origenLead,
                fechaPrimerContacto: $input->fechaPrimerContacto,
                fechaEstimadaCierre: $input->fechaEstimadaCierre,
            );
            $this->ventaRepo->save($lead);

            $this->eventos->dispatch(new CasoCreado(
                casoId:     $casoId,
                publicId:   $caso->publicId,
                proyectoId: $caso->proyectoId,
                carteraId:  $caso->carteraId,
                personaId:  $caso->personaId,
                tipoCaso:   TipoCaso::LEAD_VENTA,
                creadaEn:   $ahora,
            ));

            return new RegistrarCasoLeadVentaOutput(
                casoId:   $casoId,
                publicId: $caso->publicId,
            );
        });
    }
}
