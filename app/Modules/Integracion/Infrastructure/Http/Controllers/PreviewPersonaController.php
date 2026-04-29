<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PreviewPersonaController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'identificacion' => ['required', 'string'],
            'tipo_identificacion_codigo' => ['required', 'string'],
            'proyecto_id' => ['required', 'integer'],
        ]);

        $proyectoId = (int) $request->input('proyecto_id');

        /** @var User $usuario */
        $usuario = $request->user();

        if (! $usuario->tieneAccesoAProyecto($proyectoId)) {
            return response()->json(['message' => 'No tienes acceso a este proyecto.'], 403);
        }

        $persona = DB::table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('ti.codigo', $request->input('tipo_identificacion_codigo'))
            ->where('p.identificacion', $request->input('identificacion'))
            ->whereNull('p.eliminada_en')
            ->select('p.public_id', 'p.nombres', 'p.apellidos', 'p.razon_social', 'p.identificacion', 'ti.codigo as tipo_identificacion')
            ->first();

        if ($persona === null) {
            return response()->json(['message' => 'Persona no encontrada en este proyecto.'], 404);
        }

        $casos = DB::table('casos as c')
            ->join('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.persona_id', function ($sub) use ($persona): void {
                $sub->select('id')->from('personas')->where('public_id', $persona->public_id);
            })
            ->whereNull('c.eliminada_en')
            ->select('c.public_id', 'c.tipo_caso', 'ec.nombre as estado')
            ->get();

        $compromisoVigente = DB::table('compromisos as co')
            ->join('casos as ca', 'ca.id', '=', 'co.caso_id')
            ->where('ca.proyecto_id', $proyectoId)
            ->where('ca.persona_id', function ($sub) use ($persona): void {
                $sub->select('id')->from('personas')->where('public_id', $persona->public_id);
            })
            ->where('co.estado', 'pendiente')
            ->where('co.fecha_vencimiento', '>=', now()->toDateString())
            ->whereNull('co.eliminada_en')
            ->select('co.public_id', 'co.tipo_compromiso', 'co.fecha_vencimiento')
            ->first();

        $ultimaGestion = DB::table('gestiones as g')
            ->join('casos as ca', 'ca.id', '=', 'g.caso_id')
            ->join('resultados as r', 'r.id', '=', 'g.resultado_id')
            ->where('ca.proyecto_id', $proyectoId)
            ->where('ca.persona_id', function ($sub) use ($persona): void {
                $sub->select('id')->from('personas')->where('public_id', $persona->public_id);
            })
            ->whereNull('g.eliminada_en')
            ->orderByDesc('g.creada_en')
            ->select('g.public_id', 'r.nombre as resultado', 'g.creada_en')
            ->first();

        $nombre = $persona->razon_social ?? trim("{$persona->nombres} {$persona->apellidos}");

        return response()->json([
            'persona' => [
                'public_id' => $persona->public_id,
                'nombre' => $nombre,
                'identificacion' => $persona->identificacion,
                'tipo_identificacion' => $persona->tipo_identificacion,
            ],
            'casos' => $casos->toArray(),
            'compromiso_vigente' => $compromisoVigente,
            'ultima_gestion' => $ultimaGestion,
        ]);
    }
}
