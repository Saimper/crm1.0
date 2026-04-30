<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class AsignacionModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'asignaciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'cerrada_en' => 'immutable_datetime',
        'fecha_asignacion' => 'immutable_date',
        'prioridad' => 'integer',
    ];
}
