<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProyectoModel extends Model
{
    use SoftDeletes;

    protected $table = 'proyectos';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'fecha_inicio' => 'immutable_date',
        'fecha_fin' => 'immutable_date',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $proyecto): void {
            if (empty($proyecto->getAttribute('sso_secret'))) {
                $proyecto->setAttribute('sso_secret', bin2hex(random_bytes(32)));
            }
        });
    }
}
