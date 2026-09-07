<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\ValueObjects;

/**
 * Saca contactos de una celda de texto libre.
 *
 * Vive en el dominio y no en el job de importación porque decidir qué es un
 * teléfono panameño válido, dónde parte una celda con varios y qué se descarta
 * es una regla de negocio (§13.4), no fontanería de importación.
 *
 * Las tres formas que aparecen de verdad en la base, no inventadas:
 *
 *  1. Un valor por columna — `tel_1`..`tel_5`, `emails`, `celular_codeudor` en
 *     los dos proyectos de cobranza grandes: `65500723`, `3979025`.
 *  2. Varios en una celda, separados por espacios —el campo `telefonos` del
 *     proyecto de Banco Azteca—:
 *     `"61750650   65976897   66214303"`.
 *  3. Nombre y número emparejados, separados por comas —el campo `referencias`
 *     del mismo proyecto—:
 *     `"GUSTAVO GARRIDO(61750650), MIRIAM ANGEL BILL (65976897)"`.
 *     Esta forma es la única que trae etiqueta, y la etiqueta es el nombre de
 *     quien contesta: se conserva.
 *
 * Panamá: los móviles tienen 8 dígitos y empiezan por 6; los fijos, 7. El
 * prefijo internacional `507` se descarta si viene delante. Lo que no encaja se
 * tira en silencio: en la muestra real hay `000000` y cadenas de 9 dígitos que
 * son dos números pegados, y adivinar dónde parten sería inventarse un teléfono.
 */
final readonly class ExtractorDeContactos
{
    /** Separadores que aparecen de verdad entre números de una misma celda. */
    private const SEPARADORES = '/[\s,;\/|]+/u';

    /** `NOMBRE(numero)` o `NOMBRE (numero)`, separados por comas. */
    private const PAR_ETIQUETADO = '/([^(),]+?)\s*\(\s*([\d\s\-+]{6,20})\s*\)/u';

    /**
     * @return list<ContactoExtraido>
     */
    public function telefonos(string $bruto, ?string $etiqueta = null): array
    {
        $vistos = [];
        $numeros = [];

        foreach (preg_split(self::SEPARADORES, trim($bruto)) ?: [] as $token) {
            $numero = self::normalizarTelefono((string) $token);

            if ($numero === null || isset($vistos[$numero])) {
                continue;
            }

            $vistos[$numero] = true;
            $numeros[] = $numero;
        }

        // La etiqueta del campo sólo sirve cuando distingue a UN número: en
        // `celular_codeudor` dice de quién es, en `telefonos` diría «Teléfonos»
        // en los seis contactos de la misma celda, que es ruido.
        $etiquetaUtil = count($numeros) === 1 ? $etiqueta : null;

        return array_map(
            fn (string $numero): ContactoExtraido => new ContactoExtraido(TipoContacto::TELEFONO, $numero, $etiquetaUtil),
            $numeros,
        );
    }

    /**
     * @return list<ContactoExtraido>
     */
    public function correos(string $bruto): array
    {
        $vistos = [];
        $salida = [];

        foreach (preg_split(self::SEPARADORES, trim($bruto)) ?: [] as $token) {
            $correo = mb_strtolower(trim((string) $token));

            if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false || mb_strlen($correo) > 250) {
                continue;
            }

            if (isset($vistos[$correo])) {
                continue;
            }

            $vistos[$correo] = true;
            $salida[] = new ContactoExtraido(TipoContacto::CORREO, $correo);
        }

        return $salida;
    }

    /**
     * Referencias: nombre y número emparejados. Si la celda no trae paréntesis,
     * se trata como una lista de teléfonos sin nombre.
     *
     * @return list<ContactoExtraido>
     */
    public function referencias(string $bruto): array
    {
        if (preg_match_all(self::PAR_ETIQUETADO, $bruto, $pares, PREG_SET_ORDER) === 0) {
            return $this->telefonos($bruto);
        }

        $vistos = [];
        $salida = [];

        foreach ($pares as $par) {
            $numero = self::normalizarTelefono($par[2]);

            if ($numero === null || isset($vistos[$numero])) {
                continue;
            }

            $nombre = self::normalizarNombre($par[1]);
            $vistos[$numero] = true;
            $salida[] = new ContactoExtraido(TipoContacto::TELEFONO, $numero, $nombre);
        }

        return $salida;
    }

    /**
     * Normaliza un número panameño, o devuelve null si no lo es.
     *
     * Se descartan a propósito, y no es una lista arbitraria: son los casos que
     * aparecen en la base.
     */
    public static function normalizarTelefono(string $bruto): ?string
    {
        $digitos = preg_replace('/\D+/', '', $bruto) ?? '';

        // Prefijo internacional de Panamá delante de un número completo.
        if (str_starts_with($digitos, '507') && (strlen($digitos) === 10 || strlen($digitos) === 11)) {
            $digitos = substr($digitos, 3);
        }

        if (strlen($digitos) !== 7 && strlen($digitos) !== 8) {
            return null;
        }

        // `0000000`, `1111111`: relleno, no un teléfono.
        if (preg_match('/^(\d)\1+$/', $digitos) === 1) {
            return null;
        }

        // Un móvil panameño de 8 dígitos empieza por 6; un fijo tiene 7.
        if (strlen($digitos) === 8 && $digitos[0] !== '6') {
            return null;
        }

        return $digitos;
    }

    private static function normalizarNombre(string $bruto): ?string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $bruto) ?? '');
        $nombre = trim($nombre, " \t\n\r\0\x0B,;.-");

        if ($nombre === '' || mb_strlen($nombre) < 2) {
            return null;
        }

        return mb_substr($nombre, 0, 100);
    }
}
