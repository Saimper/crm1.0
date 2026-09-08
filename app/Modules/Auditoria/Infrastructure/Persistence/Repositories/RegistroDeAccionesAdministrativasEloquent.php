<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Persistence\Repositories;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use App\Modules\Auditoria\Infrastructure\Persistence\Models\AuditoriaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegistroDeAccionesAdministrativasEloquent implements RegistroDeAccionesAdministrativas
{
    public function alta(
        string $entidadTipo,
        int $entidadId,
        array $datos,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void {
        $this->escribir($entidadTipo, $entidadId, 'creado', $proyectoId, $mandanteId, despues: $datos);
    }

    public function cambio(
        string $entidadTipo,
        int $entidadId,
        array $cambios,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void {
        if ($cambios === []) {
            return;
        }

        $this->escribir($entidadTipo, $entidadId, 'actualizado', $proyectoId, $mandanteId, cambios: $cambios);
    }

    public function baja(
        string $entidadTipo,
        int $entidadId,
        array $datos,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void {
        $this->escribir($entidadTipo, $entidadId, 'eliminado', $proyectoId, $mandanteId, antes: $datos);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     * @param  array<string, array{antes: mixed, despues: mixed}>|null  $cambios
     */
    private function escribir(
        string $entidadTipo,
        int $entidadId,
        string $evento,
        ?int $proyectoId,
        ?int $mandanteId,
        ?array $antes = null,
        ?array $despues = null,
        ?array $cambios = null,
    ): void {
        AuditoriaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            // Atribuir el evento a su cliente EN EL MOMENTO de escribirlo, igual
            // que hace el observer: si se dejara para la lectura, una acción
            // administrativa —que no cuelga de ningún proyecto— no sería de
            // nadie y sólo la vería ADMIN_GLOBAL.
            'mandante_id' => $this->mandanteDe($proyectoId, $mandanteId),
            'usuario_id' => auth()->id(),
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'evento' => $evento,
            'datos_antes' => $antes,
            'datos_despues' => $despues,
            'cambios' => $cambios,
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512) ?: null,
        ]);
    }

    private function mandanteDe(?int $proyectoId, ?int $mandanteId): ?int
    {
        if ($mandanteId !== null) {
            return $mandanteId;
        }

        if ($proyectoId === null) {
            return null;
        }

        $id = DB::table('proyectos')->where('id', $proyectoId)->value('mandante_id');

        return $id === null ? null : (int) $id;
    }
}
