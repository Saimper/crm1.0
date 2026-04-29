<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\Entities;

use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use DateTimeImmutable;

final readonly class Contacto
{
    private function __construct(
        public ?int $id,
        public int $proyectoId,
        public int $personaId,
        public TipoContacto $tipo,
        public string $valor,
        public ?string $etiqueta,
        public bool $esPrincipal,
        public bool $activo,
        public DateTimeImmutable $creadaEn,
    ) {
    }

    public static function registrar(
        int $proyectoId,
        int $personaId,
        TipoContacto $tipo,
        string $valor,
        ?string $etiqueta,
        bool $esPrincipal,
        DateTimeImmutable $creadaEn,
    ): self {
        $valorNormalizado = self::normalizarYValidar($tipo, $valor);
        $etiquetaNormalizada = self::normalizarEtiqueta($etiqueta);

        return new self(
            id: null,
            proyectoId: $proyectoId,
            personaId: $personaId,
            tipo: $tipo,
            valor: $valorNormalizado,
            etiqueta: $etiquetaNormalizada,
            esPrincipal: $esPrincipal,
            activo: true,
            creadaEn: $creadaEn,
        );
    }

    public static function reconstituir(
        int $id,
        int $proyectoId,
        int $personaId,
        TipoContacto $tipo,
        string $valor,
        ?string $etiqueta,
        bool $esPrincipal,
        bool $activo,
        DateTimeImmutable $creadaEn,
    ): self {
        return new self(
            id: $id,
            proyectoId: $proyectoId,
            personaId: $personaId,
            tipo: $tipo,
            valor: $valor,
            etiqueta: $etiqueta,
            esPrincipal: $esPrincipal,
            activo: $activo,
            creadaEn: $creadaEn,
        );
    }

    public function conId(int $id): self
    {
        return new self(
            id: $id,
            proyectoId: $this->proyectoId,
            personaId: $this->personaId,
            tipo: $this->tipo,
            valor: $this->valor,
            etiqueta: $this->etiqueta,
            esPrincipal: $this->esPrincipal,
            activo: $this->activo,
            creadaEn: $this->creadaEn,
        );
    }

    private static function normalizarYValidar(TipoContacto $tipo, string $valor): string
    {
        $limpio = trim($valor);
        if ($limpio === '') {
            throw new DatosContactoInvalidos('El valor del contacto no puede estar vacío.');
        }

        match ($tipo) {
            TipoContacto::TELEFONO  => self::validarTelefono($limpio),
            TipoContacto::CORREO    => self::validarCorreo($limpio),
            TipoContacto::DIRECCION => self::validarDireccion($limpio),
        };

        return $limpio;
    }

    private static function validarTelefono(string $valor): void
    {
        if (preg_match('/^[\d\s()+\-\.]{7,25}$/', $valor) !== 1) {
            throw new DatosContactoInvalidos("Teléfono inválido: {$valor}");
        }
    }

    private static function validarCorreo(string $valor): void
    {
        if (filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            throw new DatosContactoInvalidos("Correo inválido: {$valor}");
        }
        if (mb_strlen($valor) > 250) {
            throw new DatosContactoInvalidos('Correo excede la longitud máxima.');
        }
    }

    private static function validarDireccion(string $valor): void
    {
        $len = mb_strlen($valor);
        if ($len < 5 || $len > 250) {
            throw new DatosContactoInvalidos("Dirección debe tener entre 5 y 250 caracteres. Recibido: {$len}.");
        }
    }

    private static function normalizarEtiqueta(?string $etiqueta): ?string
    {
        if ($etiqueta === null) {
            return null;
        }
        $limpia = trim($etiqueta);

        return $limpia === '' ? null : mb_substr($limpia, 0, 100);
    }
}
