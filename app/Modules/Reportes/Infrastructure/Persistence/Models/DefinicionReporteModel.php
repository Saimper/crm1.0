<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class DefinicionReporteModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'reportes_definiciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'columnas' => 'array',
        'filtros' => 'array',
        'agrupaciones' => 'array',
        'orden' => 'array',
        'activo' => 'boolean',
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
    ];
}
