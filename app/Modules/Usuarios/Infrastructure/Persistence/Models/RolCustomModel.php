<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class RolCustomModel extends Model
{
    protected $table = 'roles_custom';

    public const CREATED_AT = 'creada_en';

    public const UPDATED_AT = 'actualizada_en';

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'proyecto_id' => 'integer',
        'creado_por_usuario_id' => 'integer',
        'eliminada_en' => 'datetime',
    ];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(
            PermisoModel::class,
            'rol_custom_permiso',
            'rol_custom_id',
            'permiso_id',
        );
    }
}
