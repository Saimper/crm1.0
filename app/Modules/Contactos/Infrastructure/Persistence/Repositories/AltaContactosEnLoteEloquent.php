<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Persistence\Repositories;

use App\Modules\Contactos\Domain\Contracts\AltaContactosEnLote;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final readonly class AltaContactosEnLoteEloquent implements AltaContactosEnLote
{
    public function __construct(private ConnectionInterface $db) {}

    public function alta(int $proyectoId, int $personaId, array $contactos, string $origen): int
    {
        if ($contactos === []) {
            return 0;
        }

        $ahora = Carbon::now();

        // El primer teléfono de una persona que no tiene ninguno pasa a ser el
        // principal: es el que la Vista de Trabajo propone por defecto.
        $yaTienePrincipal = $this->db->table('contactos')
            ->where('persona_id', $personaId)
            ->where('es_principal', true)
            ->whereNull('eliminada_en')
            ->exists();

        $filas = [];
        $vistos = [];

        foreach ($contactos as $contacto) {
            if (isset($vistos[$contacto->clave()])) {
                continue;
            }

            $vistos[$contacto->clave()] = true;

            $filas[] = [
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'persona_id' => $personaId,
                'tipo' => $contacto->tipo->value,
                'valor' => $contacto->valor,
                'etiqueta' => $contacto->etiqueta,
                'es_principal' => false,
                'activo' => true,
                'origen' => $origen,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ];
        }

        // `insertOrIgnore` contra el unique (persona, tipo, valor): reimportar el
        // mismo fichero no duplica nada y no hace falta consultar antes.
        $insertados = $this->db->table('contactos')->insertOrIgnore($filas);

        if (! $yaTienePrincipal && $insertados > 0) {
            $primero = $this->db->table('contactos')
                ->where('persona_id', $personaId)
                ->where('tipo', 'telefono')
                ->whereNull('eliminada_en')
                ->orderBy('id')
                ->value('id');

            if ($primero !== null) {
                $this->db->table('contactos')->where('id', $primero)->update(['es_principal' => true]);
            }
        }

        return $insertados;
    }
}
