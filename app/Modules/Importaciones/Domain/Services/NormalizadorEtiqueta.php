<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Services;

/**
 * Normaliza nombres de columnas (del Excel) a etiquetas legibles para la UI.
 *
 * Ejemplos:
 *   "nombre_cliente"  → "Nombre"
 *   "numero_prestamo" → "Número de Préstamo"
 *   "fecha_nac"       → "Fecha de Nacimiento"
 *   "tipo_doc"        → "Tipo de Documento"
 *   "codigo_ticket"   → "Código de Ticket"
 */
final readonly class NormalizadorEtiqueta
{
    /** Sufijos contextuales que se eliminan del final del nombre */
    private const SUFIJOS_ELIMINAR = [
        'cliente', 'persona', 'deudor', 'cartera', 'caso',
        'del', 'de_la', 'de_los', 'de_las', 'del_cliente',
        'de_la_persona', 'deudor', 'del_deudor',
    ];

    /** Mapeo de palabras a su forma con tildes y capitalización correcta */
    private const PALABRAS = [
        'id' => 'ID',
        'url' => 'URL',
        'iva' => 'IVA',
        'rfid' => 'RFID',
        'sku' => 'SKU',
        'num' => 'Número',
        'nro' => 'Número',
        'no' => 'Número',
        'tel' => 'Teléfono',
        'telf' => 'Teléfono',
        'tlf' => 'Teléfono',
        'cel' => 'Celular',
        'email' => 'Correo Electrónico',
        'cod' => 'Código',
        'codigo' => 'Código',
        'desc' => 'Descripción',
        'descripcion' => 'Descripción',
        'obs' => 'Observación',
        'observacion' => 'Observación',
        'dir' => 'Dirección',
        'direccion' => 'Dirección',
        'nom' => 'Nombre',
        'nombre' => 'Nombre',
        'nombres' => 'Nombres',
        'ape' => 'Apellido',
        'apellido' => 'Apellido',
        'apellidos' => 'Apellidos',
        'numero' => 'Número',
        'prestamo' => 'Préstamo',
        'credito' => 'Crédito',
        'ticket' => 'Ticket',
        'lead' => 'Lead',
        'servicio' => 'Servicio',
        'telefono' => 'Teléfono',
        'identificacion' => 'Identificación',
        'correo' => 'Correo',
        'fecha' => 'Fecha',
        'fechanac' => 'Fecha de Nacimiento',
        'fechanacimiento' => 'Fecha de Nacimiento',
        'fvenc' => 'Fecha de Vencimiento',
        'fecha_venc' => 'Fecha de Vencimiento',
        'ultima' => 'Última',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'monto' => 'Monto',
        'saldo' => 'Saldo',
        'deuda' => 'Deuda',
        'cuota' => 'Cuota',
        'plazo' => 'Plazo',
        'interes' => 'Interés',
        'moneda' => 'Moneda',
        'estado' => 'Estado',
        'ciudad' => 'Ciudad',
        'provincia' => 'Provincia',
        'pais' => 'País',
        'nacionalidad' => 'Nacionalidad',
        'genero' => 'Género',
        'profesion' => 'Profesión',
        'edad' => 'Edad',
        'ingreso' => 'Ingreso',
        'egreso' => 'Egreso',
        'garante' => 'Garante',
        'codeudor' => 'Codeudor',
    ];

    public function sugerir(string $nombreOriginal): string
    {
        $snake = $this->aSnakeCase($nombreOriginal);
        $partes = explode('_', $snake);

        $partes = $this->eliminarSufijos($partes);
        $partes = $this->eliminarPrefijosRedundantes($partes);

        if ($partes === []) {
            return ucfirst($snake);
        }

        $etiqueta = $this->unirConectando($partes);

        return $this->capitalizar($etiqueta);
    }

    /**
     * @param  list<string>  $partes
     * @return list<string>
     */
    private function eliminarSufijos(array $partes): array
    {
        while ($partes !== []) {
            $ultimo = end($partes);
            if ($ultimo === 'del' || $ultimo === 'de') {
                array_pop($partes);

                continue;
            }
            $candidato = implode('_', $partes);
            foreach (self::SUFIJOS_ELIMINAR as $sufijo) {
                if ($this->terminaCon($candidato, $sufijo)) {
                    array_pop($partes);

                    continue 2;
                }
            }

            break;
        }

        return $partes;
    }

    /**
     * @param  list<string>  $partes
     * @return list<string>
     */
    private function eliminarPrefijosRedundantes(array $partes): array
    {
        if ($partes === []) {
            return $partes;
        }

        if (in_array($partes[0], ['del', 'de_la', 'de_los', 'de_las', 'de'], true)) {
            array_shift($partes);
        }

        return $partes;
    }

    /**
     * @param  list<string>  $partes
     */
    private function unirConectando(array $partes): string
    {
        $conectados = [];
        $total = count($partes);

        foreach ($partes as $i => $parte) {
            $resuelta = self::PALABRAS[$parte] ?? ucfirst($parte);

            if ($i > 0 && $this->necesitaConector($parte, $partes[$i - 1] ?? null, $i, $total)) {
                $conectados[] = 'de';
            }

            $conectados[] = $resuelta;
        }

        return implode(' ', $conectados);
    }

    private function necesitaConector(string $parte, ?string $anterior, int $indice, int $total): bool
    {
        $temporales = [
            'nacimiento', 'nac', 'vencimiento', 'venc',
            'expiracion', 'exp', 'creacion',
            'actualizacion', 'modificacion',
        ];

        if (! in_array(mb_strtolower($parte), $temporales, true)) {
            return false;
        }

        $fechas = ['fecha', 'fechahora', 'fecha_hora', 'fechatope', 'fecha_tope'];

        return $anterior !== null && in_array(mb_strtolower($anterior), $fechas, true);
    }

    private function capitalizar(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    private function terminaCon(string $texto, string $sufijo): bool
    {
        return str_ends_with($texto, '_'.$sufijo) || $texto === $sufijo;
    }

    private function aSnakeCase(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = (string) preg_replace('/\p{Mn}/u', '', $s);
        $s = (string) preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim($s, '_');
        $s = (string) preg_replace('/_+/', '_', $s);

        return $s;
    }
}
