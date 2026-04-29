<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class NivelSlaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'niveles_sla';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'         => 'immutable_datetime',
        'actualizada_en'    => 'immutable_datetime',
        'activo'            => 'boolean',
        'horas_resolucion'  => 'integer',
        'orden'             => 'integer',
    ];
}
