<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Application\Services;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Los cinco filtros de pantalla de la auditoría, en un solo sitio.
 *
 * Vivían copiados tres veces —el listado Livewire y los dos controllers de
 * exportación— y cada copia leía la query string a su manera. Tres copias del
 * mismo filtro son tres sitios donde lo que se VE y lo que se DESCARGA pueden
 * dejar de coincidir, que es justo lo que ya pasó una vez con el recorte de
 * tenant (ver AlcanceAuditoria).
 *
 * Sólo estrechan: el recorte de tenant se aplica ANTES y no depende de nada de
 * lo que haya aquí. Por eso un valor inválido no es un error, es «sin filtro».
 */
final readonly class FiltrosAuditoria
{
    public function __construct(
        public string $entidadTipo = '',
        public ?int $usuarioId = null,
        public string $evento = '',
        public string $desde = '',
        public string $hasta = '',
    ) {}

    /**
     * Desde la query string de una descarga. `?usuario_id[]=1` o
     * `?entidad_tipo[]=x` llegan como array y se tratan como «sin filtro»
     * (ParametroDeConsulta); una fecha que no es `Y-m-d` de calendario,
     * también.
     */
    public static function desdeQueryString(Request $request): self
    {
        return new self(
            entidadTipo: ParametroDeConsulta::texto($request, 'entidad_tipo'),
            usuarioId: ParametroDeConsulta::entero($request, 'usuario_id'),
            evento: ParametroDeConsulta::texto($request, 'evento'),
            desde: ParametroDeConsulta::fecha($request, 'desde') ?? '',
            hasta: ParametroDeConsulta::fecha($request, 'hasta') ?? '',
        );
    }

    /** @param  string  $alias  Alias de `auditorias` en esa consulta. */
    public function aplicar(Builder $q, string $alias): void
    {
        if ($this->entidadTipo !== '') {
            $q->where($alias.'.entidad_tipo', $this->entidadTipo);
        }
        if ($this->usuarioId !== null) {
            $q->where($alias.'.usuario_id', $this->usuarioId);
        }
        if ($this->evento !== '') {
            $q->where($alias.'.evento', $this->evento);
        }
        if ($this->desde !== '') {
            $q->where($alias.'.creada_en', '>=', $this->desde.' 00:00:00');
        }
        if ($this->hasta !== '') {
            $q->where($alias.'.creada_en', '<=', $this->hasta.' 23:59:59');
        }
    }

    /**
     * Lo que se aplicó, para la huella de la exportación. Sólo los filtros con
     * valor: una huella que diga «desde: ''» no cuenta nada.
     *
     * @return array<string, int|string>
     */
    public function aplicados(): array
    {
        return array_filter([
            'entidad_tipo' => $this->entidadTipo,
            'usuario_id' => $this->usuarioId,
            'evento' => $this->evento,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ], static fn (int|string|null $v): bool => $v !== '' && $v !== null);
    }
}
