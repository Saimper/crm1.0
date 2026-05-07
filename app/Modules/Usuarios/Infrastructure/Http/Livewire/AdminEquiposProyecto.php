<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Support\Codigo\GeneradorCodigo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * CRUD de equipos y gestión de miembros por proyecto.
 * Permiso: usuarios.gestionar (SUPERVISOR + ADMIN_GLOBAL via Gate::before).
 *
 * Defensas:
 *   - No admitir usuarios con rol ADMIN_GLOBAL como miembro.
 *   - Solo admite usuarios con rol activo en el proyecto (SUPERVISOR/GESTOR/AUDITOR).
 *   - Código de equipo único por proyecto.
 */
final class AdminEquiposProyecto extends Component
{
    public bool $formEquipoVisible = false;

    public ?int $equipoEditandoId = null;

    public string $formCodigo = '';

    public string $formNombre = '';

    public string $formDescripcion = '';

    public bool $formActivo = true;

    public ?int $gestionandoEquipoId = null;

    public string $buscarEmail = '';

    public ?int $usuarioBuscadoId = null;

    public string $usuarioBuscadoNombre = '';

    public function abrirFormCrear(): void
    {
        $this->resetForm();
        $this->formEquipoVisible = true;
    }

    public function abrirFormEditar(int $equipoId): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $row = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('id', $equipoId)
            ->first();
        if ($row === null) {
            return;
        }

        $this->equipoEditandoId = (int) $row->id;
        $this->formCodigo = (string) $row->codigo;
        $this->formNombre = (string) $row->nombre;
        $this->formDescripcion = (string) ($row->descripcion ?? '');
        $this->formActivo = (bool) $row->activo;
        $this->formEquipoVisible = true;
    }

    public function cerrarFormEquipo(): void
    {
        $this->resetForm();
    }

    public function guardarEquipo(): void
    {
        $this->validate([
            'formCodigo' => GeneradorCodigo::reglaValidacion(50),
            'formNombre' => ['required', 'string', 'max:150'],
            'formDescripcion' => ['nullable', 'string', 'max:500'],
        ], [], [
            'formCodigo' => 'código',
            'formNombre' => 'nombre',
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $codigoInput = trim($this->formCodigo);
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar($this->formNombre, 50)
            : GeneradorCodigo::normalizar($codigoInput, 50);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($proyectoId): bool {
                $q = DB::table('equipos')
                    ->where('proyecto_id', $proyectoId)
                    ->where('codigo', $candidato);
                if ($this->equipoEditandoId !== null) {
                    $q->where('id', '!=', $this->equipoEditandoId);
                }

                return $q->exists();
            },
            50,
        );
        $this->formCodigo = $codigoFinal;

        $payload = [
            'codigo' => $codigoFinal,
            'nombre' => $this->formNombre,
            'descripcion' => $this->formDescripcion !== '' ? $this->formDescripcion : null,
            'activo' => $this->formActivo,
        ];

        if ($this->equipoEditandoId === null) {
            DB::table('equipos')->insert(array_merge($payload, [
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
            ]));
        } else {
            DB::table('equipos')
                ->where('id', $this->equipoEditandoId)
                ->update($payload);
        }

        $this->resetForm();
    }

    public function desactivar(int $equipoId): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('id', $equipoId)
            ->update(['activo' => false]);
    }

    public function activar(int $equipoId): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('id', $equipoId)
            ->update(['activo' => true]);
    }

    public function gestionarMiembros(int $equipoId): void
    {
        $this->gestionandoEquipoId = $equipoId;
        $this->buscarEmail = '';
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
        $this->resetErrorBag();
    }

    public function cerrarMiembros(): void
    {
        $this->gestionandoEquipoId = null;
        $this->buscarEmail = '';
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
    }

    public function buscarUsuario(): void
    {
        $this->validate([
            'buscarEmail' => ['required', 'email'],
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $email = strtolower(trim($this->buscarEmail));

        $user = DB::table('users')->where('email', $email)->first();
        if ($user === null) {
            $this->addError('buscarEmail', 'No existe un usuario con ese email.');

            return;
        }

        $esAdmin = DB::table('usuario_global_rol as ugr')
            ->join('roles as r', 'r.id', '=', 'ugr.rol_id')
            ->where('ugr.usuario_id', $user->id)
            ->where('r.codigo', 'ADMIN_GLOBAL')
            ->exists();
        if ($esAdmin) {
            $this->addError('buscarEmail', 'Los ADMIN_GLOBAL no se agregan a equipos de proyecto.');

            return;
        }

        $tieneRolEnProyecto = DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $user->id)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
        if (! $tieneRolEnProyecto) {
            $this->addError('buscarEmail', 'El usuario no tiene rol activo en este proyecto.');

            return;
        }

        $this->usuarioBuscadoId = (int) $user->id;
        $this->usuarioBuscadoNombre = (string) $user->name;
    }

    public function agregarMiembro(): void
    {
        if ($this->gestionandoEquipoId === null || $this->usuarioBuscadoId === null) {
            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        DB::table('equipo_usuario')->insertOrIgnore([
            'equipo_id' => $this->gestionandoEquipoId,
            'usuario_id' => $this->usuarioBuscadoId,
            'proyecto_id' => $proyectoId,
            'activo' => true,
            'creada_en' => Carbon::now(),
        ]);

        $this->buscarEmail = '';
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
    }

    public function quitarMiembro(int $usuarioId): void
    {
        if ($this->gestionandoEquipoId === null) {
            return;
        }
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        DB::table('equipo_usuario')
            ->where('proyecto_id', $proyectoId)
            ->where('equipo_id', $this->gestionandoEquipoId)
            ->where('usuario_id', $usuarioId)
            ->delete();
    }

    private function resetForm(): void
    {
        $this->formEquipoVisible = false;
        $this->equipoEditandoId = null;
        $this->formCodigo = '';
        $this->formNombre = '';
        $this->formDescripcion = '';
        $this->formActivo = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $equipos = DB::table('equipos as e')
            ->leftJoin('equipo_usuario as eu', 'eu.equipo_id', '=', 'e.id')
            ->where('e.proyecto_id', $proyectoId)
            ->select([
                'e.id', 'e.codigo', 'e.nombre', 'e.descripcion', 'e.activo',
                DB::raw('COUNT(DISTINCT eu.usuario_id) as miembros_count'),
            ])
            ->groupBy('e.id', 'e.codigo', 'e.nombre', 'e.descripcion', 'e.activo')
            ->orderBy('e.nombre')
            ->get();

        $miembros = collect();
        if ($this->gestionandoEquipoId !== null) {
            $miembros = DB::table('equipo_usuario as eu')
                ->join('users as u', 'u.id', '=', 'eu.usuario_id')
                ->leftJoin('usuario_proyecto_rol as upr', function ($join) use ($proyectoId) {
                    $join->on('upr.usuario_id', '=', 'eu.usuario_id')
                        ->where('upr.proyecto_id', '=', $proyectoId)
                        ->where('upr.activo', '=', true);
                })
                ->leftJoin('roles as r', 'r.id', '=', 'upr.rol_id')
                ->where('eu.proyecto_id', $proyectoId)
                ->where('eu.equipo_id', $this->gestionandoEquipoId)
                ->select(['u.id', 'u.name', 'u.email', 'r.codigo as rol_codigo'])
                ->orderBy('u.name')
                ->get();
        }

        return view('usuarios::admin.equipos-proyecto', [
            'equipos' => $equipos,
            'miembros' => $miembros,
        ]);
    }
}
