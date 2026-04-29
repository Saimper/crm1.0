<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CompromisoResolucionTicketModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'compromisos_resolucion_ticket';

    protected $primaryKey = 'compromiso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'        => 'immutable_datetime',
        'actualizada_en'   => 'immutable_datetime',
        'fecha_limite_sla' => 'immutable_datetime',
    ];
}
