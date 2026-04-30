<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Infrastructure\Http\Livewire;

use App\Modules\Clientes\Application\DTOs\RegistrarClienteInput;
use App\Modules\Clientes\Application\Exceptions\IdentificacionYaExistente;
use App\Modules\Clientes\Application\UseCases\RegistrarCliente;
use App\Modules\Clientes\Domain\Exceptions\DatosClienteInvalidos;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

final class CrearCliente extends Component
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

    public function guardar(RegistrarCliente $useCase): void
    {
        if (auth()->user()?->tienePermiso('clientes.crear') !== true) {
            abort(403, 'No tienes permiso para crear clientes.');
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
            $output = $useCase->execute(new RegistrarClienteInput(
                publicId: (string) Str::ulid(),
                tipoPersona: TipoPersona::from($this->tipoPersona),
                tipoIdentificacionId: (int) $this->tipoIdentificacionId,
                identificacion: new Identificacion($this->identificacion),
                nombres: $this->nombres !== '' ? $this->nombres : null,
                apellidos: $this->apellidos !== '' ? $this->apellidos : null,
                razonSocial: $this->razonSocial !== '' ? $this->razonSocial : null,
                fechaNacimiento: $this->fechaNacimiento ? new DateTimeImmutable($this->fechaNacimiento) : null,
                creadaEn: new DateTimeImmutable('now'),
            ));
        } catch (IdentificacionYaExistente $e) {
            throw ValidationException::withMessages(['identificacion' => $e->getMessage()]);
        } catch (DatosClienteInvalidos|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        $this->redirectRoute('trabajo', ['cliente' => $output->publicId], navigate: true);
    }

    public function render(): View
    {
        return view('clientes::livewire.crear-cliente', [
            'tiposIdentificacion' => DB::table('tipos_identificacion')
                ->where('activo', true)
                ->orderBy('orden')
                ->get(),
        ]);
    }
}
