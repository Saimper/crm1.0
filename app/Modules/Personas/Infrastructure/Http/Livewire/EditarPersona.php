<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Edita los datos básicos de una persona del proyecto activo.
 *
 * Limitaciones intencionales (B1):
 *   - tipo_persona NO se cambia (cambio invasivo, requiere recrear).
 *   - razón social y fecha de nacimiento son read-only — solo visibles si la
 *     persona viene de importación masiva con esos datos. UI no los edita.
 *   - tipo_identificacion + identificacion editables, respetando UNIQUE
 *     (proyecto_id, tipo_identificacion_id, identificacion).
 *
 * Permiso: personas.editar (SUPERVISOR + GESTOR + ADMIN_GLOBAL por defecto).
 */
final class EditarPersona extends Component
{
    public string $personaPublicId = '';

    public ?int $personaId = null;

    public string $tipoPersona = 'fisica';

    public ?int $tipoIdentificacionId = null;

    public string $identificacion = '';

    /**
     * Identificación tal como se cargó (pre-edición). Es el pivote estable que
     * el wrapper coteja con el vendor_lead_code del lead en llamada: aunque el
     * agente corrija la identificación, este valor sigue coincidiendo con el
     * lead (la corrección viaja en `cambios`).
     */
    public string $identificacionOriginal = '';

    public string $nombres = '';

    public string $apellidos = '';

    public string $razonSocial = '';

    public ?string $fechaNacimiento = null;

    public function mount(string $persona): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $row = DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->where('public_id', $persona)
            ->whereNull('eliminada_en')
            ->first();
        abort_unless($row !== null, 404, 'Persona no encontrada en el proyecto activo.');

        $this->personaPublicId = $persona;
        $this->personaId = (int) $row->id;
        $this->tipoPersona = (string) $row->tipo_persona;
        $this->tipoIdentificacionId = (int) $row->tipo_identificacion_id;
        $this->identificacion = (string) $row->identificacion;
        $this->identificacionOriginal = (string) $row->identificacion;
        $this->nombres = (string) ($row->nombres ?? '');
        $this->apellidos = (string) ($row->apellidos ?? '');
        $this->razonSocial = (string) ($row->razon_social ?? '');
        $this->fechaNacimiento = $row->fecha_nacimiento !== null
            ? Carbon::parse($row->fecha_nacimiento)->format('Y-m-d')
            : null;
    }

    public function guardar(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('personas.editar', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para editar personas en este proyecto.');
        }

        if ($this->personaId === null) {
            return;
        }

        $reglas = [
            'tipoIdentificacionId' => 'required|integer|exists:tipos_identificacion,id',
            'identificacion' => 'required|string|min:5|max:50',
        ];

        if ($this->tipoPersona === 'fisica') {
            $reglas['nombres'] = 'required|string|max:150';
            $reglas['apellidos'] = 'nullable|string|max:150';
        }

        $this->validate($reglas);

        $proyectoId = (int) $proyecto->id;
        $identificacionLimpia = strtoupper(trim($this->identificacion));

        $duplicado = DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_identificacion_id', $this->tipoIdentificacionId)
            ->where('identificacion', $identificacionLimpia)
            ->where('id', '!=', $this->personaId)
            ->whereNull('eliminada_en')
            ->exists();
        if ($duplicado) {
            $this->addError('identificacion', 'Ya existe otra persona con esa identificación en el proyecto.');

            return;
        }

        $payload = [
            'tipo_identificacion_id' => $this->tipoIdentificacionId,
            'identificacion' => $identificacionLimpia,
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->tipoPersona === 'fisica') {
            $payload['nombres'] = $this->nombres;
            $payload['apellidos'] = $this->apellidos !== '' ? $this->apellidos : null;
        }

        DB::table('personas')
            ->where('id', $this->personaId)
            ->where('proyecto_id', $proyectoId)
            ->update($payload);

        // Reenvía la edición al wrapper (ViciDial) si el CRM está embebido. El
        // relay JS solo postea si hay wrapper-origin firmado en la sesión, y el
        // wrapper solo aplica los campos que el supervisor haya mapeado.
        $cambios = ['identificacion' => $identificacionLimpia];
        if ($this->tipoPersona === 'fisica') {
            $cambios['nombres'] = $this->nombres;
            $cambios['apellidos'] = $this->apellidos;
        }
        $this->dispatch('crm-sync', tipo: 'persona', cambios: $cambios, pivote: [
            'identificacion' => $this->identificacionOriginal,
        ]);

        session()->flash('persona_editada', 'Persona actualizada.');

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyectoId,
            'persona' => $this->personaPublicId,
        ], navigate: true);
    }

    public function render(): View
    {
        return view('personas::livewire.editar-persona', [
            'tiposIdentificacion' => DB::table('tipos_identificacion')
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }
}
