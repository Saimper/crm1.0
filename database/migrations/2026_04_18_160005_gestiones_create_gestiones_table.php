<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestiones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('caso_id')
                ->constrained('casos')
                ->restrictOnDelete();

            // persona_id desnormalizado §4.3 — facilita reportería sin join via casos.
            $table->foreignId('persona_id')
                ->constrained('personas')
                ->restrictOnDelete();

            $table->foreignId('contacto_id')
                ->nullable()
                ->constrained('contactos')
                ->nullOnDelete();

            $table->foreignId('canal_id')
                ->constrained('canales')
                ->restrictOnDelete();
            $table->foreignId('tipo_gestion_id')
                ->constrained('tipos_gestion')
                ->restrictOnDelete();
            $table->foreignId('resultado_id')
                ->constrained('resultados')
                ->restrictOnDelete();
            $table->foreignId('motivo_no_contacto_id')
                ->nullable()
                ->constrained('motivos_no_contacto')
                ->nullOnDelete();
            $table->foreignId('causa_id')
                ->nullable()
                ->constrained('causas_gestion')
                ->nullOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notas')->nullable();
            $table->unsignedInteger('duracion_segundos')->nullable();

            // Snapshots específicos por tipo de operación se agregarán en las
            // fases 2+ vía tabla auxiliar (una por tipo) para no forzar schema
            // evolution en esta tabla base.

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index(['proyecto_id', 'caso_id', 'creada_en']);
            $table->index(['proyecto_id', 'usuario_id', 'creada_en']);
            $table->index(['proyecto_id', 'creada_en', 'resultado_id']);
            $table->index(['proyecto_id', 'persona_id']);
            $table->index(['proyecto_id', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestiones');
    }
};
