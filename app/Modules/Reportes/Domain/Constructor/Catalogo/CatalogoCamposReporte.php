<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Catalogo;

use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\Enums\TipoCampoReporte;
use App\Modules\Reportes\Domain\Constructor\Exceptions\CampoNoPermitidoEnReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\CampoDisponible;

/**
 * Whitelist server-side de campos exponibles en el constructor.
 *
 * Cada entidad raíz declara su lista cerrada de campos con SQL ya calificado.
 * El usuario nunca aporta SQL: solo selecciona claves canónicas; el ejecutor
 * traduce clave → CampoDisponible::$sql y agrega los joins necesarios.
 *
 * Campos personalizados §7 se inyectan por proyecto vía constructor.
 */
final class CatalogoCamposReporte
{
    /**
     * @var array<string, CampoDisponible>
     */
    private array $campos;

    /**
     * @param  list<CampoDisponible>  $personalizados
     */
    public function __construct(
        public readonly EntidadRaiz $entidad,
        array $personalizados = [],
    ) {
        $base = self::camposBaseDe($entidad);
        $todos = [];
        foreach ($base as $c) {
            $todos[$c->clave] = $c;
        }
        foreach ($personalizados as $c) {
            $todos[$c->clave] = $c;
        }
        $this->campos = $todos;
    }

    public function tiene(string $clave): bool
    {
        return isset($this->campos[$clave]);
    }

    public function obtener(string $clave): CampoDisponible
    {
        if (! $this->tiene($clave)) {
            throw CampoNoPermitidoEnReporte::clave($clave, $this->entidad);
        }

        return $this->campos[$clave];
    }

    /**
     * @return array<string, CampoDisponible>
     */
    public function todos(): array
    {
        return $this->campos;
    }

    /**
     * Devuelve descriptores de LEFT JOIN para los joinKeys solicitados.
     *
     * @param  list<string>  $joinKeys
     * @return list<array{tabla: string, alias: string, col_a: string, col_b: string}>
     */
    public function joinsPara(array $joinKeys): array
    {
        $mapa = self::joinsBaseDe($this->entidad);
        $out = [];
        foreach (array_unique($joinKeys) as $k) {
            if (str_starts_with($k, 'cp:')) {
                continue;
            }
            if (! isset($mapa[$k])) {
                continue;
            }
            $out[] = $mapa[$k];
        }

        return $out;
    }

    /**
     * @return list<CampoDisponible>
     */
    private static function camposBaseDe(EntidadRaiz $entidad): array
    {
        return match ($entidad) {
            EntidadRaiz::CASOS => self::camposCasos(),
            EntidadRaiz::GESTIONES => self::camposGestiones(),
            EntidadRaiz::COMPROMISOS => self::camposCompromisos(),
            EntidadRaiz::PERSONAS => self::camposPersonas(),
        };
    }

