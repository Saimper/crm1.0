<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Infrastructure\Persistence\Models;

use App\Modules\Contactos\Infrastructure\Persistence\Models\ContactoModel;
use App\Modules\Productos\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ClienteModel extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    public $timestamps = false;

    public const DELETED_AT = 'eliminada_en';

    protected $guarded = [];

    protected $casts = [
        'creada_en' => 'immutable_datetime',
        'actualizada_en' => 'immutable_datetime',
        'eliminada_en' => 'immutable_datetime',
        'fecha_nacimiento' => 'immutable_date',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(ProductoModel::class, 'cliente_id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoModel::class, 'cliente_id');
    }

    public function nombreCompleto(): string
    {
        if ($this->tipo_persona === 'juridica') {
            return (string) ($this->razon_social ?? '');
        }

        return trim(($this->nombres ?? '').' '.($this->apellidos ?? ''));
    }
}
