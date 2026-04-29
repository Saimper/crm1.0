<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class PermisoModel extends Model
{
    protected $table = 'permisos';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            RolModel::class,
            'rol_permiso',
            'permiso_id',
            'rol_id',
        );
    }
}
