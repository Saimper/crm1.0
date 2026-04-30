<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EntidadConfigurableModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'entidades_configurables';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
    ];
}
