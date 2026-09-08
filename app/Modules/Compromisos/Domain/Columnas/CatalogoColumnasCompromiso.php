<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Columnas;

/**
 * Catálogo cerrado de lo que aporta cada tipo de compromiso a la exportación.
 *
 * Un proyecto es de un solo tipo (§1), así que el tipo de operación decide qué
 * tabla CTI se une, qué catálogo resuelve el nombre de su FK y qué columnas
 * salen. Mismo criterio que `CatalogoColumnasCaso`: se elige por clave, nunca
 * se compone con entrada del usuario.
 *
 * Alias fijos: `cti` la tabla del tipo, `cat` el catálogo que referencia.
 */
final class CatalogoColumnasCompromiso
{
    /** @var list<string> Columnas comunes a todos los tipos, en el orden del CSV. */
    public const BASE = [
        'compromiso_public_id', 'tipo_compromiso', 'estado',
        'fecha_vencimiento', 'fecha_resolucion', 'creada_en',
        'caso_public_id', 'tipo_caso',
        'identificacion', 'nombres', 'apellidos', 'razon_social',
        'usuario',
    ];

    public static function tablaCti(string $tipoOperacion): ?string
    {
        return match ($tipoOperacion) {
            'cobranza' => 'compromisos_promesa_pago',
            'cx' => 'compromisos_resolucion_ticket',
            'venta' => 'compromisos_cierre_venta',
            'servicio' => 'compromisos_accion_servicio',
            default => null,
        };
    }

    /**
     * El catálogo por proyecto que la tabla CTI referencia, para unir su nombre
     * en vez de exportar un id que a nadie le dice nada.
     *
     * @return array{tabla: string, fk: string}|null
     */
    public static function catalogoDelTipo(string $tipoOperacion): ?array
    {
        return match ($tipoOperacion) {
            'cobranza' => ['tabla' => 'tipos_pago', 'fk' => 'cti.tipo_pago_id'],
            'cx' => ['tabla' => 'niveles_escalamiento', 'fk' => 'cti.nivel_escalamiento_id'],
            'venta' => ['tabla' => 'etapas_embudo', 'fk' => 'cti.etapa_embudo_id'],
            'servicio' => ['tabla' => 'tipos_accion_servicio', 'fk' => 'cti.tipo_accion_servicio_id'],
            default => null,
        };
    }

    /** @return list<ColumnaCompromiso> */
    public static function especificasDe(string $tipoOperacion): array
    {
        return match ($tipoOperacion) {
            'cobranza' => [
                new ColumnaCompromiso('monto', 'cti.monto'),
                new ColumnaCompromiso('moneda', 'cti.moneda'),
                new ColumnaCompromiso('tipo_pago', 'cat.nombre'),
            ],
            'cx' => [
                new ColumnaCompromiso('accion_comprometida', 'cti.accion_comprometida'),
                new ColumnaCompromiso('nivel_escalamiento', 'cat.nombre'),
            ],
            'venta' => [
                new ColumnaCompromiso('monto_cierre', 'cti.monto_cierre'),
                new ColumnaCompromiso('moneda', 'cti.moneda'),
                new ColumnaCompromiso('etapa_embudo', 'cat.nombre'),
            ],
            'servicio' => [
                new ColumnaCompromiso('descripcion_accion', 'cti.descripcion_accion'),
                new ColumnaCompromiso('tecnico_asignado', 'cti.tecnico_asignado'),
                new ColumnaCompromiso('tipo_accion', 'cat.nombre'),
            ],
            default => [],
        };
    }
}
