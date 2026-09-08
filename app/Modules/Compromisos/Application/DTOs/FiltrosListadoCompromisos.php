<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\DTOs;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;

/**
 * Los filtros del listado de compromisos, con los mismos campos que las
 * propiedades `#[Url]` del componente (`estado`, `venc`, `tipo`).
 *
 * Las tres son listas cerradas: un valor fuera de ellas cuenta como «sin
 * filtro», nunca llega a la consulta. Pantalla y exportación construyen esto
 * y se lo pasan al mismo servicio para que no puedan divergir.
 */
final readonly class FiltrosListadoCompromisos
{
    private const ESTADOS = ['pendiente', 'cumplido', 'roto', 'cancelado'];

    private const VENCIMIENTOS = ['vigentes', 'vencidos', 'proximos7d'];

    private const TIPOS = ['promesa_pago', 'resolucion_ticket', 'cierre_venta', 'accion_servicio'];

    private function __construct(
        public string $estado,
        public string $vencimiento,
        public string $tipoCompromiso,
    ) {}

    public static function desde(string $estado, string $vencimiento, string $tipoCompromiso): self
    {
        return new self(
            in_array($estado, self::ESTADOS, true) ? $estado : '',
            in_array($vencimiento, self::VENCIMIENTOS, true) ? $vencimiento : '',
            in_array($tipoCompromiso, self::TIPOS, true) ? $tipoCompromiso : '',
        );
    }

    public static function desdeRequest(Request $request): self
    {
        return self::desde(
            ParametroDeConsulta::texto($request, 'estado'),
            ParametroDeConsulta::texto($request, 'venc'),
            ParametroDeConsulta::texto($request, 'tipo'),
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
            'estado' => $this->estado,
            'venc' => $this->vencimiento,
            'tipo' => $this->tipoCompromiso,
        ], static fn (string $v): bool => $v !== '');
    }
}
