<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class ProductoVentaModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'productos_venta';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}
