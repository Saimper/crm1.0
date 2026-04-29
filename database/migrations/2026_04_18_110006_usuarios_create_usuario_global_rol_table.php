<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_global_rol', function (Blueprint $table): void {
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->timestamp('creada_en')->useCurrent();

            $table->primary(['usuario_id', 'rol_id']);
            $table->index('rol_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_global_rol');
    }
};
