<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F35-D: relaja invariantes hardcoded de las tablas CTI de casos.
 *
 * El cliente pidió que el form Crear Caso no pida campos hardcoded del CTI
 * (saldos, fechas, asunto, etc.). Solo el identificador único del caso queda
 * obligatorio. El resto se llena vía Campos Personalizados §7 que el admin
 * configura por proyecto/cartera.
 *
 * Mantiene compatibilidad con datos existentes (los valores actuales no cambian).
 * Solo aplica NULL como permitido para columnas que dejarán de pedirse en el form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos_cobranza', function (Blueprint $table): void {
            $table->decimal('monto_original', 15, 2)->nullable()->change();
            $table->decimal('saldo_capital', 15, 2)->nullable()->change();
            $table->decimal('saldo_interes', 15, 2)->nullable()->change();
            $table->decimal('saldo_total', 15, 2)->nullable()->change();
            $table->decimal('cuota_mensual', 15, 2)->nullable()->change();
            $table->unsignedInteger('cuotas_totales')->nullable()->change();
            $table->unsignedInteger('cuotas_pagadas')->nullable()->change();
            $table->unsignedInteger('dias_mora')->nullable()->change();
            $table->date('fecha_desembolso')->nullable()->change();
            $table->date('fecha_vencimiento')->nullable()->change();
        });

        Schema::table('casos_ticket_cx', function (Blueprint $table): void {
            $table->string('asunto', 255)->nullable()->change();
            $table->timestamp('fecha_reporte')->nullable()->change();
        });

        Schema::table('casos_lead_venta', function (Blueprint $table): void {
            $table->decimal('valor_estimado', 15, 2)->nullable()->change();
            $table->date('fecha_primer_contacto')->nullable()->change();
        });

        Schema::table('casos_servicio', function (Blueprint $table): void {
            $table->date('fecha_solicitud')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir a NOT NULL: hay riesgo de filas con NULL después de up().
        // Si se necesita rollback, hay que limpiar datos primero manualmente.
    }
};
