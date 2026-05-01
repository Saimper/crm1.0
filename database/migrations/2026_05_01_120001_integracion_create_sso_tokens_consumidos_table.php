<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_tokens_consumidos', function (Blueprint $table): void {
            $table->string('jti', 64)->primary();
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();
            $table->timestamp('consumido_en')->useCurrent();
            $table->timestamp('expira_en');

            $table->index('expira_en');
            $table->index(['proyecto_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_tokens_consumidos');
    }
};
