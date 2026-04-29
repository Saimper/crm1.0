<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('equipo_id')
                ->nullable()
                ->after('id')
                ->constrained('equipos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true)->after('password');
            $table->index(['activo', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['activo', 'equipo_id']);
            $table->dropConstrainedForeignId('equipo_id');
            $table->dropColumn('activo');
        });
    }
};
