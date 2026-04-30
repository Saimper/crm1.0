<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;

final class NotificacionModel extends Model
{
    use PerteneceAProyecto;

    protected $table = 'notificaciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'leida_en' => 'immutable_datetime',
        'creada_en' => 'immutable_datetime',
    ];
}
