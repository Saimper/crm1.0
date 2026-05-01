<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->string('sso_secret', 64)->nullable()->after('id');
            $table->index('sso_secret');
        });

        DB::table('proyectos')
            ->whereNull('sso_secret')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $row): void {
                DB::table('proyectos')
                    ->where('id', $row->id)
                    ->update(['sso_secret' => bin2hex(random_bytes(32))]);
            });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->dropIndex(['sso_secret']);
            $table->dropColumn('sso_secret');
        });
    }
};
