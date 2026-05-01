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
            $table->boolean('sso_provisioned')->default(false)->after('activo');
            $table->timestamp('ultimo_sso_en')->nullable()->after('sso_provisioned');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['sso_provisioned', 'ultimo_sso_en']);
        });
    }
};
