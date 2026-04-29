<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TipoGestionModel extends Model
{
    protected $table = 'tipos_gestion';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo'   => 'boolean',
        'orden'    => 'integer',
        'metadata' => 'array',
    ];
}
