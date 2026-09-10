<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\UseCases;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionOutput;
use App\Modules\Gestiones\Domain\Contracts\ConsultaCompatibilidadResultado;
use App\Modules\Gestiones\Domain\Contracts\ConsultaResultado;
use App\Modules\Gestiones\Domain\Contracts\ConsultaTiposPorCanal;
use App\Modules\Gestiones\Domain\Contracts\GestionRepository;
use App\Modules\Gestiones\Domain\Entities\Gestion;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Gestiones\Domain\Exceptions\PromesaRequerida;
use App\Modules\Gestiones\Domain\Exceptions\ResultadoNoAdmitidoPorTipo;
use App\Support\Database\CarterasOperativas;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarGestion
{
    public function __construct(
        private GestionRepository $repositorio,
        private ConsultaResultado $consulta,
        private ConsultaCompatibilidadResultado $compatibilidad,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
        private ConsultaTiposPorCanal $tiposPorCanal,
    ) {}

    public function execute(RegistrarGestionInput $input): RegistrarGestionOutput
    {
        // La compatibilidad tipo → resultado se comprueba aquí y no sólo al
        // pintar el `<select>`: el id viaja en una propiedad pública de Livewire
        // y un payload manipulado lo elige (§11, segunda capa).
        if (! $this->compatibilidad->admite($input->proyectoId, $input->tipoGestionId, $input->resultadoId)) {
            throw ResultadoNoAdmitidoPorTipo::para($input->tipoGestionId, $input->resultadoId);
        }

        if (! in_array($input->tipoGestionId, $this->tiposPorCanal->idsAdmitidos($input->proyectoId, $input->canalId), true)) {
            throw new DomainException('El tipo de gestión no está disponible para este canal.');
        }
        if (! CarterasOperativas::casos($this->db, $input->proyectoId)
            ->where('c.id', $input->casoId)->where('c.persona_id', $input->personaId)->exists()) {
            throw new DomainException('La cuenta ya no está disponible para gestionar.');
        }
        if (! $this->db->table('resultados')->where('proyecto_id', $input->proyectoId)
            ->where('id', $input->resultadoId)->where('activo', true)->exists()) {
            throw new DomainException('Selecciona un resultado activo de este proyecto.');
        }
        $banderas = $this->consulta->banderas($input->resultadoId);
        if ($input->motivoNoContactoId !== null && (! $banderas->esNoContactado
            || ! $this->db->table('motivos_no_contacto')->where('proyecto_id', $input->proyectoId)
                ->where('id', $input->motivoNoContactoId)->where('activo', true)->exists())) {
            throw new DomainException('El motivo de no contacto no está habilitado para este resultado o proyecto.');
        }

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
