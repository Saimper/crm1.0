<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MandanteModel extends Model
{
    use SoftDeletes;

    protected $table = 'mandantes';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'activo' => 'boolean',
    ];
}
