<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EjecucionReporteModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'reportes_ejecuciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'total_filas' => 'integer',
        'duracion_ms' => 'integer',
        'ejecutado_en' => 'immutable_datetime',
    ];
}
