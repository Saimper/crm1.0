<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CampoPersonalizadoModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'campos_personalizados';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'obligatorio' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
        'reglas' => 'array',
    ];

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionCampoPersonalizadoModel::class, 'campo_personalizado_id');
    }
}
