<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\UseCases\SaveRegionalSettings;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class RegionalSettingsEditor extends Component
{
    #[Locked]
    public int $mandanteId;

    #[Locked]
    public ?int $projectId = null;

    public bool $inherit = false;

    /** @var array<string, mixed> */
    public array $regional = [];

    public function mount(int $mandanteId, ?int $projectId = null): void
    {
        $this->mandanteId = $mandanteId;
        $this->projectId = $projectId;
        $this->authorizeSettings();
        $configuration = app(RegionalConfiguration::class);
        $project = $projectId === null ? null : DB::table('proyectos')->where('id', $projectId)->first();
        $this->inherit = $projectId !== null && ! isset($project->zona_horaria);
        $this->regional = ($projectId === null ? $configuration->forMandante($mandanteId) : $configuration->forProject($projectId))->toArray();
    }

    public function save(SaveRegionalSettings $useCase): void
    {
        $this->authorizeSettings();
        if ($this->projectId === null || ! $this->inherit) {
            if (isset($this->regional['moneda']) && is_string($this->regional['moneda'])) {
                $this->regional['moneda'] = strtoupper(trim($this->regional['moneda']));
            }
            $this->validate([
                'regional' => ['required', 'array'],
                'regional.zona_horaria' => ['required', 'string', 'timezone'],
                'regional.moneda' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
                'regional.formato_fecha' => ['required', 'in:d/m/Y,m/d/Y,Y-m-d'],
                'regional.decimales' => ['required', 'integer', 'in:2,3'],
                'regional.separador_decimal' => ['required', 'string', 'max:1'],
                'regional.separador_miles' => ['nullable', 'string', 'max:1'],
                'regional.inicio_semana' => ['required', 'integer', 'between:1,7'],
            ]);
            $this->regional['separador_miles'] ??= '';
        }
        try {
            $settings = $this->projectId !== null && $this->inherit ? null : RegionalSettings::fromArray($this->regional);
            $useCase->execute($this->mandanteId, $this->projectId, $settings);
        } catch (InvalidArgumentException $error) {
            $this->addError('regional', $error->getMessage());

            return;
        }
        $this->resetErrorBag();
        session()->flash('regional-saved', 'Configuración regional guardada.');
    }

    public function render(): View
    {
        $this->authorizeSettings();
        $inherited = app(RegionalConfiguration::class)->forMandante($this->mandanteId);
        try {
            $preview = $this->inherit ? $inherited : RegionalSettings::fromArray($this->regional);
        } catch (InvalidArgumentException) {
            $preview = $inherited;
        }

        return view('tenancy::regional-settings', [
            'timezones' => DateTimeZone::listIdentifiers(), 'preview' => $preview,
            'currencies' => DB::table('monedas')->where('activo', true)->orderBy('codigo_iso')->pluck('codigo_iso'),
        ]);
    }

    private function authorizeSettings(): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        if ($this->projectId === null) {
            abort_unless($user->esAdminGlobal(), 403);
            abort_unless(DB::table('mandantes')->where('id', $this->mandanteId)->whereNull('eliminada_en')->exists(), 404);

            return;
        }
        abort_unless(DB::table('proyectos')->where('id', $this->projectId)->where('mandante_id', $this->mandanteId)->whereNull('eliminada_en')->exists(), 404);
        abort_unless($user->esAdminGlobal() || $user->tienePermiso('proyectos.configurar', $this->projectId), 403);
    }
}
