<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditoriaModel extends Model
{
    protected $table = 'auditorias';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'cambios' => 'array',
        'creada_en' => 'immutable_datetime',
    ];
}
