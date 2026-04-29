<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OpcionCampoPersonalizadoModel extends Model
{
    protected $table = 'opciones_campo_personalizado';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'      => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo'         => 'boolean',
        'orden'          => 'integer',
    ];
}
