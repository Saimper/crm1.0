<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class TipoAccionServicioModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'tipos_accion_servicio';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'duracion_estimada_horas' => 'integer',
        'orden' => 'integer',
    ];
}
