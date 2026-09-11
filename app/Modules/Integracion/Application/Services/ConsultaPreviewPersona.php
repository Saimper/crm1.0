<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Services;

use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Support\Database\CarterasOperativas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/** The integration summary shares one authorized operational account scope. */
final readonly class ConsultaPreviewPersona
{
    public function __construct(private ConnectionInterface $db, private RegionalConfiguration $regional) {}

    /**
     * @param  list<int>|null  $carterasPermitidas
     * @return array<string, mixed>|null
     */
    public function consultar(int $proyectoId, string $identificacion, string $tipoIdentificacion, ?array $carterasPermitidas): ?array
    {
        $persona = $this->db->table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('ti.codigo', $tipoIdentificacion)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->select('p.id', 'p.public_id', 'p.nombres', 'p.apellidos', 'p.razon_social', 'p.identificacion', 'ti.codigo as tipo_identificacion')
            ->first();

        if ($persona === null) {
            return null;
        }

        $cuentas = CarterasOperativas::casos($this->db, $proyectoId)
            ->where('c.persona_id', $persona->id)
            ->when($carterasPermitidas !== null, fn ($q) => $q->whereIn('c.cartera_id', $carterasPermitidas));

        // A new person without debts remains a valid integration lookup. A person
        // whose debts are all archived or inaccessible belongs outside this view.
        if (! (clone $cuentas)->exists() && $this->db->table('casos')
            ->where('proyecto_id', $proyectoId)->where('persona_id', $persona->id)->exists()) {
            return null;
        }

        $casos = (clone $cuentas)
            ->join('estados_caso as ec', fn ($join) => $join->on('ec.id', '=', 'c.estado_caso_id')->on('ec.proyecto_id', '=', 'c.proyecto_id'))
            ->orderBy('c.id')
            ->get(['c.public_id', 'c.tipo_caso', 'ec.nombre as estado']);

        $compromisoVigente = $this->db->table('compromisos as co')
            ->where('co.proyecto_id', $proyectoId)
            ->whereIn('co.caso_id', (clone $cuentas)->select('c.id'))
            ->where('co.estado', 'pendiente')
            ->where('co.fecha_vencimiento', '>=', Carbon::now($this->regional->forProject($proyectoId)->timezone)->toDateString())
            ->whereNull('co.eliminada_en')
            ->orderBy('co.fecha_vencimiento')->orderBy('co.id')
            ->first(['co.public_id', 'co.tipo_compromiso', 'co.fecha_vencimiento']);

        $ultimaGestion = $this->db->table('gestiones as g')
            ->join('resultados as r', fn ($join) => $join->on('r.id', '=', 'g.resultado_id')->on('r.proyecto_id', '=', 'g.proyecto_id'))
            ->where('g.proyecto_id', $proyectoId)
            ->whereIn('g.caso_id', (clone $cuentas)->select('c.id'))
            ->whereNull('g.eliminada_en')
            ->orderByDesc('g.creada_en')->orderByDesc('g.id')
            ->first(['g.public_id', 'r.nombre as resultado', 'g.creada_en']);

        return [
            'persona' => [
                'public_id' => $persona->public_id,
                'nombre' => $persona->razon_social ?? trim("{$persona->nombres} {$persona->apellidos}"),
                'identificacion' => $persona->identificacion,
                'tipo_identificacion' => $persona->tipo_identificacion,
            ],
            'casos' => $casos->all(),
            'compromiso_vigente' => $compromisoVigente,
            'ultima_gestion' => $ultimaGestion,
        ];
    }
}
