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
 * Limitaciones intencionales:
 *   - tipo_persona NO se cambia (cambio invasivo, requiere recrear).
 *   - tipo_identificacion + identificacion editables, pero respetando
 *     UNIQUE (proyecto_id, tipo_identificacion_id, identificacion).
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
            'fechaNacimiento' => 'nullable|date|before:today',
        ];

        if ($this->tipoPersona === 'fisica') {
            $reglas['nombres'] = 'required|string|max:150';
            $reglas['apellidos'] = 'nullable|string|max:150';
        } else {
            $reglas['razonSocial'] = 'required|string|max:250';
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
            'fecha_nacimiento' => $this->fechaNacimiento ?: null,
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->tipoPersona === 'fisica') {
            $payload['nombres'] = $this->nombres;
            $payload['apellidos'] = $this->apellidos !== '' ? $this->apellidos : null;
            $payload['razon_social'] = null;
        } else {
            $payload['razon_social'] = $this->razonSocial;
            $payload['nombres'] = null;
            $payload['apellidos'] = null;
            $payload['fecha_nacimiento'] = null;
        }

        DB::table('personas')
            ->where('id', $this->personaId)
            ->where('proyecto_id', $proyectoId)
            ->update($payload);

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
