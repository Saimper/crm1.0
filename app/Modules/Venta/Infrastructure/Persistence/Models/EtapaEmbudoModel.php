<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EtapaEmbudoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'etapas_embudo';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'nivel' => 'integer',
        'probabilidad_cierre' => 'integer',
        'orden' => 'integer',
    ];
}
