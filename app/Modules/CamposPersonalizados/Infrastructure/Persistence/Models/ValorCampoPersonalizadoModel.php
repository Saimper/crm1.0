<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ValorCampoPersonalizadoModel extends Model
{
    protected $table = 'valores_campo_personalizado';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'           => 'immutable_datetime',
        'actualizada_en'      => 'immutable_datetime',
        'valor_fecha'         => 'immutable_date',
        'valor_fecha_hora'    => 'immutable_datetime',
        'valor_booleano'      => 'boolean',
        'valor_numero_entero' => 'integer',
        'valor_opciones_ids'  => 'array',
    ];
}
