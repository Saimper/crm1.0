<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class ImportacionFilaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'importacion_filas';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'payload' => 'array',
        'numero_fila' => 'integer',
    ];
}
