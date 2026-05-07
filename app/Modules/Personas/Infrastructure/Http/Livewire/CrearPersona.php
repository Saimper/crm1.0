<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\DatosPersonaInvalidos;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

final class CrearPersona extends Component
{
    public ?int $tipoIdentificacionId = null;

    public string $identificacion = '';

    public string $nombres = '';

    public string $apellidos = '';

    public function guardar(RegistrarPersona $useCase): void
    {
        $proyecto = app('tenancy.proyecto_activo');

        if (auth()->user()?->tienePermiso('personas.crear', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para crear personas en este proyecto.');
        }

        $this->validate([
            'tipoIdentificacionId' => 'required|integer|exists:tipos_identificacion,id',
            'identificacion' => 'required|string|min:5|max:50',
            'nombres' => 'required|string|max:150',
            'apellidos' => 'nullable|string|max:150',
        ]);

        try {
            // tipoPersona fijado a FISICA — UI simplificada (B1). Persona jurídica vía importación masiva.
            $output = $useCase->execute(new RegistrarPersonaInput(
                publicId: (string) Str::ulid(),
                proyectoId: (int) $proyecto->id,
                tipoPersona: TipoPersona::FISICA,
                tipoIdentificacionId: (int) $this->tipoIdentificacionId,
                identificacion: new Identificacion($this->identificacion),
                nombres: $this->nombres !== '' ? $this->nombres : null,
                apellidos: $this->apellidos !== '' ? $this->apellidos : null,
                razonSocial: null,
                fechaNacimiento: null,
                creadaEn: new DateTimeImmutable('now'),
            ));
        } catch (IdentificacionYaRegistradaEnProyecto $e) {
            throw ValidationException::withMessages(['identificacion' => $e->getMessage()]);
        } catch (DatosPersonaInvalidos|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        session()->flash('persona_creada', [
            'public_id' => $output->publicId,
            'nombre' => $output->nombreCompleto,
        ]);

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyecto->id,
            'persona' => $output->publicId,
        ], navigate: true);
    }

    public function render(): View
    {
        return view('personas::livewire.crear-persona', [
            'tiposIdentificacion' => DB::table('tipos_identificacion')
                ->where('activo', true)
                ->orderBy('orden')
                ->get(),
        ]);
    }
}
