<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CasoModel extends Model
{
    use PerteneceAProyecto;
    use SoftDeletes;

    protected $table = 'casos';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en'                => 'immutable_datetime',
        'actualizada_en'           => 'immutable_datetime',
        'eliminada_en'             => 'immutable_datetime',
        'cerrado_en'               => 'immutable_datetime',
        'fecha_ingreso'            => 'immutable_date',
        'fecha_ultima_gestion'     => 'immutable_datetime',
        'prioridad'                => 'integer',
        'tiene_compromiso_vigente' => 'boolean',
    ];
}
