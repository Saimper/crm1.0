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
        Schema::table('mandantes', function (Blueprint $table): void {
            $table->string('sso_secret', 64)->nullable()->after('activo');
            $table->string('sso_secret_old', 64)->nullable()->after('sso_secret');
            $table->timestamp('sso_secret_old_expires_at')->nullable()->after('sso_secret_old');
            $table->string('webhook_url_secret_rotated', 255)->nullable()->after('sso_secret_old_expires_at');
            $table->string('webhook_url_status_changed', 255)->nullable()->after('webhook_url_secret_rotated');

            $table->index('sso_secret');
        });

        DB::table('mandantes')
            ->whereNull('sso_secret')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $row): void {
                DB::table('mandantes')
                    ->where('id', $row->id)
                    ->update(['sso_secret' => bin2hex(random_bytes(32))]);
            });
    }

    public function down(): void
    {
        Schema::table('mandantes', function (Blueprint $table): void {
            $table->dropIndex(['sso_secret']);
            $table->dropColumn([
                'sso_secret',
                'sso_secret_old',
                'sso_secret_old_expires_at',
                'webhook_url_secret_rotated',
                'webhook_url_status_changed',
            ]);
        });
    }
};
