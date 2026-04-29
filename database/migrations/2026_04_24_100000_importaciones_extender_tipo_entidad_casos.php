<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE importaciones MODIFY COLUMN tipo_entidad ENUM('persona','caso_cobranza','caso_ticket_cx','caso_lead_venta','caso_servicio') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE importaciones MODIFY COLUMN tipo_entidad ENUM('persona') NOT NULL");
    }
};
