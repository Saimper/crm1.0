<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('integracion_tokens_sso');
    }

    public function down(): void
    {
        Schema::create('integracion_tokens_sso', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('proyecto_id')->nullable();
            $table->string('identificacion')->nullable();
            $table->string('tipo_identificacion_codigo', 20)->nullable();
            $table->string('redirect_path')->nullable();
            $table->timestamp('expira_en');
            $table->timestamp('consumido_en')->nullable()->index();
            $table->string('ip_origen', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('proyecto_id')->references('id')->on('proyectos')->nullOnDelete();
            $table->index(['usuario_id', 'expira_en']);
        });
    }
};
