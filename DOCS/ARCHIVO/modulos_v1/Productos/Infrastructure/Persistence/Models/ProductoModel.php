<?php

declare(strict_types=1);

namespace App\Modules\Productos\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductoModel extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'fecha_desembolso' => 'immutable_date',
        'fecha_vencimiento' => 'immutable_date',
        'fecha_ultima_gestion' => 'immutable_datetime',
        'monto_original' => 'decimal:2',
        'saldo_capital' => 'decimal:2',
        'saldo_total' => 'decimal:2',
        'cuota_mensual' => 'decimal:2',
        'dias_mora' => 'integer',
        'cuotas_totales' => 'integer',
        'cuotas_pagadas' => 'integer',
        'tiene_promesa_vigente' => 'boolean',
    ];
}
