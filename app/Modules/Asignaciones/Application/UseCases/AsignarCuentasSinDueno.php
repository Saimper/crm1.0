<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\AsignacionMasivaResultado;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use App\Modules\Usuarios\Domain\Contracts\AccesoAReparto;
use App\Support\Database\CarterasOperativas;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/** Assigns unowned accounts to an advisor or eligible team members. */
final readonly class AsignarCuentasSinDueno
{
    public function __construct(
        private ConnectionInterface $db,
        private AccesoAReparto $acceso,
        private GeneradorNotificaciones $notificaciones,
    ) {}

    public function execute(int $proyectoId, int $actorId, ?int $asesorId = null, ?int $equipoId = null, ?int $carteraId = null, int $limite = 0): AsignacionMasivaResultado
    {
        if (! $this->acceso->puedeRepartir($actorId, $proyectoId, $carteraId)) {
            throw new AuthorizationException('No tienes permiso para repartir estas cuentas.');
        }
        if (($asesorId === null) === ($equipoId === null) || $limite < 0) {
            throw new DomainException('Selecciona un asesor o un equipo y una cantidad válida.');
        }
        $miembros = $asesorId === null ? [] : [$asesorId];
        if ($equipoId !== null) {
            if (! $this->db->table('equipos')->where('proyecto_id', $proyectoId)->where('id', $equipoId)->where('activo', true)->whereNull('eliminada_en')->exists()) {
                throw new DomainException('El equipo no está activo en este proyecto.');
            }
            $miembros = $this->db->table('equipo_usuario')->where('proyecto_id', $proyectoId)
                ->where('equipo_id', $equipoId)->where('activo', true)->orderBy('usuario_id')->pluck('usuario_id')->map(fn ($id): int => (int) $id)->all();
        }
        $carteras = $this->db->table('carteras')->where('proyecto_id', $proyectoId)->where('activo', true)->whereNull('eliminada_en')
            ->when($carteraId !== null, fn ($q) => $q->where('id', $carteraId))->pluck('id');
        $destinos = [];
        foreach ($carteras as $id) {
            $id = (int) $id;
            if (! $this->acceso->puedeRepartir($actorId, $proyectoId, $id)) {
                continue;
            }
            $permitidos = array_values(array_filter($miembros, fn (int $usuarioId): bool => $this->acceso->puedeRecibir($usuarioId, $proyectoId, $id)));
            if ($permitidos !== []) {
                $destinos[$id] = $permitidos;
            }
        }
        if ($destinos === []) {
            throw new DomainException('El destino no tiene gestores autorizados para las carteras seleccionadas.');
        }

        $consulta = CarterasOperativas::casos($this->db, $proyectoId)
            ->join('personas as pe', fn ($join) => $join->on('pe.id', '=', 'c.persona_id')->on('pe.proyecto_id', '=', 'c.proyecto_id'))
            ->whereNull('pe.eliminada_en')->whereNull('c.cerrado_en')->whereIn('c.cartera_id', array_keys($destinos))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('asignaciones as a')->whereColumn('a.caso_id', 'c.id')->where('a.proyecto_id', $proyectoId))
            ->select(['c.id', 'c.cartera_id']);
        $distribucion = [];
        $asignadas = 0;
        $omitidas = 0;
        $turno = 0;
        foreach ($consulta->lazyById(500, 'c.id', 'id') as $caso) {
            if ($limite > 0 && $asignadas >= $limite) {
                break;
            }
            $posibles = $destinos[(int) $caso->cartera_id];
            $destinatario = $posibles[$turno % count($posibles)];
            $turno++;
            $insertada = $this->db->transaction(function () use ($caso, $proyectoId, $actorId, $destinatario): int {
                $actual = CarterasOperativas::casos($this->db, $proyectoId)->where('c.id', $caso->id)->whereNull('c.cerrado_en')->lockForUpdate()->first(['c.id', 'c.cartera_id', 'c.persona_id']);
                if ($actual === null || (int) $actual->cartera_id !== (int) $caso->cartera_id
                    || ! $this->db->table('personas')->where('proyecto_id', $proyectoId)->where('id', $actual->persona_id)->whereNull('eliminada_en')->exists()
                    || ! $this->acceso->puedeRepartir($actorId, $proyectoId, (int) $actual->cartera_id)
                    || ! $this->acceso->puedeRecibir($destinatario, $proyectoId, (int) $actual->cartera_id)) {
                    return 0;
                }

                return $this->db->table('asignaciones')->insertOrIgnore([
                    'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId, 'caso_id' => $actual->id,
                    'usuario_id' => $destinatario, 'fecha_asignacion' => now()->toDateString(),
                    'prioridad' => 100, 'estado' => 'pendiente', 'creada_en' => now(),
                ]);
            });
            if ($insertada === 1) {
                $asignadas++;
                $distribucion[$destinatario] = ($distribucion[$destinatario] ?? 0) + 1;
            } else {
                $omitidas++;
            }
        }
        if ($distribucion !== []) {
            $this->notificaciones->registrarAsignacionesRecibidas($proyectoId, $distribucion, 'asignacion');
        }

        return new AsignacionMasivaResultado($asignadas, $omitidas, $distribucion);
    }
}
