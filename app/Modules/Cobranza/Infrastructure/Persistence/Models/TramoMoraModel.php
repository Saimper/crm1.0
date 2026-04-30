<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class TramoMoraModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'tramos_mora';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'dias_desde' => 'integer',
        'dias_hasta' => 'integer',
        'orden' => 'integer',
    ];
}
