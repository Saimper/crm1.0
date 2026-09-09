<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\DTOs;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;

/**
 * Los filtros del listado de personas, con los mismos campos que las
 * propiedades `#[Url]` del componente.
 *
 * Existen como objeto para que la pantalla y la exportación no puedan
 * divergir: las dos construyen esto y se lo pasan al mismo servicio. Antes la
 * descarga no admitía filtros, así que el supervisor que había acotado el
 * listado a un tipo se llevaba el padrón entero.
 */
final readonly class FiltrosListadoPersonas
{
    private const TIPOS = ['fisica', 'juridica'];

    private function __construct(
        public string $busqueda,
        public string $tipoPersona,
    ) {}

    /** Desde las propiedades del componente. Un tipo fuera de la lista cuenta como «todos». */
    public static function desde(string $busqueda, string $tipoPersona): self
    {
        return new self(
            trim($busqueda),
            in_array($tipoPersona, self::TIPOS, true) ? $tipoPersona : '',
        );
    }

    /** Desde la query string, con los nombres de la URL (`q`, `tipo`). */
    public static function desdeRequest(Request $request): self
    {
        return self::desde(
            ParametroDeConsulta::texto($request, 'q'),
            ParametroDeConsulta::texto($request, 'tipo'),
        );
    }

    /**
     * Sólo lo que está puesto, con los nombres de la URL. Sirve para el enlace
     * de exportar y para la huella de auditoría: nunca lleva datos, sólo el
     * recorte con el que se pidió.
     *
     * @return array<string, string>
     */
    public function comoParametros(): array
    {
        return array_filter(
            ['q' => $this->busqueda, 'tipo' => $this->tipoPersona],
            static fn (string $v): bool => $v !== '',
        );
    }

    public function estaVacio(): bool
    {
        return $this->comoParametros() === [];
    }
}
