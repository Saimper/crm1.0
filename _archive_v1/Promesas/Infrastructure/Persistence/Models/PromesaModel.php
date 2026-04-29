<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PromesaModel extends Model
{
    use SoftDeletes;

    protected $table = 'promesas';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en'        => 'immutable_datetime',
        'actualizada_en'   => 'immutable_datetime',
        'eliminada_en'     => 'immutable_datetime',
        'fecha_promesa'    => 'immutable_date',
        'fecha_resolucion' => 'immutable_date',
        'monto_promesa'    => 'decimal:2',
    ];
}
