<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class PrioridadTicketModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'prioridades_ticket';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'      => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo'         => 'boolean',
        'peso'           => 'integer',
        'orden'          => 'integer',
    ];
}
