<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Application\UseCases;

use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Events\CasoCreado;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioOutput;
use App\Modules\Servicio\Domain\Contracts\CasoServicioRepository;
use App\Modules\Servicio\Domain\Entities\CasoServicio;
use App\Modules\Servicio\Domain\Exceptions\CodigoServicioYaRegistrado;
use App\Modules\Servicio\Domain\ValueObjects\CodigoServicio;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

readonly class RegistrarCasoServicio
{
    public function __construct(
        private CasoRepository $casoRepo,
        private CasoServicioRepository $servicioRepo,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(RegistrarCasoServicioInput $input): RegistrarCasoServicioOutput
    {
        if ($this->servicioRepo->existeCodigoEnProyecto($input->proyectoId, $input->codigoServicio)) {
            throw new CodigoServicioYaRegistrado(
                "Código de servicio '{$input->codigoServicio}' ya registrado en el proyecto {$input->proyectoId}."
            );
        }

        $ahora = new DateTimeImmutable;

        return $this->db->transaction(function () use ($input, $ahora): RegistrarCasoServicioOutput {
            $caso = Caso::registrar(
                publicId: (string) Str::ulid(),
                proyectoId: $input->proyectoId,
                carteraId: $input->carteraId,
                personaId: $input->personaId,
                tipoCaso: TipoCaso::SERVICIO,
                estadoCasoId: $input->estadoCasoId,
                fechaIngreso: $input->fechaIngreso,
                prioridad: $input->prioridad,
                creadaEn: $ahora,
            );
            $caso = $this->casoRepo->save($caso);
            $casoId = (int) $caso->id;

            $servicio = CasoServicio::registrar(
                casoId: $casoId,
                proyectoId: $input->proyectoId,
                codigoServicio: new CodigoServicio($input->codigoServicio),
                tipoAccionServicioId: $input->tipoAccionServicioId,
                estadoTecnicoId: $input->estadoTecnicoId,
                direccionServicio: $input->direccionServicio,
                tecnicoAsignado: $input->tecnicoAsignado,
                fechaSolicitud: $input->fechaSolicitud,
                fechaProgramada: $input->fechaProgramada,
            );
            $this->servicioRepo->save($servicio);

            $this->eventos->dispatch(new CasoCreado(
                casoId: $casoId,
                publicId: $caso->publicId,
                proyectoId: $caso->proyectoId,
                carteraId: $caso->carteraId,
                personaId: $caso->personaId,
                tipoCaso: TipoCaso::SERVICIO,
                creadaEn: $ahora,
            ));

            return new RegistrarCasoServicioOutput(
                casoId: $casoId,
                publicId: $caso->publicId,
            );
        });
    }
}
