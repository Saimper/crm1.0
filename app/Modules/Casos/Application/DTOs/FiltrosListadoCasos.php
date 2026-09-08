<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\DTOs;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;

/**
 * Los filtros del listado de casos, con los mismos campos que las propiedades
 * `#[Url]` del componente (`q`, `cartera`, `estado`).
 *
 * La pantalla y la exportación construyen esto y se lo pasan al mismo
 * servicio, así que no pueden divergir. Los ids llegan como texto desde la
 * URL y sólo cuentan si son dígitos: cualquier otra cosa es «sin filtro».
 */
final readonly class FiltrosListadoCasos
{
    private function __construct(
        public string $busqueda,
        public ?int $carteraId,
        public ?int $estadoCasoId,
    ) {}

    /** Desde las propiedades del componente, que son cadenas. */
    public static function desde(string $busqueda, string $cartera, string $estado): self
    {
        return new self(trim($busqueda), self::id($cartera), self::id($estado));
    }

    public static function desdeRequest(Request $request): self
    {
        return new self(
            ParametroDeConsulta::texto($request, 'q'),
            ParametroDeConsulta::entero($request, 'cartera'),
            ParametroDeConsulta::entero($request, 'estado'),
        );
    }

    /**
     * Sólo lo que está puesto, con los nombres de la URL: para el enlace de
     * exportar y para la huella de auditoría.
     *
     * @return array<string, string>
     */
    public function comoParametros(): array
    {
        return array_filter([
            'q' => $this->busqueda,
            'cartera' => $this->carteraId === null ? '' : (string) $this->carteraId,
            'estado' => $this->estadoCasoId === null ? '' : (string) $this->estadoCasoId,
        ], static fn (string $v): bool => $v !== '');
    }

    private static function id(string $texto): ?int
    {
        $texto = trim($texto);

        return $texto !== '' && ctype_digit($texto) ? (int) $texto : null;
    }
}
