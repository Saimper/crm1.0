<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración regional del cliente.
 *
 * Un mandante es un cliente del BPO, y la zona horaria, la moneda y el idioma
 * son suyos, no de la plataforma ni del proyecto: dos proyectos del mismo
 * cliente cierran el día a la misma hora, y dos clientes distintos no tienen por
 * qué.
 *
 * Los valores por defecto reproducen exactamente lo que el sistema hacía hasta
 * ahora —UTC, USD, español, semana de lunes a domingo— así que aplicar la
 * migración no cambia ni un número. Cada cliente ajusta el suyo cuando toca.
 *
 * IMPORTANTE: esto NO migra datos. Lo guardado en `creada_en` y compañía son
 * instantes UTC correctos y siguen siéndolo; lo que cambia es en qué huso se
 * interpretan al mostrarlos y al cortar los rangos de los informes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandantes', function (Blueprint $tabla): void {
            $tabla->string('zona_horaria', 64)->default('UTC')->after('activo');
            $tabla->char('moneda', 3)->default('USD')->after('zona_horaria');
            $tabla->string('locale', 5)->default('es')->after('moneda');
            // ISO-8601: 1 = lunes … 7 = domingo. Decide dónde empieza «esta
            // semana» en los informes, que no es igual en todos los clientes.
            $tabla->unsignedTinyInteger('inicio_semana')->default(1)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('mandantes', function (Blueprint $tabla): void {
            $tabla->dropColumn(['zona_horaria', 'moneda', 'locale', 'inicio_semana']);
        });
    }
};
