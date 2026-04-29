<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class ImportacionModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'importaciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'         => 'immutable_datetime',
        'actualizada_en'    => 'immutable_datetime',
        'total_filas'       => 'integer',
        'filas_ok'          => 'integer',
        'filas_error'       => 'integer',
        'filas_importadas'  => 'integer',
    ];
}
