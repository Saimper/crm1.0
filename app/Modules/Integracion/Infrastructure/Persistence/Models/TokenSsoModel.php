<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TokenSsoModel extends Model
{
    protected $table = 'integracion_tokens_sso';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expira_en' => 'immutable_datetime',
            'consumido_en' => 'immutable_datetime',
            'creado_en' => 'immutable_datetime',
            'actualizado_en' => 'immutable_datetime',
        ];
    }
}