    /**
     * @return array<string, array{tabla: string, alias: string, col_a: string, col_b: string}>
     */
    private static function joinsBaseDe(EntidadRaiz $entidad): array
    {
        return match ($entidad) {
            EntidadRaiz::CASOS => [
                'estado' => ['tabla' => 'estados_caso', 'alias' => 'ec', 'col_a' => 'ec.id', 'col_b' => 'casos.estado_caso_id'],
                'cartera' => ['tabla' => 'carteras', 'alias' => 'ca', 'col_a' => 'ca.id', 'col_b' => 'casos.cartera_id'],
                'persona' => ['tabla' => 'personas', 'alias' => 'pe', 'col_a' => 'pe.id', 'col_b' => 'casos.persona_id'],
                'cobranza' => ['tabla' => 'casos_cobranza', 'alias' => 'cc', 'col_a' => 'cc.caso_id', 'col_b' => 'casos.id'],
                'ticket_cx' => ['tabla' => 'casos_ticket_cx', 'alias' => 'ctx', 'col_a' => 'ctx.caso_id', 'col_b' => 'casos.id'],
                'usuario_ultima' => ['tabla' => 'users', 'alias' => 'uug', 'col_a' => 'uug.id', 'col_b' => 'casos.usuario_ultima_gestion_id'],
                'resultado_ultima' => ['tabla' => 'resultados', 'alias' => 'rug', 'col_a' => 'rug.id', 'col_b' => 'casos.resultado_ultima_gestion_id'],
            ],
            EntidadRaiz::GESTIONES => [
                'caso' => ['tabla' => 'casos', 'alias' => 'cs', 'col_a' => 'cs.id', 'col_b' => 'gestiones.caso_id'],
                'persona' => ['tabla' => 'personas', 'alias' => 'pe', 'col_a' => 'pe.id', 'col_b' => 'gestiones.persona_id'],
                'usuario' => ['tabla' => 'users', 'alias' => 'ug', 'col_a' => 'ug.id', 'col_b' => 'gestiones.usuario_id'],
                'canal' => ['tabla' => 'canales', 'alias' => 'cn', 'col_a' => 'cn.id', 'col_b' => 'gestiones.canal_id'],
                'tipo' => ['tabla' => 'tipos_gestion', 'alias' => 'tg', 'col_a' => 'tg.id', 'col_b' => 'gestiones.tipo_gestion_id'],
                'resultado' => ['tabla' => 'resultados', 'alias' => 'rs', 'col_a' => 'rs.id', 'col_b' => 'gestiones.resultado_id'],
                'motivo' => ['tabla' => 'motivos_no_contacto', 'alias' => 'mn', 'col_a' => 'mn.id', 'col_b' => 'gestiones.motivo_no_contacto_id'],
            ],
            EntidadRaiz::COMPROMISOS => [
                'caso' => ['tabla' => 'casos', 'alias' => 'cs', 'col_a' => 'cs.id', 'col_b' => 'compromisos.caso_id'],
                'usuario' => ['tabla' => 'users', 'alias' => 'uc', 'col_a' => 'uc.id', 'col_b' => 'compromisos.usuario_id'],
                'promesa_pago' => ['tabla' => 'compromisos_promesa_pago', 'alias' => 'cpp', 'col_a' => 'cpp.compromiso_id', 'col_b' => 'compromisos.id'],
            ],
            EntidadRaiz::PERSONAS => [
                'tipo_identificacion' => ['tabla' => 'tipos_identificacion', 'alias' => 'ti', 'col_a' => 'ti.id', 'col_b' => 'personas.tipo_identificacion_id'],
            ],
        };
    }

