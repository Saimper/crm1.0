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

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            // cliente_id desnormalizado (derivado del producto) para queries — §4 CLAUDE.md
            $table->foreignId('cliente_id')
                ->constrained('clientes')
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
            $table->foreignId('causa_mora_id')
                ->nullable()
                ->constrained('causas_mora')
                ->nullOnDelete();
            $table->foreignId('motivo_no_contacto_id')
                ->nullable()
                ->constrained('motivos_no_contacto')
                ->nullOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notas')->nullable();
            $table->unsignedInteger('duracion_segundos')->nullable();

            // Snapshots opcionales — §3.4 CLAUDE.md
            $table->decimal('snapshot_saldo', 15, 2)->nullable();
            $table->unsignedInteger('snapshot_dias_mora')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            // Índices obligatorios §3.5
            $table->index(['producto_id', 'creada_en']);
            $table->index(['usuario_id', 'creada_en']);
            // Soporte reportería diaria
            $table->index(['creada_en', 'resultado_id']);
            $table->index('cliente_id');
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestiones');
    }
};
