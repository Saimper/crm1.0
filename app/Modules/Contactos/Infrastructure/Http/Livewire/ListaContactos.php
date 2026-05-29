<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Http\Livewire;

use App\Modules\Contactos\Application\DTOs\RegistrarContactoInput;
use App\Modules\Contactos\Application\UseCases\RegistrarContacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use App\Modules\Integracion\Infrastructure\Http\Concerns\EmiteWritebackFicha;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ListaContactos extends Component
{
    use EmiteWritebackFicha;

    public string $personaPublicId;

    public ?int $personaId = null;

    public string $tipo = 'telefono';

    public string $valor = '';

    public string $etiqueta = '';

    public bool $esPrincipal = false;

    public ?string $mensajeExito = null;

    public ?int $editandoId = null;

    public function mount(string $persona): void
    {
        $this->personaPublicId = $persona;

        /** @var PersonaModel|null $persona */
        $personaModel = PersonaModel::query()->where('public_id', $persona)->first();
        abort_unless($personaModel !== null, 404, 'Persona no encontrada en el proyecto activo.');

        $this->personaId = (int) $personaModel->id;
    }

    public function agregar(RegistrarContacto $useCase): void
    {
        $proyecto = app('tenancy.proyecto_activo');

        if (auth()->user()?->tienePermiso('contactos.crear', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para agregar contactos en este proyecto.');
        }

        $this->validate([
            'tipo' => 'required|in:telefono,correo,direccion',
            'valor' => 'required|string|max:250',
            'etiqueta' => 'nullable|string|max:100',
            'esPrincipal' => 'boolean',
        ]);

        try {
            $useCase->execute(new RegistrarContactoInput(
                proyectoId: (int) $proyecto->id,
                personaId: (int) $this->personaId,
                tipo: TipoContacto::from($this->tipo),
                valor: $this->valor,
                etiqueta: $this->etiqueta !== '' ? $this->etiqueta : null,
                esPrincipal: $this->esPrincipal,
                creadaEn: new DateTimeImmutable('now'),
            ));
        } catch (DatosContactoInvalidos $e) {
            throw ValidationException::withMessages(['valor' => $e->getMessage()]);
        }

        $this->emitirWritebackFicha(['contacto' => $this->construirGrupoContacto()]);

        $this->mensajeExito = 'Contacto agregado.';
        $this->reset(['valor', 'etiqueta', 'esPrincipal']);
    }

    public function abrirEditar(int $id): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $row = DB::table('contactos')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->where('persona_id', $this->personaId)
            ->whereNull('eliminada_en')
            ->first();
        if ($row === null) {
            return;
        }

        $this->editandoId = (int) $row->id;
        $this->tipo = (string) $row->tipo;
        $this->valor = (string) $row->valor;
        $this->etiqueta = (string) ($row->etiqueta ?? '');
        $this->esPrincipal = (bool) $row->es_principal;
        $this->resetErrorBag();
    }

    public function cancelarEdicion(): void
    {
        $this->editandoId = null;
        $this->reset(['valor', 'etiqueta', 'esPrincipal']);
        $this->tipo = 'telefono';
        $this->resetErrorBag();
    }

    public function guardarEdicion(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('contactos.editar', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para editar contactos en este proyecto.');
        }

        if ($this->editandoId === null) {
            return;
        }

        $this->validate([
            'tipo' => 'required|in:telefono,correo,direccion',
            'valor' => 'required|string|max:250',
            'etiqueta' => 'nullable|string|max:100',
            'esPrincipal' => 'boolean',
        ]);

        $proyectoId = (int) $proyecto->id;
        $ahora = Carbon::now();

        DB::transaction(function () use ($proyectoId, $ahora): void {
            // Si marca como principal, degradar otros del mismo tipo de la persona.
            if ($this->esPrincipal) {
                DB::table('contactos')
                    ->where('proyecto_id', $proyectoId)
                    ->where('persona_id', $this->personaId)
                    ->where('tipo', $this->tipo)
                    ->where('id', '!=', $this->editandoId)
                    ->whereNull('eliminada_en')
                    ->update(['es_principal' => false, 'actualizada_en' => $ahora]);
            }

            DB::table('contactos')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update([
                    'tipo' => $this->tipo,
                    'valor' => $this->valor,
                    'etiqueta' => $this->etiqueta !== '' ? $this->etiqueta : null,
                    'es_principal' => $this->esPrincipal,
                    'actualizada_en' => $ahora,
                ]);
        });

        $this->emitirWritebackFicha(['contacto' => $this->construirGrupoContacto()]);

        $this->mensajeExito = 'Contacto actualizado.';
        $this->editandoId = null;
        $this->reset(['valor', 'etiqueta', 'esPrincipal']);
        $this->tipo = 'telefono';
    }

    /**
     * Grupo `contacto` del writeback: los contactos PRINCIPALES de la persona (uno por
     * canal). Las claves (`telefono`/`correo`/`direccion`) coinciden con el contrato.
     *
     * @return array<string, string>
     */
    private function construirGrupoContacto(): array
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $principales = DB::table('contactos')
            ->where('proyecto_id', $proyectoId)
            ->where('persona_id', $this->personaId)
            ->where('es_principal', true)
            ->whereNull('eliminada_en')
            ->get(['tipo', 'valor']);

        $grupo = [];
        foreach ($principales as $contacto) {
            $grupo[(string) $contacto->tipo] = (string) $contacto->valor;
        }

        return $grupo;
    }

    public function eliminar(int $id): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('contactos.eliminar', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para eliminar contactos en este proyecto.');
        }

        DB::table('contactos')
            ->where('id', $id)
            ->where('proyecto_id', (int) $proyecto->id)
            ->where('persona_id', $this->personaId)
            ->whereNull('eliminada_en')
            ->update(['eliminada_en' => Carbon::now()]);

        $this->mensajeExito = 'Contacto eliminado.';
    }

    public function render(): View
    {
        $persona = DB::table('personas')->where('id', $this->personaId)->first();
        abort_unless($persona !== null, 404);

        $contactos = DB::table('contactos')
            ->where('persona_id', $this->personaId)
            ->whereNull('eliminada_en')
            ->orderByDesc('es_principal')
            ->orderBy('tipo')
            ->orderBy('creada_en')
            ->get();

        $nombre = $persona->tipo_persona === 'juridica'
            ? (string) ($persona->razon_social ?? '')
            : trim((string) ($persona->nombres ?? '').' '.(string) ($persona->apellidos ?? ''));

        return view('contactos::livewire.lista-contactos', [
            'persona' => $persona,
            'nombre' => $nombre,
            'contactos' => $contactos,
        ]);
    }
}
