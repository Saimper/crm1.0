<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Catalogo;

/**
 * Sinónimos de encabezado → campo del sistema.
 *
 * Vive en Domain porque lo consumen dos caminos: la inferencia del esquema al
 * subir un archivo, y el rescate de importaciones antiguas que mandaron a
 * Campos Personalizados datos que sí tienen columna nativa.
 *
 * La comparación es sobre el nombre normalizado (minúsculas, sin espacios,
 * guiones ni guiones bajos), de modo que "NOMBRE DEL TITULAR",
 * "nombre_del_titular" y "Nombre-Del-Titular" resuelven al mismo campo.
 */
final class SinonimosCampoSistema
{
    /**
     * @var array<string, list<string>>
     */
    public const MAPA = [
        'identificacion' => [
            'cedula', 'cédula', 'ced', 'documento', 'doc',
            'dni', 'id', 'identificacion', 'identificación',
            'nit', 'ruc', 'pasaporte', 'curp',
            'num_documento', 'nro_documento', 'nrodocumento',
            'numerodocumento', 'numerodedocumento',
            'identificaciondelcliente', 'documentodeidentidad',
            'cedulacliente', 'ceduladelcliente', 'cedulatitular',
        ],
        'tipo_identificacion_codigo' => [
            'tipoidentificacion', 'tipoidentificación', 'tipodocumento',
            'tipodedocumento', 'tipodoc', 'tipo_documento',
            'tipo_identificacion', 'tipo_de_identificacion',
            'tipo_de_documento', 'tipo_doc',
        ],
        'nombres' => [
            'nombre', 'nombres', 'nombrecompleto', 'nombredeltitular',
            'nombretitular', 'titular', 'nombredelcliente', 'nombrecliente',
            'cliente', 'nombreyapellido', 'nombresyapellidos', 'nombreapellido',
            'deudor', 'nombredeudor', 'nombredeldeudor', 'name', 'fullname',
        ],
        'apellidos' => [
            'apellido', 'apellidos', 'apellidocompleto', 'apellidopaterno',
            'apellidosdelcliente', 'apellidocliente', 'lastname', 'surname',
        ],
        'razon_social' => [
            'razonsocial', 'razón social', 'razonsocialcliente', 'empresa',
            'nombreempresa', 'compania', 'compañia', 'compañía', 'company',
        ],
        'saldo_total' => [
            'saldototal', 'saldo', 'saldoactual', 'saldovigente', 'deuda',
            'deudatotal', 'totaldeuda', 'montototal', 'totaladeudado',
            'saldoadeudado', 'saldopendiente',
        ],
        'saldo_capital' => [
            'capital', 'saldocapital', 'montocapital', 'capitalpendiente',
            'capitaladeudado', 'principal',
        ],
        'saldo_interes' => [
            'interes', 'interés', 'intereses', 'saldointeres', 'saldointereses',
            'interesespendientes', 'montointeres',
        ],
        'monto_original' => [
            'montooriginal', 'montoriginal', 'valororiginal', 'montodesembolsado',
            'montootorgado', 'montoprestamo', 'montocredito', 'valorcredito',
        ],
        'cuota_mensual' => [
            'cuota', 'cuotamensual', 'cuotadepago', 'valorcuota', 'montocuota',
            'cuotapactada', 'pagomensual',
        ],
        'dias_mora' => [
            'diasmora', 'diasenmora', 'diasdemora', 'diasatraso', 'diasenatraso',
            'diasdeatraso', 'diasvencidos', 'mora', 'atraso', 'diasvencido',
        ],
        'cuotas_totales' => [
            'cuotastotales', 'totalcuotas', 'numerocuotas', 'nrocuotas', 'plazo',
        ],
        'cuotas_pagadas' => [
            'cuotaspagadas', 'cuotascanceladas', 'cuotasabonadas',
        ],
        'fecha_desembolso' => [
            'fechadesembolso', 'fechadeotorgamiento', 'fechaotorgamiento',
            'fechacredito', 'fechaprestamo',
        ],
        'fecha_vencimiento' => [
            'fechavencimiento', 'fechadevencimiento', 'fechavence', 'vencimiento',
        ],
        'asunto' => [
            'asunto', 'titulo', 'título', 'subject', 'motivo', 'tema',
        ],
        'descripcion' => [
            'descripcion', 'descripción', 'detalle', 'observacion', 'observación',
            'description', 'comentario',
        ],
        'fecha_reporte' => [
            'fechareporte', 'fechadereporte', 'fechaapertura', 'fechacreacion',
        ],
        'fecha_limite_sla' => [
            'fechalimitesla', 'fechasla', 'limitesla', 'vencimientosla',
        ],
        'valor_estimado_monto' => [
            'valorestimado', 'montoestimado', 'valorpotencial', 'montopotencial',
            'valoroportunidad',
        ],
        'origen_lead' => [
            'origen', 'origenlead', 'fuente', 'canalorigen', 'fuentelead',
        ],
        'fecha_primer_contacto' => [
            'fechaprimercontacto', 'primercontacto', 'fechacontacto',
        ],
        'fecha_estimada_cierre' => [
            'fechaestimadacierre', 'fechacierreestimada', 'cierreestimado',
        ],
        'direccion_servicio' => [
            'direccion', 'dirección', 'direccionservicio', 'direcciondelservicio',
            'domicilio', 'ubicacion', 'ubicación',
        ],
        'tecnico_asignado' => [
            'tecnico', 'técnico', 'tecnicoasignado', 'tecnicoresponsable',
        ],
        'fecha_solicitud' => [
            'fechasolicitud', 'fechadesolicitud', 'fecharequerimiento',
        ],
        'fecha_programada' => [
            'fechaprogramada', 'fechaagendada', 'fechavisita', 'fechaatencion',
        ],
    ];

    /**
     * Campo del sistema al que corresponde un encabezado, o null si no hay match.
     */
    public static function buscar(string $encabezado): ?string
    {
        $normalizado = self::normalizar($encabezado);

        foreach (self::MAPA as $codigo => $sinonimos) {
            if ($normalizado === self::normalizar($codigo) || in_array($normalizado, $sinonimos, true)) {
                return $codigo;
            }
        }

        return null;
    }

    public static function normalizar(string $valor): string
    {
        $valor = mb_strtolower($valor);

        return (string) preg_replace('/[\s\-_]+/u', '', $valor);
    }
}
