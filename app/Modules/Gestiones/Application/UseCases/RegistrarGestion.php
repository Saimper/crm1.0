<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\UseCases;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionOutput;
use App\Modules\Gestiones\Domain\Contracts\ConsultaResultado;
use App\Modules\Gestiones\Domain\Contracts\GestionRepository;
use App\Modules\Gestiones\Domain\Entities\Gestion;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Gestiones\Domain\Exceptions\PromesaRequerida;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarGestion
{
    public function __construct(
        private GestionRepository $repositorio,
        private ConsultaResultado $consulta,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(RegistrarGestionInput $input): RegistrarGestionOutput
    {
        $banderas = $this->consulta->banderas($input->resultadoId);

        if ($banderas->requiereCompromiso && $input->datosCompromiso === null) {
            throw new PromesaRequerida(
                "El resultado {$input->resultadoId} exige datos de compromiso y no se recibieron."
            );
        }

        $gestion = Gestion::registrar(
            publicId: $input->publicId,
            proyectoId: $input->proyectoId,
            casoId: $input->casoId,
            personaId: $input->personaId,
            contactoId: $input->contactoId,
            canalId: $input->canalId,
            tipoGestionId: $input->tipoGestionId,
            resultadoId: $input->resultadoId,
            motivoNoContactoId: $input->motivoNoContactoId,
            causaId: $input->causaId,
            usuarioId: $input->usuarioId,
            notas: $input->notas,
            duracion: $input->duracion,
            banderas: $banderas,
            creadaEn: $input->creadaEn,
        );

        $persistida = $this->db->transaction(function () use ($gestion, $input): Gestion {
            $guardada = $this->repositorio->save($gestion);

            $this->eventos->dispatch(new GestionRegistrada(
                gestionId: (int) $guardada->id,
                publicId: $guardada->publicId,
                proyectoId: $guardada->proyectoId,
                casoId: $guardada->casoId,
                personaId: $guardada->personaId,
                usuarioId: $guardada->usuarioId,
                resultadoId: $guardada->resultadoId,
                tipoGestionId: $guardada->tipoGestionId,
                canalId: $guardada->canalId,
                banderas: $guardada->banderas,
                creadaEn: $guardada->creadaEn,
                datosCompromiso: $input->datosCompromiso,
            ));

            return $guardada;
        });

        return new RegistrarGestionOutput(
            id: (int) $persistida->id,
            publicId: $persistida->publicId,
            creadaEn: $persistida->creadaEn,
        );
    }
}
