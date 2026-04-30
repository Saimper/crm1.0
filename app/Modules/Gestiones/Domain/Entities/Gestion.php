<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Entities;

use App\Modules\Gestiones\Domain\Exceptions\CausaRequerida;
use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;

final readonly class Gestion
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public int $casoId,
        public int $personaId,
        public ?int $contactoId,
        public int $canalId,
        public int $tipoGestionId,
        public int $resultadoId,
        public ?int $motivoNoContactoId,
        public ?int $causaId,
        public int $usuarioId,
        public ?string $notas,
        public ?DuracionSegundos $duracion,
        public BanderasResultado $banderas,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        int $proyectoId,
        int $casoId,
        int $personaId,
        ?int $contactoId,
        int $canalId,
        int $tipoGestionId,
        int $resultadoId,
        ?int $motivoNoContactoId,
        ?int $causaId,
        int $usuarioId,
        ?string $notas,
        ?DuracionSegundos $duracion,
        BanderasResultado $banderas,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($banderas->requiereCausa && $causaId === null) {
            throw new CausaRequerida('El resultado exige indicar la causa.');
        }

        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            casoId: $casoId,
            personaId: $personaId,
            contactoId: $contactoId,
            canalId: $canalId,
            tipoGestionId: $tipoGestionId,
            resultadoId: $resultadoId,
            motivoNoContactoId: $motivoNoContactoId,
            causaId: $causaId,
            usuarioId: $usuarioId,
            notas: $notas,
            duracion: $duracion,
            banderas: $banderas,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            casoId: $this->casoId,
            personaId: $this->personaId,
            contactoId: $this->contactoId,
            canalId: $this->canalId,
            tipoGestionId: $this->tipoGestionId,
            resultadoId: $this->resultadoId,
            motivoNoContactoId: $this->motivoNoContactoId,
            causaId: $this->causaId,
            usuarioId: $this->usuarioId,
            notas: $this->notas,
            duracion: $this->duracion,
            banderas: $this->banderas,
            creadaEn: $this->creadaEn,
        );
    }
}
