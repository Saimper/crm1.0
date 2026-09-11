<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Application\Listeners;

use App\Modules\Compromisos\Domain\Events\CompromisoRoto;
use App\Support\Database\CarterasOperativas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Un compromiso roto es trabajo, no un dato de archivo: hay que llamar y saber
 * por qué no se cumplió.
 *
 * Antes de cerrar el ciclo, el aviso era `compromiso_vencido` y se generaba
 * mientras la promesa seguía pendiente pasada su fecha. Al romperla, ese estado
 * desaparece y con él desaparecería el aviso, dejando el caso sin señal justo
 * cuando más falta hace. Esto la sustituye.
 *
 * Avisa al dueño del compromiso —quien lo consiguió—, tanto si lo rompió la
 * tarea diaria como si lo marcó un supervisor a mano: en los dos casos es quien
 * tiene que hacer la llamada. `insertOrIgnore` sobre el único
 * (proyecto, destinatario, tipo, entidad) evita repetirlo.
 */
final readonly class NotificarCompromisoRoto
{
    public function handle(CompromisoRoto $evento): void
    {
        $compromiso = CarterasOperativas::filtrarVinculados(DB::table('compromisos'), 'compromisos')
            ->where('id', $evento->compromisoId)
            ->where('proyecto_id', $evento->proyectoId)
            ->where('caso_id', $evento->casoId)
            ->whereNull('eliminada_en')
            ->first(['tipo_compromiso', 'fecha_vencimiento']);

        if ($compromiso === null) {
            return;
        }

        try {
            DB::table('notificaciones')->insert([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $evento->proyectoId,
                'destinatario_usuario_id' => $evento->usuarioId,
                'tipo' => 'compromiso_roto',
                'entidad_tipo' => 'compromisos',
                'entidad_id' => $evento->compromisoId,
                'titulo' => 'Compromiso roto: hay que llamar',
                'mensaje' => sprintf(
                    'El compromiso (%s) venció el %s y no se cumplió. Contacta para saber por qué.',
                    (string) $compromiso->tipo_compromiso,
                    (string) $compromiso->fecha_vencimiento,
                ),
                'metadata' => json_encode([
                    'caso_id' => $evento->casoId,
                    'tipo_compromiso' => (string) $compromiso->tipo_compromiso,
                    'fecha_vencimiento' => (string) $compromiso->fecha_vencimiento,
                    'fecha_resolucion' => $evento->fechaResolucion->format('Y-m-d'),
                ], JSON_UNESCAPED_UNICODE),
                'creada_en' => now(),
            ]);
        } catch (QueryException $e) {
            // Sólo se traga el duplicado, que es el caso legítimo: el único
            // (proyecto, destinatario, tipo, entidad) impide avisar dos veces
            // del mismo compromiso.
            //
            // `insertOrIgnore` habría sido más corto y fue lo primero que se
            // escribió, pero INSERT IGNORE de MySQL degrada TODOS los errores a
            // avisos, no sólo el de clave duplicada. Ya pasó una vez aquí: con
            // la migración del enum sin aplicar, los 49 avisos se perdieron sin
            // dejar rastro y el comando informó de éxito.
            if (! $this->esDuplicado($e)) {
                throw $e;
            }
        }
    }

    private function esDuplicado(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
