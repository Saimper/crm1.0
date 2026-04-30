<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class EntidadRegistroModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'entidades_registros';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'creado_en' => 'immutable_datetime',
        'actualizado_en' => 'immutable_datetime',
        'eliminado_en' => 'immutable_datetime',
    ];
}
