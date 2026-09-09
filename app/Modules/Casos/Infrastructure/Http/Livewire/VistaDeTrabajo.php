<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\AutoasignarCaso;
use App\Modules\Asignaciones\Domain\Exceptions\AutoasignacionNoPermitida;
use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Vista de Trabajo abstracta (§9 CLAUDE.md v2).
 * Muestra identidad de la persona + lista de casos del proyecto activo + historial de gestiones.
 * No incluye UI específica por tipo de caso; cada tipo añadirá su panel en fases posteriores.
 */
final class VistaDeTrabajo extends Component
{
    public string $personaPublicId = '';

    #[Url(as: 'caso')]
    public ?string $casoPublicIdSeleccionado = null;

    public function mount(string $persona, ?string $caso = null): void
    {
        $this->personaPublicId = $persona;
        $this->casoPublicIdSeleccionado = $caso;
    }

    public string $mensajeAsignacion = '';

    /**
     * Enseñar sólo lo que llegó a ser una conversación.
     *
     * Un caso con treinta intentos y dos contactos cuenta su historia en esos
     * dos: el resto es ruido cuando lo que se busca es qué se habló la última
     * vez. No se persiste en la URL porque es una lente momentánea, no un sitio
     * al que volver.
     */
    public bool $soloEfectivas = false;

    public function alternarSoloEfectivas(): void
    {
        $this->soloEfectivas = ! $this->soloEfectivas;
    }

    public function seleccionarCaso(string $publicId): void
    {
        $this->casoPublicIdSeleccionado = $publicId;
        $this->mensajeAsignacion = '';
    }

    /**
     * El asesor toma la cuenta que tiene delante.
     *
     * Es la otra mitad de `AutoasignarCasoDesdeGestion`: aquel la asigna al
     * registrar la gestión, este deja decirlo antes —el asesor que va a llamar
     * quiere que la cuenta esté en su bandeja mientras la trabaja, no después—.
     */
    public function tomarCuenta(int $casoId, AutoasignarCaso $autoasignar): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuario = auth()->user();

        abort_unless(
            $usuario?->tienePermiso('asignaciones.autoasignarse', $proyectoId) === true,
            403,
            'No tienes permiso para tomar cuentas en este proyecto.',
        );

