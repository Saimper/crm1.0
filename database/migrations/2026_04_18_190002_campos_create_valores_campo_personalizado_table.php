<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores de campos personalizados. Una sola fila por (campo, entidad).
 * Solo una columna `valor_<tipo>` está llena según el tipo del campo; el resto, null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_campo_personalizado', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('campo_personalizado_id')
                ->constrained('campos_personalizados')
                ->cascadeOnDelete();

            // `entidad_id` es el id del caso/gestion/compromiso según el ámbito del campo.
            // Sin FK (polimórfico). La integridad se valida en el Application (§7.3).
            $table->unsignedBigInteger('entidad_id');

            $table->string('valor_texto_corto', 255)->nullable();
            $table->text('valor_texto_largo')->nullable();
            $table->bigInteger('valor_numero_entero')->nullable();
            $table->decimal('valor_numero_decimal', 18, 4)->nullable();
            $table->date('valor_fecha')->nullable();
            $table->dateTime('valor_fecha_hora')->nullable();
            $table->boolean('valor_booleano')->nullable();
            $table->foreignId('valor_opcion_id')
                ->nullable()
                ->constrained('opciones_campo_personalizado')
                ->cascadeOnDelete();
            $table->json('valor_opciones_ids')->nullable();
            $table->decimal('valor_moneda_monto', 15, 2)->nullable();
            $table->char('valor_moneda_codigo', 3)->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['campo_personalizado_id', 'entidad_id'], 'valores_cp_campo_entidad_unique');
            $table->index(['campo_personalizado_id', 'entidad_id'], 'valores_cp_lectura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_campo_personalizado');
    }
};
