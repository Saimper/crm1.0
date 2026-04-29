<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Domain\Entities;

use App\Modules\Clientes\Domain\Exceptions\DatosClienteInvalidos;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;

final readonly class Cliente
{
    private function __construct(
        public ?int $id,
        public string $publicId,
        public TipoPersona $tipoPersona,
        public int $tipoIdentificacionId,
        public Identificacion $identificacion,
        public ?string $nombres,
        public ?string $apellidos,
        public ?string $razonSocial,
        public ?DateTimeImmutable $fechaNacimiento,
        public DateTimeImmutable $creadaEn,
    ) {
    }

    public static function registrar(
        string $publicId,
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
            if ($nombres === null) {
                throw new DatosClienteInvalidos('Una persona física debe tener nombres.');
            }
            $razonSocial = null;
            $apellidos = self::normalizar($apellidos);
        } else {
            $razonSocial = self::normalizar($razonSocial);
            if ($razonSocial === null) {
                throw new DatosClienteInvalidos('Una persona jurídica debe tener razón social.');
            }
            $nombres = null;
            $apellidos = null;
            $fechaNacimiento = null;
        }

        return new self(
            id: null,
            publicId: $publicId,
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
        return $this->tipoPersona === TipoPersona::JURIDICA
            ? (string) $this->razonSocial
            : trim((string) $this->nombres.' '.(string) $this->apellidos);
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