    /**
     * @return list<CampoDisponible>
     */
    private static function camposCasos(): array
    {
        return [
            new CampoDisponible('casos.public_id', 'ID público', TipoCampoReporte::TEXTO, 'casos.public_id'),
            new CampoDisponible('casos.tipo_caso', 'Tipo de caso', TipoCampoReporte::ENUM, 'casos.tipo_caso'),
            new CampoDisponible('casos.fecha_ingreso', 'Fecha ingreso', TipoCampoReporte::FECHA, 'casos.fecha_ingreso'),
            new CampoDisponible('casos.prioridad', 'Prioridad', TipoCampoReporte::NUMERO, 'casos.prioridad'),
            new CampoDisponible('casos.cerrado_en', 'Cerrado en', TipoCampoReporte::FECHA_HORA, 'casos.cerrado_en'),
            new CampoDisponible('casos.fecha_ultima_gestion', 'Última gestión', TipoCampoReporte::FECHA_HORA, 'casos.fecha_ultima_gestion'),
            new CampoDisponible('casos.tiene_compromiso_vigente', 'Tiene compromiso vigente', TipoCampoReporte::BOOLEANO, 'casos.tiene_compromiso_vigente'),
            new CampoDisponible('casos.creada_en', 'Creado en', TipoCampoReporte::FECHA_HORA, 'casos.creada_en'),
            new CampoDisponible('casos.estado.codigo', 'Estado código', TipoCampoReporte::TEXTO, 'ec.codigo', 'estado'),
            new CampoDisponible('casos.estado.nombre', 'Estado nombre', TipoCampoReporte::TEXTO, 'ec.nombre', 'estado'),
            new CampoDisponible('casos.cartera.codigo', 'Cartera código', TipoCampoReporte::TEXTO, 'ca.codigo', 'cartera'),
            new CampoDisponible('casos.cartera.nombre', 'Cartera nombre', TipoCampoReporte::TEXTO, 'ca.nombre', 'cartera'),
            new CampoDisponible('casos.persona.identificacion', 'Identificación', TipoCampoReporte::TEXTO, 'pe.identificacion', 'persona'),
            new CampoDisponible('casos.persona.nombres', 'Nombres', TipoCampoReporte::TEXTO, 'pe.nombres', 'persona'),
            new CampoDisponible('casos.persona.apellidos', 'Apellidos', TipoCampoReporte::TEXTO, 'pe.apellidos', 'persona'),
            new CampoDisponible('casos.persona.razon_social', 'Razón social', TipoCampoReporte::TEXTO, 'pe.razon_social', 'persona'),
            new CampoDisponible('casos.cobranza.numero_prestamo', 'Préstamo', TipoCampoReporte::TEXTO, 'cc.numero_prestamo', 'cobranza'),
            new CampoDisponible('casos.cobranza.saldo_total', 'Saldo total', TipoCampoReporte::DECIMAL, 'cc.saldo_total', 'cobranza'),
            new CampoDisponible('casos.cobranza.saldo_capital', 'Saldo capital', TipoCampoReporte::DECIMAL, 'cc.saldo_capital', 'cobranza'),
            new CampoDisponible('casos.cobranza.dias_mora', 'Días mora', TipoCampoReporte::NUMERO, 'cc.dias_mora', 'cobranza'),
            new CampoDisponible('casos.cobranza.fecha_vencimiento', 'Vencimiento préstamo', TipoCampoReporte::FECHA, 'cc.fecha_vencimiento', 'cobranza'),
            new CampoDisponible('casos.cobranza.cuotas_pagadas', 'Cuotas pagadas', TipoCampoReporte::NUMERO, 'cc.cuotas_pagadas', 'cobranza'),
            new CampoDisponible('casos.ticket_cx.codigo_ticket', 'Código ticket', TipoCampoReporte::TEXTO, 'ctx.codigo_ticket', 'ticket_cx'),
            new CampoDisponible('casos.ticket_cx.asunto', 'Asunto', TipoCampoReporte::TEXTO, 'ctx.asunto', 'ticket_cx'),
            new CampoDisponible('casos.ticket_cx.fecha_limite_sla', 'Límite SLA', TipoCampoReporte::FECHA_HORA, 'ctx.fecha_limite_sla', 'ticket_cx'),
            new CampoDisponible('casos.usuario_ultima.nombre', 'Último usuario', TipoCampoReporte::TEXTO, 'uug.name', 'usuario_ultima'),
            new CampoDisponible('casos.resultado_ultima.codigo', 'Último resultado', TipoCampoReporte::TEXTO, 'rug.codigo', 'resultado_ultima'),
        ];
    }

    /**
     * @return list<CampoDisponible>
     */
    private static function camposGestiones(): array
    {
        return [
            new CampoDisponible('gestiones.public_id', 'ID público', TipoCampoReporte::TEXTO, 'gestiones.public_id'),
            new CampoDisponible('gestiones.creada_en', 'Creada en', TipoCampoReporte::FECHA_HORA, 'gestiones.creada_en'),
            new CampoDisponible('gestiones.duracion_segundos', 'Duración (s)', TipoCampoReporte::NUMERO, 'gestiones.duracion_segundos'),
            new CampoDisponible('gestiones.notas', 'Notas', TipoCampoReporte::TEXTO, 'gestiones.notas'),
            new CampoDisponible('gestiones.caso.public_id', 'Caso ID', TipoCampoReporte::TEXTO, 'cs.public_id', 'caso'),
            new CampoDisponible('gestiones.caso.tipo_caso', 'Tipo caso', TipoCampoReporte::ENUM, 'cs.tipo_caso', 'caso'),
            new CampoDisponible('gestiones.persona.identificacion', 'Identificación', TipoCampoReporte::TEXTO, 'pe.identificacion', 'persona'),
            new CampoDisponible('gestiones.persona.nombres', 'Nombres', TipoCampoReporte::TEXTO, 'pe.nombres', 'persona'),
            new CampoDisponible('gestiones.persona.apellidos', 'Apellidos', TipoCampoReporte::TEXTO, 'pe.apellidos', 'persona'),
            new CampoDisponible('gestiones.usuario.nombre', 'Usuario', TipoCampoReporte::TEXTO, 'ug.name', 'usuario'),
            new CampoDisponible('gestiones.canal.codigo', 'Canal', TipoCampoReporte::TEXTO, 'cn.codigo', 'canal'),
            new CampoDisponible('gestiones.tipo.codigo', 'Tipo gestión', TipoCampoReporte::TEXTO, 'tg.codigo', 'tipo'),
            new CampoDisponible('gestiones.resultado.codigo', 'Resultado', TipoCampoReporte::TEXTO, 'rs.codigo', 'resultado'),
            new CampoDisponible('gestiones.motivo_no_contacto.codigo', 'Motivo no contacto', TipoCampoReporte::TEXTO, 'mn.codigo', 'motivo'),
        ];
    }

