<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONEY_COLUMNS = [
        'casos_cobranza' => ['monto_original', 'saldo_capital', 'saldo_interes', 'saldo_total', 'cuota_mensual'],
        'casos_lead_venta' => ['valor_estimado'],
        'compromisos_promesa_pago' => ['monto'],
        'compromisos_cierre_venta' => ['monto_cierre'],
        'valores_campo_personalizado' => ['valor_moneda_monto'],
    ];

    public function up(): void
    {
        Schema::table('mandantes', function (Blueprint $table): void {
            $table->string('formato_fecha', 12)->default('d/m/Y');
            $table->unsignedTinyInteger('decimales')->default(2);
            $table->char('separador_decimal', 1)->default('.');
            $table->string('separador_miles', 1)->default(',');
        });
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->string('zona_horaria', 64)->nullable();
            $table->char('moneda', 3)->nullable();
            $table->string('formato_fecha', 12)->nullable();
            $table->unsignedTinyInteger('decimales')->nullable();
            $table->char('separador_decimal', 1)->nullable();
            $table->string('separador_miles', 1)->nullable();
            $table->unsignedTinyInteger('inicio_semana')->nullable();
        });
        foreach (self::MONEY_COLUMNS as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($name, $columns): void {
                foreach ($columns as $column) {
                    $definition = $table->decimal($column, 16, 3);
                    if (in_array($name, ['casos_cobranza', 'casos_lead_venta', 'valores_campo_personalizado'], true)) {
                        $definition->nullable();
                    }
                    $definition->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Refuse a destructive scale reduction before making any DDL change.
        foreach (self::MONEY_COLUMNS as $name => $columns) {
            foreach ($columns as $column) {
                if (DB::table($name)->whereRaw("`{$column}` <> ROUND(`{$column}`, 2)")->exists()) {
                    throw new RuntimeException('No se puede revertir: existen montos con tres decimales.');
                }
            }
        }
        foreach (self::MONEY_COLUMNS as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($name, $columns): void {
                foreach ($columns as $column) {
                    $definition = $table->decimal($column, 15, 2);
                    if (in_array($name, ['casos_cobranza', 'casos_lead_venta', 'valores_campo_personalizado'], true)) {
                        $definition->nullable();
                    }
                    $definition->change();
                }
            });
        }
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn(['zona_horaria', 'moneda', 'formato_fecha', 'decimales', 'separador_decimal', 'separador_miles', 'inicio_semana']));
        Schema::table('mandantes', fn (Blueprint $table) => $table->dropColumn(['formato_fecha', 'decimales', 'separador_decimal', 'separador_miles']));
    }
};
