<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Columnas;

/**
 * Catálogo cerrado de columnas que el usuario puede mostrar en el listado de casos.
 *
 * Las columnas base existen en cualquier proyecto; las específicas dependen del
 * tipo de operación y salen de la tabla CTI correspondiente. Nada aquí se compone
 * con entrada del usuario: se selecciona por clave.
 */
final class CatalogoColumnasCaso
{
    /** @var list<string> Columnas mostradas cuando el usuario no ha configurado nada */
    public const POR_DEFECTO = ['tipo', 'persona', 'identificacion', 'cartera', 'estado', 'prioridad', 'compromiso'];

    /**
     * @return list<ColumnaCaso>
     */
    public static function paraTipoOperacion(string $tipoOperacion): array
    {
        return [...self::columnasBase(), ...self::columnasDelTipo($tipoOperacion)];
    }

    /**
     * @return array<string, ColumnaCaso>
     */
    public static function indexadoPorClave(string $tipoOperacion): array
    {
        $indexado = [];

        foreach (self::paraTipoOperacion($tipoOperacion) as $columna) {
            $indexado[$columna->clave] = $columna;
        }

        return $indexado;
    }

    /**
     * Filtra una selección del usuario dejando solo claves válidas y sin repetir.
     * Si no queda ninguna, cae en las por defecto.
     *
     * @param  list<string>  $seleccion
     * @return list<string>
     */
    public static function sanear(array $seleccion, string $tipoOperacion): array
    {
        $validas = self::indexadoPorClave($tipoOperacion);
        $limpias = [];

        foreach ($seleccion as $clave) {
            if (isset($validas[$clave]) && ! in_array($clave, $limpias, true)) {
                $limpias[] = $clave;
            }
        }

        return $limpias !== [] ? $limpias : self::POR_DEFECTO;
    }

    /** @return list<ColumnaCaso> */
    private static function columnasBase(): array
    {
        return [
            new ColumnaCaso('tipo', 'Tipo', 'c.tipo_caso', fija: true),
            new ColumnaCaso('persona', 'Persona', 'p.nombres', fija: true),
            new ColumnaCaso('identificacion', 'Identificación', 'p.identificacion'),
            new ColumnaCaso('cartera', 'Cartera', 'ca.nombre'),
            new ColumnaCaso('estado', 'Estado', 'ec.nombre'),
            new ColumnaCaso('prioridad', 'Prioridad', 'c.prioridad', numerica: true),
            new ColumnaCaso('compromiso', 'Compromiso', 'c.tiene_compromiso_vigente'),
            new ColumnaCaso('ultima_gestion', 'Última gestión', 'c.fecha_ultima_gestion'),
            new ColumnaCaso('fecha_ingreso', 'Fecha de ingreso', 'c.fecha_ingreso'),
        ];
    }

    /** @return list<ColumnaCaso> */
    private static function columnasDelTipo(string $tipoOperacion): array
    {
        return match ($tipoOperacion) {
            'cobranza' => self::columnasCobranza(),
            'cx' => self::columnasCx(),
            'venta' => self::columnasVenta(),
            'servicio' => self::columnasServicio(),
            default => [],
        };
    }

    /** @return list<ColumnaCaso> */
    private static function columnasCobranza(): array
    {
        return [
            new ColumnaCaso('cuenta', 'Cuenta', 'cti.numero_prestamo', tipoOperacion: 'cobranza'),
            new ColumnaCaso('saldo_total', 'Saldo', 'cti.saldo_total', numerica: true, tipoOperacion: 'cobranza'),
            new ColumnaCaso('saldo_capital', 'Capital', 'cti.saldo_capital', numerica: true, tipoOperacion: 'cobranza'),
            new ColumnaCaso('saldo_interes', 'Intereses', 'cti.saldo_interes', numerica: true, tipoOperacion: 'cobranza'),
            new ColumnaCaso('cuota_mensual', 'Cuota', 'cti.cuota_mensual', numerica: true, tipoOperacion: 'cobranza'),
            new ColumnaCaso('dias_mora', 'Días mora', 'cti.dias_mora', numerica: true, tipoOperacion: 'cobranza'),
            new ColumnaCaso('monto_original', 'Monto original', 'cti.monto_original', numerica: true, tipoOperacion: 'cobranza'),
        ];
    }

    /** @return list<ColumnaCaso> */
    private static function columnasCx(): array
    {
        return [
            new ColumnaCaso('codigo_ticket', 'Ticket', 'cti.codigo_ticket', tipoOperacion: 'cx'),
            new ColumnaCaso('asunto', 'Asunto', 'cti.asunto', tipoOperacion: 'cx'),
            new ColumnaCaso('fecha_limite_sla', 'Límite SLA', 'cti.fecha_limite_sla', tipoOperacion: 'cx'),
        ];
    }

    /** @return list<ColumnaCaso> */
    private static function columnasVenta(): array
    {
        return [
            new ColumnaCaso('codigo_lead', 'Lead', 'cti.codigo_lead', tipoOperacion: 'venta'),
            new ColumnaCaso('valor_estimado', 'Valor estimado', 'cti.valor_estimado_monto', numerica: true, tipoOperacion: 'venta'),
            new ColumnaCaso('origen_lead', 'Origen', 'cti.origen_lead', tipoOperacion: 'venta'),
        ];
    }

    /** @return list<ColumnaCaso> */
    private static function columnasServicio(): array
    {
        return [
            new ColumnaCaso('codigo_servicio', 'Servicio', 'cti.codigo_servicio', tipoOperacion: 'servicio'),
            new ColumnaCaso('tecnico_asignado', 'Técnico', 'cti.tecnico_asignado', tipoOperacion: 'servicio'),
            new ColumnaCaso('fecha_programada', 'Programada', 'cti.fecha_programada', tipoOperacion: 'servicio'),
        ];
    }

    /**
     * Tabla CTI a unir según el tipo de operación del proyecto.
     */
    public static function tablaCti(string $tipoOperacion): ?string
    {
        return match ($tipoOperacion) {
            'cobranza' => 'casos_cobranza',
            'cx' => 'casos_ticket_cx',
            'venta' => 'casos_lead_venta',
            'servicio' => 'casos_servicio',
            default => null,
        };
    }
}
