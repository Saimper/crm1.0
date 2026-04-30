<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class CasoLeadVentaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'casos_lead_venta';

    protected $primaryKey = 'caso_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'fecha_primer_contacto' => 'immutable_date',
        'fecha_estimada_cierre' => 'immutable_date',
        'valor_estimado' => 'string',
    ];
}
