<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\DTOs;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;

/**
 * Con qué recorte se pide la exportación de gestiones.
 *
 * Dos formas excluyentes de acotar el tiempo: un rango con nombre —el mismo
 * selector de Reportes operativos— o dos fechas de calendario. Si viene
 * cualquiera de las dos fechas, manda la ventana; `desde`/`hasta` se guardan
 * tal cual llegan y los valida el dominio (`VentanaDeExportacion`), que es
 * quien sabe cuándo una ventana es admisible.
 *
 * `usuario_id` es opcional y sólo se aplica si ese usuario tiene rol activo
 * en el proyecto; lo decide el exportador, no este objeto.
 */
final readonly class FiltrosExportacionGestiones
{
    private const RANGOS = ['hoy', 'ayer', 'semana', 'mes'];

    private function __construct(
        public string $rango,
        public string $desde,
        public string $hasta,
        public ?int $usuarioId,
    ) {}

    public static function desdeRequest(Request $request): self
    {
        $rango = ParametroDeConsulta::texto($request, 'rango');

        return new self(
            in_array($rango, self::RANGOS, true) ? $rango : 'hoy',
            ParametroDeConsulta::texto($request, 'desde'),
            ParametroDeConsulta::texto($request, 'hasta'),
            ParametroDeConsulta::entero($request, 'usuario_id'),
        );
    }

    public function usaVentana(): bool
    {
        return $this->desde !== '' || $this->hasta !== '';
    }

    /**
     * El recorte tal como se aplicó, para la huella de auditoría.
     *
     * @return array<string, string>
     */
    public function comoParametros(): array
    {
        $parametros = $this->usaVentana()
            ? ['desde' => $this->desde, 'hasta' => $this->hasta]
            : ['rango' => $this->rango];

        if ($this->usuarioId !== null) {
            $parametros['usuario_id'] = (string) $this->usuarioId;
        }

        return $parametros;
    }
}
