<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Http\Livewire;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DatosPromesa;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class NuevaGestion extends Component
{
    public int $productoId;

    public int $clienteId;

    public ?int $canalId = null;

    public ?int $tipoGestionId = null;

    public ?int $resultadoId = null;

    public ?int $causaMoraId = null;

    public ?int $motivoNoContactoId = null;

    public ?int $contactoId = null;

    public string $notas = '';

    public ?int $duracionSegundos = null;

    public ?string $montoPromesa = null;

    public ?string $fechaPromesa = null;

    public ?string $mensajeExito = null;

    public function mount(int $productoId, int $clienteId): void
    {
        $this->productoId = $productoId;
        $this->clienteId = $clienteId;
    }

    /** @return array{es_contacto_efectivo:bool, requiere_promesa:bool, requiere_causa_mora:bool} */
    public function getBanderasProperty(): array
    {
        $vacio = ['es_contacto_efectivo' => false, 'requiere_promesa' => false, 'requiere_causa_mora' => false];

        if ($this->resultadoId === null) {
            return $vacio;
        }

        $row = DB::table('resultados')->where('id', $this->resultadoId)->first();
        if ($row === null) {
            return $vacio;
        }

        $md = is_string($row->metadata) ? (json_decode($row->metadata, true) ?? []) : ($row->metadata ?? []);

        return [
            'es_contacto_efectivo' => (bool) ($md['es_contacto_efectivo'] ?? false),
            'requiere_promesa'     => (bool) ($md['requiere_promesa']     ?? false),
            'requiere_causa_mora'  => (bool) ($md['requiere_causa_mora']  ?? false),
        ];
    }

    public function updatedResultadoId(): void
    {
        $this->resetErrorBag();
        $banderas = $this->banderas;

        if (! $banderas['requiere_promesa']) {
            $this->montoPromesa = null;
            $this->fechaPromesa = null;
        }
        if (! $banderas['requiere_causa_mora']) {
            $this->causaMoraId = null;
        }
    }

    public function guardar(RegistrarGestion $useCase): void
    {
        $this->mensajeExito = null;

        if (auth()->user()?->tienePermiso('gestiones.crear') !== true) {
            abort(403, 'No tienes permiso para registrar gestiones.');
        }

        $banderas = $this->banderas;

        $reglas = [
            'canalId'            => 'required|integer|exists:canales,id',
            'tipoGestionId'      => 'required|integer|exists:tipos_gestion,id',
            'resultadoId'        => 'required|integer|exists:resultados,id',
            'contactoId'         => 'nullable|integer|exists:contactos,id',
            'motivoNoContactoId' => 'nullable|integer|exists:motivos_no_contacto,id',
            'notas'              => 'nullable|string|max:2000',
            'duracionSegundos'   => 'nullable|integer|min:1|max:86400',
            'causaMoraId'        => $banderas['requiere_causa_mora']
                ? 'required|integer|exists:causas_mora,id'
                : 'nullable|integer|exists:causas_mora,id',
            'montoPromesa'       => $banderas['requiere_promesa']
                ? 'required|numeric|min:0.01|max:9999999999999.99'
                : 'nullable',
            'fechaPromesa'       => $banderas['requiere_promesa']
                ? 'required|date|after_or_equal:today'
                : 'nullable',
        ];

        $mensajes = [
            'causaMoraId.required'  => 'El resultado seleccionado exige indicar la causa de mora.',
            'montoPromesa.required' => 'El resultado seleccionado exige registrar el monto de la promesa.',
            'fechaPromesa.required' => 'El resultado seleccionado exige registrar la fecha de la promesa.',
            'fechaPromesa.after_or_equal' => 'La fecha de promesa no puede ser anterior a hoy.',
        ];

        $this->validate($reglas, $mensajes);

        $hoy = new DateTimeImmutable('today');

        $input = new RegistrarGestionInput(
            publicId:           (string) Str::ulid(),
            productoId:         $this->productoId,
            clienteId:          $this->clienteId,
            contactoId:         $this->contactoId,
            canalId:            (int) $this->canalId,
            tipoGestionId:      (int) $this->tipoGestionId,
            resultadoId:        (int) $this->resultadoId,
            causaMoraId:        $this->causaMoraId,
            motivoNoContactoId: $this->motivoNoContactoId,
            usuarioId:          (int) auth()->id(),
            notas:              $this->notas !== '' ? $this->notas : null,
            duracion:           $this->duracionSegundos !== null ? new DuracionSegundos($this->duracionSegundos) : null,
            snapshot:           null,
            datosPromesa:       $banderas['requiere_promesa']
                ? new DatosPromesa(
                    monto: new MontoPromesa(number_format((float) $this->montoPromesa, 2, '.', '')),
                    fecha: FechaPromesa::futura(new DateTimeImmutable((string) $this->fechaPromesa), $hoy),
                )
                : null,
            creadaEn:           new DateTimeImmutable('now'),
        );

        try {
            $useCase->execute($input);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        $this->reset([
            'canalId', 'tipoGestionId', 'resultadoId', 'causaMoraId',
            'motivoNoContactoId', 'contactoId', 'notas',
            'duracionSegundos', 'montoPromesa', 'fechaPromesa',
        ]);
        $this->mensajeExito = 'Gestión registrada.';
        $this->dispatch('gestion-registrada');
    }

    public function render(): View
    {
        return view('gestiones::livewire.nueva-gestion', [
            'canales'    => DB::table('canales')->where('activo', true)->orderBy('orden')->get(),
            'tipos'      => DB::table('tipos_gestion')->where('activo', true)->orderBy('orden')->get(),
            'resultados' => DB::table('resultados')->where('activo', true)->orderBy('orden')->get(),
            'causas'     => DB::table('causas_mora')->where('activo', true)->orderBy('orden')->get(),
            'motivos'    => DB::table('motivos_no_contacto')->where('activo', true)->orderBy('orden')->get(),
            'contactos'  => DB::table('contactos')
                ->where('cliente_id', $this->clienteId)
                ->where('activo', true)
                ->orderByDesc('es_principal')
                ->get(),
            'banderas'   => $this->banderas,
        ]);
    }
}
