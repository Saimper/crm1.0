<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandantes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->string('documento', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index(['activo', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandantes');
    }
};
