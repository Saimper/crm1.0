<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * F35 — pantalla admin global para ver y rotar el sso_secret de cada proyecto.
 *
 * Permiso: ADMIN_GLOBAL via Gate::before (ruta protegida con admin.global).
 *
 * El secret se muestra enmascarado por defecto. El usuario puede revelarlo
 * temporalmente para copiarlo. La rotación genera un valor nuevo y lo muestra
 * UNA vez (advertencia: el wrapper queda desincronizado hasta que se actualice
 * tenants.crm_token_encrypted).
 */
final class AdminSsoSecrets extends Component
{
    /** @var array<int, bool> */
    public array $revelado = [];

    /** ID del último proyecto rotado (mostrar secret completo una sola vez). */
    public ?int $rotadoId = null;

    public ?string $rotadoSecret = null;

    public function revelar(int $proyectoId): void
    {
        $this->revelado[$proyectoId] = true;
    }

    public function ocultar(int $proyectoId): void
    {
        $this->revelado[$proyectoId] = false;
        $this->rotadoId = null;
        $this->rotadoSecret = null;
    }

    public function rotar(int $proyectoId): void
    {
        $nuevo = bin2hex(random_bytes(32));

        DB::table('proyectos')
            ->where('id', $proyectoId)
            ->update([
                'sso_secret' => $nuevo,
                'actualizada_en' => now(),
            ]);

        $this->rotadoId = $proyectoId;
        $this->rotadoSecret = $nuevo;
        $this->revelado[$proyectoId] = true;

        session()->flash(
            'admin-sso-ok',
            'Secret rotado. Actualizar el wrapper antes de que el cache de cliente venza.',
        );
    }

    #[Computed]
    public function proyectos(): Collection
    {
        return DB::table('proyectos as p')
            ->leftJoin('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->whereNull('p.eliminada_en')
            ->select([
                'p.id', 'p.codigo', 'p.nombre', 'p.activo',
                'p.sso_secret', 'p.actualizada_en',
                'm.codigo as mandante_codigo',
            ])
            ->orderBy('m.codigo')
            ->orderBy('p.codigo')
            ->get();
    }

    public function render(): View
    {
        return view('integracion::admin.sso-secrets');
    }
}
