<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Helpers de inserción CTI para tests del configurador de proyecto (F36 P5).
 * Cada método inserta un caso base + su sub-tabla CTI usando todos los FK
 * necesarios para satisfacer las constraints de schema.
 */
trait InsertaCti
{
    use EscenarioOperativo;

    protected function insertarCasoCobranzaConTramoMora(stdClass $proyecto, int $tramoMoraId): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'COB_'.Str::random(4));

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PRST-'.Str::random(6),
            'monto_original' => 1000.00,
            'saldo_capital' => 1000.00,
            'saldo_total' => 1000.00,
            'cuota_mensual' => 100.00,
            'cuotas_totales' => 12,
            'tramo_mora_id' => $tramoMoraId,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $casoId;
    }

    /**
     * @param  array<string, int|null>  $fks  claves opcionales: categoria_ticket_id, prioridad_ticket_id, nivel_sla_id, nivel_escalamiento_id.
     */
    protected function insertarCasoTicketCx(stdClass $proyecto, array $fks): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'CX_'.Str::random(4));

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'ticket_cx',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_ticket_cx')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'codigo_ticket' => 'TKT-'.Str::random(6),
            'asunto' => 'Asunto demo',
            'categoria_ticket_id' => $fks['categoria_ticket_id'] ?? $this->insertarCategoriaTicket($proyecto),
            'prioridad_ticket_id' => $fks['prioridad_ticket_id'] ?? $this->insertarPrioridadTicket($proyecto),
            'nivel_sla_id' => $fks['nivel_sla_id'] ?? $this->insertarNivelSla($proyecto),
            'nivel_escalamiento_id' => $fks['nivel_escalamiento_id'] ?? $this->insertarNivelEscalamiento($proyecto),
            'fecha_reporte' => Carbon::now(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $casoId;
    }

    protected function insertarCasoLeadVenta(stdClass $proyecto, ?int $productoVentaId = null, ?int $etapaEmbudoId = null): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'VT_'.Str::random(4));

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'lead_venta',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_lead_venta')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'codigo_lead' => 'LD-'.Str::random(6),
            'producto_venta_id' => $productoVentaId,
            'etapa_embudo_id' => $etapaEmbudoId,
            'fecha_primer_contacto' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $casoId;
    }

    protected function insertarCasoServicio(stdClass $proyecto, ?int $tipoAccionId = null, ?int $estadoTecnicoId = null): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'SV_'.Str::random(4));

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'servicio',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_servicio')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'codigo_servicio' => 'SRV-'.Str::random(6),
            'tipo_accion_servicio_id' => $tipoAccionId,
            'estado_tecnico_id' => $estadoTecnicoId,
            'fecha_solicitud' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $casoId;
    }

    protected function insertarCompromisoPromesaPagoConTipoPago(stdClass $proyecto, int $tipoPagoId): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'COB_'.Str::random(4));
        $usuario = $this->crearGestor($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::today()->addWeek(),
            'usuario_id' => $usuario->id,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('compromisos_promesa_pago')->insert([
            'compromiso_id' => $compromisoId,
            'proyecto_id' => $proyecto->id,
            'monto' => 500.00,
            'tipo_pago_id' => $tipoPagoId,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $compromisoId;
    }

    private function insertarCategoriaTicket(stdClass $proyecto): int
    {
        return (int) DB::table('categorias_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CAT_'.Str::random(4),
            'nombre' => 'Categoría auto',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function insertarPrioridadTicket(stdClass $proyecto): int
    {
        return (int) DB::table('prioridades_ticket')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'PRI_'.Str::random(4),
            'nombre' => 'Prioridad auto',
            'peso' => 100,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function insertarNivelSla(stdClass $proyecto): int
    {
        return (int) DB::table('niveles_sla')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'SLA_'.Str::random(4),
            'nombre' => 'SLA auto',
            'horas_resolucion' => 24,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function insertarNivelEscalamiento(stdClass $proyecto): int
    {
        $proximoNivel = ((int) DB::table('niveles_escalamiento')
            ->where('proyecto_id', $proyecto->id)
            ->max('nivel')) + 1;

        return (int) DB::table('niveles_escalamiento')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'ESC_'.Str::random(4),
            'nombre' => 'Nivel auto',
            'nivel' => $proximoNivel,
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
