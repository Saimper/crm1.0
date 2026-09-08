<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\Services;

use App\Modules\Compromisos\Application\DTOs\FiltrosListadoCompromisos;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * La consulta del listado de compromisos, compartida por la pantalla y el CSV.
 *
 * El «hoy» contra el que se decide qué está vencido entra como parámetro y es
 * el del cliente (`RelojDelMandante::hoy()`), no `Carbon::today()`: la fecha de
 * vencimiento es una `date` del calendario de quien opera, y con el servidor
 * en UTC a las 20:00 en Panamá ya era «mañana», así que «vencidos» incluía lo
 * que vencía al día siguiente.
 *
 * Alias fijos: `co` compromisos, `cs` casos, `p` personas, `u` usuarios.
 */
final readonly class ConsultaListadoCompromisos
{
    public function __construct(private ConnectionInterface $db) {}

    public function consultaBase(int $proyectoId): Builder
    {
        return $this->db->table('compromisos as co')
            ->leftJoin('casos as cs', 'cs.id', '=', 'co.caso_id')
            ->leftJoin('personas as p', 'p.id', '=', 'cs.persona_id')
            ->leftJoin('users as u', 'u.id', '=', 'co.usuario_id')
            ->where('co.proyecto_id', $proyectoId)
            ->whereNull('co.eliminada_en');
    }

    /**
     * El límite por cartera del rol del usuario (F22), por el caso del
     * compromiso. `null` es «sin límite».
     *
     * @param  list<int>|null  $carterasPermitidas  Lo que devuelve `User::carterasPermitidas()`.
     */
    public function recortarACarteras(Builder $q, ?array $carterasPermitidas): Builder
    {
        if ($carterasPermitidas !== null) {
            $q->whereIn('cs.cartera_id', $carterasPermitidas);
        }

        return $q;
    }

    /**
     * @param  string  $hoy  'Y-m-d' en el calendario del cliente.
     */
    public function aplicarFiltros(Builder $q, FiltrosListadoCompromisos $filtros, string $hoy): Builder
    {
        if ($filtros->estado !== '') {
            $q->where('co.estado', $filtros->estado);
        }

        match ($filtros->vencimiento) {
            'vigentes' => $q->where('co.estado', 'pendiente')->where('co.fecha_vencimiento', '>=', $hoy),
            'vencidos' => $q->where('co.estado', 'pendiente')->where('co.fecha_vencimiento', '<', $hoy),
            'proximos7d' => $q->where('co.estado', 'pendiente')
                ->whereBetween('co.fecha_vencimiento', [$hoy, Carbon::parse($hoy)->addDays(7)->toDateString()]),
            default => null,
        };

        if ($filtros->tipoCompromiso !== '') {
            $q->where('co.tipo_compromiso', $filtros->tipoCompromiso);
        }

        return $q;
    }
}
