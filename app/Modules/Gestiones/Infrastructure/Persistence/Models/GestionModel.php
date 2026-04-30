<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class GestionModel extends Model
{
    use PerteneceAProyecto;
    use SoftDeletes;

    protected $table = 'gestiones';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'duracion_segundos' => 'integer',
    ];
}
