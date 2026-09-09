<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

/**
 * Deja el texto de un archivo en UTF-8 legible antes de que entre en la base.
 *
 * Lo que había en el proyecto 8 —26 personas y 29 valores de campos
 * personalizados— son DOS patrones distintos, y conviene tenerlos claros
 * porque uno se arregla y el otro no:
 *
 *  1. «Ã» + carácter de control C1: GONZÃ\x81LEZ, DOMÃ\x8DNGUEZ. Es la doble
 *     codificación clásica —un UTF-8 leído como latin1 y vuelto a codificar—
 *     y es reversible: los dos caracteres son exactamente los dos bytes del
 *     original. Se deshace pasando el par a Windows-1252 y leyéndolo como UTF-8.
 *
 *  2. «Ã» + «Ñ» literal: CEDEÃÑO, RUBÃÑN, NÃÑÃÑEZ. Aguas arriba alguien
 *     sustituyó el byte de continuación (0x89, 0x91, 0x9A) por una Ñ, así que
 *     É, Ñ y Ú colapsaron en la misma secuencia y ya no hay forma de saber cuál
 *     era. NUNCA se mapea «ÃÑ» → «Ñ»: acertaría 3 de 5 y corrompería en
 *     silencio RUBÉN y NÚÑEZ. Un apellido mal codificado se ve; uno cambiado
 *     por otro no.
 *
 * Todo con mbstring y no con iconv: iconv devuelve `false` con U+0081 y U+008D,
 * que son justamente los caracteres del patrón 1.
 */
final readonly class NormalizadorEncoding
{
    /**
     * El contenido de un archivo, en UTF-8.
     *
     * Si ya lo es se devuelve tal cual. Si no, se asume Windows-1252, que es
     * el superconjunto de latin1 con el que Excel guarda un CSV «sin Unicode»
     * en las máquinas de los clientes.
     */
    public function aUtf8(string $contenido): string
    {
        if (mb_check_encoding($contenido, 'UTF-8')) {
            return $contenido;
        }

        return mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    /**
     * Deshace la doble codificación de una celda, y sólo donde es reversible.
     *
     * Trabaja par a par: «Â» o «Ã» más un carácter, y «â» más dos (los
     * caracteres de tres bytes, como las comillas tipográficas). Cada trozo se
     * lleva a Windows-1252 y sólo se sustituye si el resultado es UTF-8 válido
     * de exactamente un carácter; si no, se deja como estaba. Así «ÃÑ» (patrón
     * 2) y un «JOÃO» legítimo se quedan intactos.
     */
    public function repararDobleCodificacion(string $celda): string
    {
        if (! str_contains($celda, 'Ã') && ! str_contains($celda, 'Â') && ! str_contains($celda, 'â')) {
            return $celda;
        }

        $reparada = preg_replace_callback(
            '/[\x{00C2}\x{00C3}].|\x{00E2}../su',
            static function (array $coincidencia): string {
                $trozo = $coincidencia[0];
                $bytes = mb_convert_encoding($trozo, 'Windows-1252', 'UTF-8');

                if (! mb_check_encoding($bytes, 'UTF-8') || mb_strlen($bytes, 'UTF-8') !== 1) {
                    return $trozo;
                }

                return $bytes;
            },
            $celda,
        );

        return $reparada ?? $celda;
    }

    /**
     * Una celda tal como debe entrar en la base: sin doble codificación y sin
     * la comilla que neutraliza fórmulas. Es lo que aplican los dos lectores.
     */
    public function limpiarCelda(string $celda): string
    {
        return $this->desneutralizarFormula($this->repararDobleCodificacion($celda));
    }

    /**
     * Quita la comilla que `RespuestaCsv::celda()` antepone a una celda que
     * Excel leería como fórmula (`=`, `+`, `-`, `@`, tabulador, retorno).
     *
     * Existe por el viaje de ida y vuelta de las filas rechazadas: el CRM las
     * descarga neutralizadas, el supervisor las corrige y las vuelve a subir,
     * y sin esto un «-» o un «+507…» entrarían con la comilla delante y ya no
     * casarían con nada. Sólo se quita si lo que sigue es uno de esos
     * caracteres; una comilla suelta es texto y se respeta.
     */
    public function desneutralizarFormula(string $celda): string
    {
        if (strlen($celda) < 2 || $celda[0] !== "'") {
            return $celda;
        }

        return str_contains("=+-@\t\r", $celda[1]) ? substr($celda, 1) : $celda;
    }

    /** Si una celda sigue teniendo marcas de doble codificación tras repararla. */
    public function pareceDobleCodificada(string $celda): bool
    {
        return str_contains($celda, 'Ã') || str_contains($celda, 'Â') || str_contains($celda, 'â');
    }
}