        try {
            $autoasignar->execute(
                proyectoId: $proyectoId,
                casoId: $casoId,
                usuarioId: (int) $usuario->id,
                ahora: new DateTimeImmutable,
                carterasPermitidas: $usuario->carterasPermitidas($proyectoId),
            );
            $this->mensajeAsignacion = __('casos.assign_taken');
        } catch (AutoasignacionNoPermitida $e) {
            $this->addError('asignacion', $e->getMessage());
        }
    }

    #[On('gestion-registrada')]
    #[On('compromiso-resuelto')]
    public function refrescar(): void
    {
        // Livewire re-renderiza automáticamente al recibir el evento.
    }

    public function render(): View
    {
        $proyectoActivo = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyectoActivo->id;

        $persona = DB::table('personas as p')
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.public_id', $this->personaPublicId)
            ->where('p.proyecto_id', $proyectoId)
            ->whereNull('p.eliminada_en')
            ->select([
                'p.id', 'p.public_id', 'p.tipo_persona',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'p.fecha_nacimiento',
                'ti.codigo as tipo_identificacion_codigo',
                'ti.nombre as tipo_identificacion_nombre',
            ])
            ->first();
        abort_unless($persona, 404);

        $casos = DB::table('casos as c')
            ->leftJoin('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->leftJoin('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->leftJoin('resultados as ru', 'ru.id', '=', 'c.resultado_ultima_gestion_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.persona_id', $persona->id)
            ->whereNull('c.eliminada_en')
            ->select([
                'c.id', 'c.public_id', 'c.tipo_caso', 'c.prioridad',
                'c.cartera_id', 'c.fecha_ingreso', 'c.cerrado_en',
                'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'ec.nombre as estado_caso_nombre', 'ec.codigo as estado_caso_codigo',
                'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo_nombre',
            ])
            ->orderByDesc('c.prioridad')
            ->orderByDesc('c.fecha_ingreso')
            ->get();

        if (
            $casos->isNotEmpty()
            && ($this->casoPublicIdSeleccionado === null
                || ! $casos->contains('public_id', $this->casoPublicIdSeleccionado))
        ) {
            $this->casoPublicIdSeleccionado = (string) $casos->first()->public_id;
        }

        $casoActivo = $casos->firstWhere('public_id', $this->casoPublicIdSeleccionado);

        $historial = collect();
        $compromisoActivo = null;

        if ($casoActivo !== null) {
            $historial = DB::table('gestiones as g')
                ->leftJoin('resultados as r', 'r.id', '=', 'g.resultado_id')
                ->leftJoin('tipos_gestion as tg', 'tg.id', '=', 'g.tipo_gestion_id')
                ->leftJoin('canales as cn', 'cn.id', '=', 'g.canal_id')
                ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
                ->leftJoin('motivos_no_contacto as mnc', 'mnc.id', '=', 'g.motivo_no_contacto_id')
                ->leftJoin('causas_gestion as cg', 'cg.id', '=', 'g.causa_id')
                ->where('g.proyecto_id', $proyectoId)
                ->where('g.caso_id', $casoActivo->id)
                ->whereNull('g.eliminada_en')
                ->when($this->soloEfectivas, fn ($q) => $q->where('r.es_contacto_efectivo', true))
                ->select([
                    'g.id', 'g.public_id', 'g.creada_en', 'g.notas', 'g.duracion_segundos',
                    'r.nombre as resultado_nombre', 'r.codigo as resultado_codigo',
                    'r.es_contacto_efectivo',
                    'tg.nombre as tipo_gestion_nombre',
                    'cn.nombre as canal_nombre',
                    'u.name as usuario_nombre',
                    'mnc.nombre as motivo_no_contacto_nombre',
                    'cg.nombre as causa_nombre',
                ])
                // Desempate por clave: dos gestiones del mismo segundo —una
                // llamada y su nota, lo normal— se turnaban entre renders, y con
                // el límite de 30 una podía entrar y salir de la lista.
                ->orderByDesc('g.creada_en')
                ->orderByDesc('g.id')
                ->limit(30)
                ->get();

            // Valores de campos personalizados ámbito gestión × tipo_gestion para
            // las gestiones del historial. Una sola query, agrupados por gestion_id.
            $valoresCamposGestion = [];
            if ($historial->isNotEmpty()) {
                $idsGestion = $historial->pluck('id')->all();
                $filas = DB::table('valores_campo_personalizado as v')
                    ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
                    ->where('c.proyecto_id', $proyectoId)
                    ->where('c.ambito', 'gestion')
                    ->whereIn('v.entidad_id', $idsGestion)
                    ->orderBy('c.orden')
                    ->orderBy('c.codigo')
                    ->select([
                        'v.entidad_id as gestion_id',
                        'c.codigo', 'c.etiqueta', 'c.tipo',
                        'v.valor_texto_corto', 'v.valor_texto_largo',
                        'v.valor_numero_entero', 'v.valor_numero_decimal',
                        'v.valor_fecha', 'v.valor_fecha_hora',
                        'v.valor_booleano', 'v.valor_moneda_monto', 'v.valor_moneda_codigo',
                    ])
                    ->get();

                foreach ($filas as $f) {
                    $valor = match ((string) $f->tipo) {
                        'texto_corto' => $f->valor_texto_corto,
                        'texto_largo' => $f->valor_texto_largo,
                        'numero_entero' => $f->valor_numero_entero,
                        'numero_decimal' => $f->valor_numero_decimal,
                        'fecha' => $f->valor_fecha
                            ? Carbon::parse($f->valor_fecha)->format('d/m/Y')
                            : null,
                        'fecha_hora' => $f->valor_fecha_hora
                            ? Carbon::parse($f->valor_fecha_hora)->format('d/m/Y H:i')
                            : null,
                        'booleano' => $f->valor_booleano === null
                            ? null
                            : ((bool) $f->valor_booleano ? 'Sí' : 'No'),
                        'moneda' => $f->valor_moneda_monto !== null
                            ? ($f->valor_moneda_codigo ?? '').' '.number_format((float) $f->valor_moneda_monto, 2, '.', ',')
                            : null,
                        default => null,
                    };

                    if ($valor === null || $valor === '') {
                        continue;
                    }

                    $gid = (int) $f->gestion_id;
                    $valoresCamposGestion[$gid] ??= [];
                    $valoresCamposGestion[$gid][] = [
                        'etiqueta' => (string) $f->etiqueta,
                        'valor' => (string) $valor,
                    ];
                }
            }

            $compromisoActivo = DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)
                ->where('caso_id', $casoActivo->id)
                ->where('estado', 'pendiente')
                ->whereNull('eliminada_en')
                ->orderBy('fecha_vencimiento')
                ->first();

            if ($compromisoActivo !== null && $compromisoActivo->tipo_compromiso === 'promesa_pago') {
                $compromisoActivo->promesa = DB::table('compromisos_promesa_pago')
                    ->where('compromiso_id', $compromisoActivo->id)
                    ->first();
            }
            if ($compromisoActivo !== null && $compromisoActivo->tipo_compromiso === 'resolucion_ticket') {
                $compromisoActivo->resolucion = DB::table('compromisos_resolucion_ticket as crt')
                    ->leftJoin('niveles_escalamiento as ne', 'ne.id', '=', 'crt.nivel_escalamiento_id')
                    ->where('crt.compromiso_id', $compromisoActivo->id)
                    ->select(['crt.accion_comprometida', 'crt.fecha_limite_sla', 'ne.nombre as escalamiento_nombre'])
                    ->first();
            }
            if ($compromisoActivo !== null && $compromisoActivo->tipo_compromiso === 'cierre_venta') {
                $compromisoActivo->cierre = DB::table('compromisos_cierre_venta as ccv')
                    ->leftJoin('etapas_embudo as ee', 'ee.id', '=', 'ccv.etapa_embudo_id')
                    ->where('ccv.compromiso_id', $compromisoActivo->id)
                    ->select(['ccv.monto_cierre', 'ccv.moneda', 'ee.nombre as etapa_nombre'])
                    ->first();
            }
            if ($compromisoActivo !== null && $compromisoActivo->tipo_compromiso === 'accion_servicio') {
                $compromisoActivo->accion = DB::table('compromisos_accion_servicio as cas')
                    ->leftJoin('tipos_accion_servicio as tas', 'tas.id', '=', 'cas.tipo_accion_servicio_id')
                    ->where('cas.compromiso_id', $compromisoActivo->id)
                    ->select(['cas.descripcion_accion', 'cas.fecha_programada', 'cas.tecnico_asignado', 'tas.nombre as tipo_accion_nombre'])
                    ->first();
            }

            $compromisosResueltos = DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)
                ->where('caso_id', $casoActivo->id)
                ->whereIn('estado', ['cumplido', 'roto', 'cancelado'])
                ->whereNull('eliminada_en')
                ->orderByDesc('fecha_resolucion')
                ->select(['id', 'tipo_compromiso', 'estado', 'fecha_vencimiento', 'fecha_resolucion'])
                ->limit(20)
                ->get();
        } else {
            $compromisosResueltos = collect();
        }

        $casoCobranza = null;
        if ($casoActivo !== null && $casoActivo->tipo_caso === 'cobranza') {
            $casoCobranza = DB::table('casos_cobranza as cc')
                ->leftJoin('tramos_mora as tm', 'tm.id', '=', 'cc.tramo_mora_id')
                ->where('cc.caso_id', $casoActivo->id)
                ->select([
                    'cc.numero_prestamo', 'cc.moneda', 'cc.monto_original',
                    'cc.saldo_capital', 'cc.saldo_interes', 'cc.saldo_total',
                    'cc.cuota_mensual', 'cc.cuotas_totales', 'cc.cuotas_pagadas',
                    'cc.dias_mora', 'cc.dias_mora_confirmado_en', 'cc.fecha_desembolso', 'cc.fecha_vencimiento',
                    'tm.nombre as tramo_mora_nombre',
                ])
                ->first();
        }

        $casoLeadVenta = null;
        if ($casoActivo !== null && $casoActivo->tipo_caso === 'lead_venta') {
            $casoLeadVenta = DB::table('casos_lead_venta as clv')
                ->leftJoin('productos_venta as pv', 'pv.id', '=', 'clv.producto_venta_id')
                ->leftJoin('etapas_embudo as ee', 'ee.id', '=', 'clv.etapa_embudo_id')
                ->where('clv.caso_id', $casoActivo->id)
                ->select([
                    'clv.codigo_lead', 'clv.valor_estimado', 'clv.moneda',
                    'clv.origen_lead', 'clv.fecha_primer_contacto', 'clv.fecha_estimada_cierre',
                    'pv.nombre as producto_nombre',
                    'ee.nombre as etapa_nombre', 'ee.probabilidad_cierre as etapa_probabilidad',
                ])
                ->first();
        }

        $casoTicketCx = null;
        if ($casoActivo !== null && $casoActivo->tipo_caso === 'ticket_cx') {
            $casoTicketCx = DB::table('casos_ticket_cx as ct')
                ->leftJoin('categorias_ticket as cat', 'cat.id', '=', 'ct.categoria_ticket_id')
                ->leftJoin('prioridades_ticket as pr', 'pr.id', '=', 'ct.prioridad_ticket_id')
                ->leftJoin('niveles_sla as sla', 'sla.id', '=', 'ct.nivel_sla_id')
                ->leftJoin('niveles_escalamiento as esc', 'esc.id', '=', 'ct.nivel_escalamiento_id')
                ->where('ct.caso_id', $casoActivo->id)
                ->select([
                    'ct.codigo_ticket', 'ct.asunto', 'ct.descripcion',
                    'ct.fecha_reporte', 'ct.fecha_limite_sla',
                    'cat.nombre as categoria_nombre',
                    'pr.nombre as prioridad_nombre', 'pr.codigo as prioridad_codigo',
                    'sla.nombre as sla_nombre',
                    'esc.nombre as escalamiento_nombre',
                ])
                ->first();
        }

        $casoServicio = null;
        if ($casoActivo !== null && $casoActivo->tipo_caso === 'servicio') {
            $casoServicio = DB::table('casos_servicio as cs')
                ->leftJoin('tipos_accion_servicio as tas', 'tas.id', '=', 'cs.tipo_accion_servicio_id')
                ->leftJoin('estados_tecnicos as et', 'et.id', '=', 'cs.estado_tecnico_id')
                ->where('cs.caso_id', $casoActivo->id)
                ->select([
                    'cs.codigo_servicio', 'cs.direccion_servicio', 'cs.tecnico_asignado',
                    'cs.fecha_solicitud', 'cs.fecha_programada',
                    'tas.nombre as tipo_accion_nombre',
                    'et.nombre as estado_tecnico_nombre',
                ])
                ->first();
        }

        $contactos = DB::table('contactos')
            ->where('proyecto_id', $proyectoId)
            ->where('persona_id', $persona->id)
            ->where('activo', true)
            ->orderByDesc('es_principal')
            ->orderBy('tipo')
            ->get();

        $nombrePersona = $persona->tipo_persona === 'juridica'
            ? (string) ($persona->razon_social ?? '')
            : trim((string) ($persona->nombres ?? '').' '.(string) ($persona->apellidos ?? ''));

        return view('casos::livewire.vista-de-trabajo', [
            'proyectoActivo' => $proyectoActivo,
            'persona' => $persona,
            'nombrePersona' => $nombrePersona,
            'casos' => $casos,
            'casoActivo' => $casoActivo,
            'casoCobranza' => $casoCobranza,
            'casoTicketCx' => $casoTicketCx,
            'casoLeadVenta' => $casoLeadVenta,
            'casoServicio' => $casoServicio,
            'historial' => $historial,
            'valoresCamposGestion' => $valoresCamposGestion ?? [],
            'compromisoActivo' => $compromisoActivo,
            'compromisosResueltos' => $compromisosResueltos,
            'contactos' => $contactos,
            'gruposCamposCaso' => $casoActivo === null ? [] : $this->camposDelCasoPorGrupo($casoActivo),
            'duenioCaso' => $casoActivo === null ? null : $this->duenioDelCaso($proyectoId, (int) $casoActivo->id),
            'puedeTomar' => $casoActivo !== null
                && auth()->user()?->tienePermiso('asignaciones.autoasignarse', $proyectoId) === true
                && app(AutoasignarCaso::class)->proyectoLoPermite($proyectoId),
        ]);
    }

    /**
     * Nombre de quien tiene la cuenta, o null si no la ha tenido nadie.
     *
     * También cuenta la asignación cerrada: mientras exista, la cuenta no se
     * puede volver a tomar (único `(proyecto_id, caso_id)`).
     */
    private function duenioDelCaso(int $proyectoId, int $casoId): ?string
    {
        $nombre = DB::table('asignaciones as a')
            ->join('users as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.proyecto_id', $proyectoId)
            ->where('a.caso_id', $casoId)
            ->orderByDesc('a.id')
            ->value('u.name');

        return $nombre === null ? null : (string) $nombre;
    }

    /**
     * Los campos personalizados del caso, en lectura y repartidos por grupo.
     *
     * Se leen aquí y se editan en «Editar caso». Estaban además como 34 inputs
     * dentro del formulario de gestión, que era una segunda superficie de
     * escritura sobre el mismo dato (§13.3) y por donde se borraban valores.
     *
     * El valor se formatea aquí, en una sola consulta para todo el caso: la
     * alternativa era que la vista resolviera opciones y monedas campo a campo.
     *
     * @return list<array{nombre: string, campos: list<array{campo: object, valor: string|null}>}>
     */
    private function camposDelCasoPorGrupo(object $casoActivo): array
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $campos = app(ServicioCamposPersonalizados::class)
            ->campos($proyectoId, AmbitoCampo::CASO, (int) $casoActivo->cartera_id)
            ->filter(fn (object $c): bool => (bool) $c->visible_en_gestion);

        if ($campos->isEmpty()) {
            return [];
        }

        $valores = app(ServicioCamposPersonalizados::class)->valoresSerializadosParaWriteback(
            $proyectoId,
            AmbitoCampo::CASO,
            (int) $casoActivo->cartera_id,
            (int) $casoActivo->id,
        );

        $grupos = [];
        foreach ($campos as $campo) {
            $nombre = (string) ($campo->grupo_nombre ?? __('casos.fields_ungrouped'));
            $grupos[$nombre][] = [
                'campo' => $campo,
                'valor' => $valores[(string) $campo->codigo] ?? null,
            ];
        }

        return array_map(
            fn (string $nombre, array $campos): array => ['nombre' => $nombre, 'campos' => $campos],
            array_keys($grupos),
            $grupos,
        );
    }
}
