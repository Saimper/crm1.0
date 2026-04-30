<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class TipoPagoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'tipos_pago';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}
