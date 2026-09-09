<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\Importaciones\Domain\Exceptions\MensajeAptoParaPantalla;
use App\Modules\Importaciones\Domain\ValueObjects\FalloDescrito;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Convierte cualquier excepción en algo que se puede guardar y enseñar.
 *
 * Nació de una importación real: `error_global` guardaba la `QueryException`
 * cruda —«SQLSTATE[21S01]… SQL: insert into `personas` (…) values (8-990-429,
 * 'ALEXIS SANTOS', …)»—, es decir, el INSERT completo con los datos de la
 * persona, en una columna que se pinta en pantalla y que cualquiera con acceso
 * a la base lee sin querer. Y por fila pasaba lo mismo con `mensaje_error`.
 *
 * La regla es una LISTA BLANCA (`MensajeAptoParaPantalla`), no una jerarquía:
 * las excepciones de dominio de otros módulos interpolan valores
 * (`DatosContactoInvalidos('Correo inválido: x@y')`), así que «DomainException
 * pasa» habría sido la misma fuga con otro nombre.
 *
 *  - Marcada como apta → su texto, tal cual.
 *  - `QueryException` → SQLSTATE y código del driver, nunca el mensaje.
 *  - Cualquier otra → el nombre corto de la clase.
 *
 * Es el ÚNICO sitio que escribe al log: con la referencia, la clase, el
 * archivo:línea y el contexto, más el SQL con placeholders (SIN bindings) para
 * las de base de datos y el mensaje redactado para el resto. Las aptas no se
 * loguean: su texto ya es el diagnóstico completo y son, en la práctica,
 * validaciones que el supervisor corrige en el wizard, no incidentes.
 */
final readonly class DescriptorDeFalloImportacion
{
    private const LARGO_MOTIVO_FILA = 200;

    /**
     * Describe el fallo de una importación entera y deja el detalle en el log.
     *
     * @param  array<string, int|string|null>  $contexto  `importacion_id`, `proyecto_id`, `lote`… Sólo ids.
     */
    public function describir(Throwable $e, array $contexto = []): FalloDescrito
    {
        $referencia = self::referencia();

        if ($e instanceof MensajeAptoParaPantalla) {
            return new FalloDescrito($e->getMessage(), $referencia);
        }

        if ($e instanceof QueryException) {
            // El SQL va con placeholders y sin bindings. Y el tercer elemento
            // de errorInfo es el mensaje del driver, que en un «Duplicate
            // entry '8-990-429'» lleva el dato: se redacta como cualquier otro.
            $this->registrar($e, $referencia, $contexto, [
                'sql' => $e->getSql(),
                'error_info' => [
                    self::sqlstate($e),
                    self::codigoDelDriver($e),
                    self::redactar((string) ($e->errorInfo[2] ?? '')),
                ],
            ]);

            return new FalloDescrito(sprintf(
                'La base de datos rechazó el lote (SQLSTATE %s, error %s). Referencia %s.',
                self::sqlstate($e),
                self::codigoDelDriver($e),
                $referencia,
            ), $referencia);
        }

        $this->registrar($e, $referencia, $contexto, [
            'mensaje' => self::redactar($e->getMessage()),
        ]);

        return new FalloDescrito(sprintf(
            'Fallo interno (%s). Referencia %s.',
            self::nombreCorto($e),
            $referencia,
        ), $referencia);
    }

    /**
     * El motivo que se guarda en `mensaje_error` cuando falla UNA fila.
     *
     * Aquí sí pasan `DomainException` e `InvalidArgumentException`: son los
     * value objects («Los días de mora (99999) superan los 40 años…») y lo que
     * interpolan es el valor de la celda que hay que corregir, que el
     * supervisor va a ver de todos modos en el CSV de rechazadas. Lo que no
     * pasa nunca es el mensaje de base de datos, que trae la fila entera.
     *
     * No se loguea: una importación de 8.000 filas con un archivo mal hecho
     * son 8.000 líneas iguales, y el motivo ya queda en la fila.
     */
    public function motivoDeFila(Throwable $e): string
    {
        if ($e instanceof MensajeAptoParaPantalla) {
            return mb_substr($e->getMessage(), 0, self::LARGO_MOTIVO_FILA);
        }

        if ($e instanceof QueryException) {
            return sprintf(
                'La base de datos rechazó la fila (SQLSTATE %s, error %s).',
                self::sqlstate($e),
                self::codigoDelDriver($e),
            );
        }

        if ($e instanceof DomainException || $e instanceof InvalidArgumentException) {
            return mb_substr($e->getMessage(), 0, self::LARGO_MOTIVO_FILA);
        }

        return sprintf('Fallo interno (%s).', self::nombreCorto($e));
    }

    /**
     * Deja fuera lo que puede ser un dato: lo que va entre comillas simples
     * (así cita MySQL los valores) y cualquier tira de seis o más dígitos
     * (identificaciones, teléfonos, números de préstamo).
     */
    public static function redactar(string $mensaje): string
    {
        $sinCitas = (string) preg_replace("/'[^']*'/", '…', $mensaje);

        return (string) preg_replace('/\d{6,}/', '#', $sinCitas);
    }

    /**
     * @param  array<string, int|string|null>  $contexto
     * @param  array<string, mixed>  $detalle
     */
    private function registrar(Throwable $e, string $referencia, array $contexto, array $detalle): void
    {
        Log::error("importacion: fallo {$referencia}", array_merge([
            'referencia' => $referencia,
            'excepcion' => $e::class,
            'en' => $e->getFile().':'.$e->getLine(),
            'importacion_id' => $contexto['importacion_id'] ?? null,
            'proyecto_id' => $contexto['proyecto_id'] ?? null,
            'lote' => $contexto['lote'] ?? null,
        ], $detalle));
    }

    private static function referencia(): string
    {
        return bin2hex(random_bytes(4));
    }

    private static function sqlstate(QueryException $e): string
    {
        $sqlstate = (string) ($e->errorInfo[0] ?? $e->getCode());

        return $sqlstate === '' || $sqlstate === '0' ? '?' : $sqlstate;
    }

    private static function codigoDelDriver(QueryException $e): string
    {
        $codigo = $e->errorInfo[1] ?? null;

        return $codigo === null ? '?' : (string) $codigo;
    }

    private static function nombreCorto(Throwable $e): string
    {
        $partes = explode('\\', $e::class);

        return (string) end($partes);
    }
}
