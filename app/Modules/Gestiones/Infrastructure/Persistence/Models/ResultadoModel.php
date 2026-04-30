<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class ResultadoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'resultados';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'orden' => 'integer',
        'es_contacto_efectivo' => 'boolean',
        'requiere_compromiso' => 'boolean',
        'requiere_causa' => 'boolean',
        'metadata' => 'array',
    ];
}