    /**
     * @return list<CampoDisponible>
     */
    private static function camposCompromisos(): array
    {
        return [
            new CampoDisponible('compromisos.public_id', 'ID público', TipoCampoReporte::TEXTO, 'compromisos.public_id'),
            new CampoDisponible('compromisos.tipo_compromiso', 'Tipo compromiso', TipoCampoReporte::ENUM, 'compromisos.tipo_compromiso'),
            new CampoDisponible('compromisos.estado', 'Estado', TipoCampoReporte::ENUM, 'compromisos.estado'),
            new CampoDisponible('compromisos.fecha_vencimiento', 'Vencimiento', TipoCampoReporte::FECHA, 'compromisos.fecha_vencimiento'),
            new CampoDisponible('compromisos.fecha_resolucion', 'Resolución', TipoCampoReporte::FECHA, 'compromisos.fecha_resolucion'),
            new CampoDisponible('compromisos.creada_en', 'Creado en', TipoCampoReporte::FECHA_HORA, 'compromisos.creada_en'),
            new CampoDisponible('compromisos.caso.public_id', 'Caso ID', TipoCampoReporte::TEXTO, 'cs.public_id', 'caso'),
            new CampoDisponible('compromisos.usuario.nombre', 'Usuario', TipoCampoReporte::TEXTO, 'uc.name', 'usuario'),
            new CampoDisponible('compromisos.promesa_pago.monto', 'Monto promesa', TipoCampoReporte::DECIMAL, 'cpp.monto', 'promesa_pago'),
            new CampoDisponible('compromisos.promesa_pago.moneda', 'Moneda', TipoCampoReporte::TEXTO, 'cpp.moneda', 'promesa_pago'),
        ];
    }

    /**
     * @return list<CampoDisponible>
     */
    private static function camposPersonas(): array
    {
        return [
            new CampoDisponible('personas.public_id', 'ID público', TipoCampoReporte::TEXTO, 'personas.public_id'),
            new CampoDisponible('personas.tipo_persona', 'Tipo', TipoCampoReporte::ENUM, 'personas.tipo_persona'),
            new CampoDisponible('personas.identificacion', 'Identificación', TipoCampoReporte::TEXTO, 'personas.identificacion'),
            new CampoDisponible('personas.nombres', 'Nombres', TipoCampoReporte::TEXTO, 'personas.nombres'),
            new CampoDisponible('personas.apellidos', 'Apellidos', TipoCampoReporte::TEXTO, 'personas.apellidos'),
            new CampoDisponible('personas.razon_social', 'Razón social', TipoCampoReporte::TEXTO, 'personas.razon_social'),
            new CampoDisponible('personas.fecha_nacimiento', 'Fecha nacimiento', TipoCampoReporte::FECHA, 'personas.fecha_nacimiento'),
            new CampoDisponible('personas.creada_en', 'Creada en', TipoCampoReporte::FECHA_HORA, 'personas.creada_en'),
            new CampoDisponible('personas.tipo_identificacion.codigo', 'Tipo ID código', TipoCampoReporte::TEXTO, 'ti.codigo', 'tipo_identificacion'),
        ];
    }
}
