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
    /** @var 'fisica'|'juridica' */
    public string $tipoPersona = 'fisica';

    public ?int $tipoIdentificacionId = null;

    public string $identificacion = '';

    public string $nombres = '';

    public string $apellidos = '';

    public string $razonSocial = '';

    public ?string $fechaNacimiento = null;

    public function updatedTipoPersona(): void
    {
        $this->resetErrorBag();
    }

    public function guardar(RegistrarPersona $useCase): void
    {
        $proyecto = app('tenancy.proyecto_activo');

        if (auth()->user()?->tienePermiso('personas.crear', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para crear personas en este proyecto.');
        }

        $reglas = [
            'tipoPersona' => 'required|in:fisica,juridica',
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

        try {
            $output = $useCase->execute(new RegistrarPersonaInput(
                publicId: (string) Str::ulid(),
                proyectoId: (int) $proyecto->id,
                tipoPersona: TipoPersona::from($this->tipoPersona),
                tipoIdentificacionId: (int) $this->tipoIdentificacionId,
                identificacion: new Identificacion($this->identificacion),
                nombres: $this->nombres !== '' ? $this->nombres : null,
                apellidos: $this->apellidos !== '' ? $this->apellidos : null,
                razonSocial: $this->razonSocial !== '' ? $this->razonSocial : null,
                fechaNacimiento: $this->fechaNacimiento ? new DateTimeImmutable($this->fechaNacimiento) : null,
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

        $this->redirectRoute('proyectos.dashboard', ['proyecto_id' => $proyecto->id], navigate: true);
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
