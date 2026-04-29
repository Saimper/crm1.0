<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EstadoCasoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'estados_caso';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'      => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo'         => 'boolean',
        'es_terminal'    => 'boolean',
        'orden'          => 'integer',
        'metadata'       => 'array',
    ];
}
