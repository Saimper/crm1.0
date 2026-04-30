<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CasoTicketCxModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'casos_ticket_cx';

    protected $primaryKey = 'caso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'fecha_reporte' => 'immutable_datetime',
        'fecha_limite_sla' => 'immutable_datetime',
    ];
}
