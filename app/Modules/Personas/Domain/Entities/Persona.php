<?php

declare(strict_types=1);

namespace App\Modules\Personas\Domain\Entities;

use App\Modules\Personas\Domain\Exceptions\DatosPersonaInvalidos;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;

final readonly class Persona
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public int $proyectoId,
        public TipoPersona $tipoPersona,
        public int $tipoIdentificacionId,
        public Identificacion $identificacion,
        public ?string $nombres,
        public ?string $apellidos,
        public ?string $razonSocial,
        public ?DateTimeImmutable $fechaNacimiento,
        public DateTimeImmutable $creadaEn,
    ) {}

    public static function registrar(
        string $publicId,
        int $proyectoId,
        TipoPersona $tipoPersona,
        int $tipoIdentificacionId,
        Identificacion $identificacion,
        ?string $nombres,
        ?string $apellidos,
        ?string $razonSocial,
        ?DateTimeImmutable $fechaNacimiento,
        DateTimeImmutable $creadaEn,
    ): self {
        if ($tipoPersona === TipoPersona::FISICA) {
            $nombres = self::normalizar($nombres);
            $apellidos = self::normalizar($apellidos);
            $razonSocial = null;
        } else {
            $razonSocial = self::normalizar($razonSocial);
            if ($razonSocial === null) {
                throw new DatosPersonaInvalidos('Una persona jurídica debe tener razón social.');
            }
            $nombres = null;
            $apellidos = null;
            $fechaNacimiento = null;
        }

        return new self(
            id: null,
            publicId: $publicId,
            proyectoId: $proyectoId,
            tipoPersona: $tipoPersona,
            tipoIdentificacionId: $tipoIdentificacionId,
            identificacion: $identificacion,
            nombres: $nombres,
            apellidos: $apellidos,
            razonSocial: $razonSocial,
            fechaNacimiento: $fechaNacimiento,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        string $publicId,
        int $proyectoId,
        TipoPersona $tipoPersona,
        int $tipoIdentificacionId,
        Identificacion $identificacion,
        ?string $nombres,
        ?string $apellidos,
        ?string $razonSocial,
        ?DateTimeImmutable $fechaNacimiento,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            publicId: $publicId,
            proyectoId: $proyectoId,
            tipoPersona: $tipoPersona,
            tipoIdentificacionId: $tipoIdentificacionId,
            identificacion: $identificacion,
            nombres: $nombres,
            apellidos: $apellidos,
            razonSocial: $razonSocial,
            fechaNacimiento: $fechaNacimiento,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            publicId: $this->publicId,
            proyectoId: $this->proyectoId,
            tipoPersona: $this->tipoPersona,
            tipoIdentificacionId: $this->tipoIdentificacionId,
            identificacion: $this->identificacion,
            nombres: $this->nombres,
            apellidos: $this->apellidos,
            razonSocial: $this->razonSocial,
            fechaNacimiento: $this->fechaNacimiento,
            creadaEn: $this->creadaEn,
        );
    }

    public function nombreCompleto(): string
    {
        if ($this->tipoPersona === TipoPersona::JURIDICA) {
            return (string) $this->razonSocial;
        }

        $completo = trim((string) $this->nombres.' '.(string) $this->apellidos);

        return $completo !== '' ? $completo : $this->identificacion->asString();
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }
}
