<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Infrastructure\Support\PerteneceAProyecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class ContactoModel extends Model
{
    use PerteneceAProyecto;
    use SoftDeletes;

    protected $table = 'contactos';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * El ULID público, que §4 pide en toda entidad operativa y a esta le
     * faltaba. Se genera aquí para que ningún camino de escritura tenga que
     * acordarse.
     */
    protected static function booted(): void
    {
        self::creating(function (self $contacto): void {
            if (blank($contacto->public_id)) {
                $contacto->public_id = (string) Str::ulid();
            }
        });
    }
}
