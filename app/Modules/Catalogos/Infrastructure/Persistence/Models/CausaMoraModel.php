<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CausaMoraModel extends Model
{
    protected $table = 'causas_mora';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'metadata' => 'array',
    ];
}
