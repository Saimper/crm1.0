<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Modules\Usuarios\Application\RolesBase\GuardarRolBase;
use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class AdminRolesBase extends Component
{
    #[Locked]
    public ?string $codigo = null;

    #[Locked]
    public string $alcance = 'proyecto';

    public string $nombre = '';

    public string $descripcion = '';

    /** @var list<string> */
    public array $permisos = [];

    /** @var array<int, string> */
    public array $decisiones = [];

    public string $busqueda = '';

    public function editar(string $codigo, string $alcance = 'proyecto'): void
    {
        $this->autorizar();
        abort_unless(in_array($codigo, ConfiguracionRolBase::CODIGOS, true) && in_array($alcance, ['proyecto', 'global'], true), 422);
        $rol = DB::table('roles')->where('codigo', $codigo)->first();
        abort_if($rol === null, 404);
        $this->codigo = $codigo;
        $this->alcance = $alcance;
        $this->nombre = (string) $rol->nombre;
        $this->descripcion = (string) ($rol->descripcion ?? '');
        $this->permisos = DB::table('rol_permiso as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('rp.rol_id', $rol->id)->where('p.activo', true)->pluck('p.codigo')->all();
        $this->decisiones = DB::table('rol_proyecto_permiso as rpp')->join('permisos as p', 'p.id', '=', 'rpp.permiso_id')
            ->where('p.activo', true)->where('rpp.proyecto_id', $this->proyectoId())
            ->where('rpp.rol_id', $rol->id)->pluck('rpp.permitido', 'rpp.permiso_id')
            ->map(fn ($v): string => $v ? 'permitir' : 'denegar')->all();
        $this->busqueda = '';
        $this->resetErrorBag();
    }

    public function cerrar(): void
    {
        $this->codigo = null;
        $this->resetErrorBag();
    }

    public function heredarTodo(): void
    {
        $this->autorizar();
        abort_unless($this->alcance === 'proyecto', 422);
        $this->decisiones = [];
    }

    public function guardar(GuardarRolBase $useCase): void
    {
        $this->autorizar();
        abort_if($this->codigo === null, 422);
        $this->validate([
            'nombre' => ['required', 'string', 'max:100'], 'descripcion' => ['string', 'max:500'],
            'permisos' => ['array'], 'permisos.*' => ['string'],
            'decisiones' => ['array'], 'decisiones.*' => ['in:heredar,permitir,denegar'],
        ]);
        try {
            if ($this->alcance === 'global') {
                $entrada = ConfiguracionRolBase::plantilla($this->codigo, $this->nombre, $this->descripcion ?: null, $this->permisos);
            } else {
                $codigos = DB::table('permisos')->where('activo', true)->whereIn('id', array_keys($this->decisiones))->pluck('codigo', 'id');
                if ($codigos->count() !== count($this->decisiones)) {
                    throw new DomainException('Hay permisos inexistentes o desactivados. Recarga la página.');
                }
                $estados = [];
                foreach ($this->decisiones as $id => $estado) {
                    $estados[(string) $codigos[$id]] = $estado;
                }
                $entrada = ConfiguracionRolBase::proyecto($this->codigo, $this->proyectoId(), $estados);
            }
            $useCase->execute($entrada, (int) auth()->id());
        } catch (DomainException $e) {
            $this->addError('configuracion', $e->getMessage());

            return;
        }
        $this->dispatch('roles-actualizados');
        session()->flash('roles-base-ok', $this->alcance === 'global' ? 'Plantilla global guardada. Se aplicará donde el proyecto herede sus permisos.' : 'Permisos del rol guardados para este proyecto.');
        $this->cerrar();
    }

    public function render(): View
    {
        $this->autorizar();
        $roles = DB::table('roles')->whereIn('codigo', ConfiguracionRolBase::CODIGOS)->orderBy('orden')->get();
        $excepciones = DB::table('rol_proyecto_permiso')->where('proyecto_id', $this->proyectoId())
            ->selectRaw('rol_id, count(*) as total')->groupBy('rol_id')->pluck('total', 'rol_id');
        $query = DB::table('permisos')->where('activo', true)->whereNotIn('codigo', ConfiguracionRolBase::PERMISOS_PROTEGIDOS);
        if (trim($this->busqueda) !== '') {
            $like = '%'.trim($this->busqueda).'%';
            $query->where(fn ($q) => $q->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like)->orWhere('grupo', 'like', $like));
        }

        return view('usuarios::admin.roles-base', [
            'roles' => $roles, 'excepciones' => $excepciones,
            'gruposPermisos' => $query->orderBy('grupo')->orderBy('nombre')->get()->groupBy('grupo'),
        ]);
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->esAdminGlobal() === true, 403);
    }

    private function proyectoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }
}
