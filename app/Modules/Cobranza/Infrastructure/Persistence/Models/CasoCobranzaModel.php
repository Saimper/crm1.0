<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CasoCobranzaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'casos_cobranza';

    protected $primaryKey = 'caso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'fecha_desembolso' => 'immutable_date',
        'fecha_vencimiento' => 'immutable_date',
        'monto_original' => 'string',
        'saldo_capital' => 'string',
        'saldo_interes' => 'string',
        'saldo_total' => 'string',
        'cuota_mensual' => 'string',
        'cuotas_totales' => 'integer',
        'cuotas_pagadas' => 'integer',
        'dias_mora' => 'integer',
    ];
}
