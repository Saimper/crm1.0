<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Http\Livewire;

use App\Modules\Contactos\Application\DTOs\RegistrarContactoInput;
use App\Modules\Contactos\Application\UseCases\RegistrarContacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ListaContactos extends Component
{
    public string $personaPublicId;

    public ?int $personaId = null;

    public string $tipo = 'telefono';

    public string $valor = '';

    public string $etiqueta = '';

    public bool $esPrincipal = false;

    public ?string $mensajeExito = null;

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

        $this->mensajeExito = 'Contacto agregado.';
        $this->reset(['valor', 'etiqueta', 'esPrincipal']);
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
