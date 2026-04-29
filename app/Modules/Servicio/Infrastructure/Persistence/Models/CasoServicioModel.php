<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CasoServicioModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'casos_servicio';

    protected $primaryKey = 'caso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'       => 'immutable_datetime',
        'actualizada_en'  => 'immutable_datetime',
        'fecha_solicitud' => 'immutable_date',
        'fecha_programada' => 'immutable_datetime',
    ];
}
