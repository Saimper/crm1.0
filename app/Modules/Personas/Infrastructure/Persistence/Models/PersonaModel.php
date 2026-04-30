<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PersonaModel extends Model
{
    use PerteneceAProyecto;
    use SoftDeletes;

    protected $table = 'personas';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'fecha_nacimiento' => 'immutable_date',
    ];
}
