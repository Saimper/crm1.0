<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CompromisoCierreVentaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'compromisos_cierre_venta';

    protected $primaryKey = 'compromiso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en'      => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'monto_cierre'   => 'string',
    ];
}
