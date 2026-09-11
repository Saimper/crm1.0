<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Application\RolesBase;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

final readonly class GuardarRolBase
{
    public function __construct(
        private ConnectionInterface $db,
        private RegistroDeAccionesAdministrativas $auditoria,
    ) {}

    public function execute(ConfiguracionRolBase $entrada, int $actorId): void
    {
        $administrador = $this->db->table('usuario_global_rol as ugr')
            ->join('roles as r', 'r.id', '=', 'ugr.rol_id')
            ->join('users as u', 'u.id', '=', 'ugr.usuario_id')
            ->where('ugr.usuario_id', $actorId)->where('r.codigo', 'ADMIN_GLOBAL')
            ->where('r.activo', true)->where('u.activo', true)->exists();
        if (! $administrador) {
            throw new AuthorizationException('Solo el administrador global puede modificar roles base.');
        }

        $this->db->transaction(function () use ($entrada): void {
            $rol = $this->db->table('roles')->where('codigo', $entrada->codigo)->lockForUpdate()->first();
            if ($rol === null) {
                throw new DomainException('El rol no existe.');
            }
            $permisos = $this->db->table('permisos')->where('activo', true)
                ->whereIn('codigo', array_keys($entrada->permisos))->pluck('id', 'codigo');
            if ($permisos->count() !== count($entrada->permisos)) {
                throw new DomainException('Hay permisos inexistentes o desactivados. Recarga la página.');
            }

            if ($entrada->proyectoId === null) {
                $antes = $this->db->table('rol_permiso as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
                    ->where('rp.rol_id', $rol->id)->orderBy('p.codigo')->pluck('p.codigo')->all();
                $this->db->table('roles')->where('id', $rol->id)->update([
                    'nombre' => $entrada->nombre,
                    'descripcion' => $entrada->descripcion,
                    'configurado_en' => now(),
                ]);
                $this->db->table('rol_permiso')->where('rol_id', $rol->id)->delete();
                foreach ($permisos as $id) {
                    $this->db->table('rol_permiso')->insert(['rol_id' => $rol->id, 'permiso_id' => $id]);
                }
                $cambios = [];
                foreach (['nombre' => $entrada->nombre, 'descripcion' => $entrada->descripcion, 'permisos' => array_keys($entrada->permisos)] as $campo => $valor) {
                    $previo = $campo === 'permisos' ? $antes : $rol->{$campo};
                    if ($previo !== $valor) {
                        $cambios[$campo] = ['antes' => $previo, 'despues' => $valor];
                    }
                }
                if ($rol->configurado_en === null) {
                    $cambios['plantilla_administrada'] = ['antes' => false, 'despues' => true];
                }
                $this->auditoria->cambio('roles', (int) $rol->id, $cambios);

                return;
            }

            if (! $this->db->table('proyectos')->where('id', $entrada->proyectoId)->where('activo', true)->whereNull('eliminada_en')->exists()) {
                throw new DomainException('El proyecto no está disponible.');
            }
            $antes = $this->db->table('rol_proyecto_permiso as rpp')->join('permisos as p', 'p.id', '=', 'rpp.permiso_id')
                ->where('rpp.proyecto_id', $entrada->proyectoId)->where('rpp.rol_id', $rol->id)
                ->orderBy('p.codigo')->pluck('rpp.permitido', 'p.codigo')->map(fn ($v): bool => (bool) $v)->all();
            $this->db->table('rol_proyecto_permiso')->where('proyecto_id', $entrada->proyectoId)->where('rol_id', $rol->id)->delete();
            foreach ($entrada->permisos as $codigo => $permitido) {
                $this->db->table('rol_proyecto_permiso')->insert([
                    'proyecto_id' => $entrada->proyectoId, 'rol_id' => $rol->id,
                    'permiso_id' => $permisos[$codigo], 'permitido' => $permitido,
                ]);
            }
            $this->auditoria->cambio('rol_proyecto_permiso', (int) $rol->id, $antes === $entrada->permisos ? [] : [
                'rol' => ['antes' => $entrada->codigo, 'despues' => $entrada->codigo],
                'excepciones' => ['antes' => $antes, 'despues' => $entrada->permisos],
            ], $entrada->proyectoId);
        });
    }
}
