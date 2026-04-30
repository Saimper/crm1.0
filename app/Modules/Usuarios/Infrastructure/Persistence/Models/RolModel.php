<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class RolModel extends Model
{
    protected $table = 'roles';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'es_global' => 'boolean',
        'orden' => 'integer',
    ];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(
            PermisoModel::class,
            'rol_permiso',
            'rol_id',
            'permiso_id',
        );
    }
}
