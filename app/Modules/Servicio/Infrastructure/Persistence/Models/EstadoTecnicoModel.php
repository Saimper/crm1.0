<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EstadoTecnicoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'estados_tecnicos';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}
