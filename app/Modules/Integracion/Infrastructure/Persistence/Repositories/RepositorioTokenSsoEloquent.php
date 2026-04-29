<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Persistence\Repositories;

use App\Modules\Integracion\Domain\Contracts\RepositorioTokenSso;
use App\Modules\Integracion\Domain\Entities\TokenSso;
use App\Modules\Integracion\Infrastructure\Persistence\Models\TokenSsoModel;
use DateTimeImmutable;

final class RepositorioTokenSsoEloquent implements RepositorioTokenSso
{
    public function guardar(TokenSso $token): void
    {
        $model = new TokenSsoModel;
        $model->public_id = $token->publicId;
        $model->usuario_id = $token->usuarioId;
        $model->token_hash = $token->tokenHash;
        $model->proyecto_id = $token->proyectoId;
        $model->identificacion = $token->identificacion;
        $model->tipo_identificacion_codigo = $token->tipoIdentificacionCodigo;
        $model->redirect_path = $token->redirectPath;
        $model->expira_en = $token->expiraEn->format('Y-m-d H:i:s');
        $model->consumido_en = null;
        $model->ip_origen = $token->ipOrigen;
        $model->user_agent = $token->userAgent;
        $model->save();
    }

    public function buscarPorHash(string $hash): ?TokenSso
    {
        $model = TokenSsoModel::query()->where('token_hash', $hash)->first();

        if ($model === null) {
            return null;
        }

        /** @var DateTimeImmutable|null $consumidoEn */
        $consumidoEn = $model->consumido_en instanceof DateTimeImmutable
            ? $model->consumido_en
            : ($model->consumido_en !== null ? new DateTimeImmutable((string) $model->consumido_en) : null);

        /** @var DateTimeImmutable $expiraEn */
        $expiraEn = $model->expira_en instanceof DateTimeImmutable
            ? $model->expira_en
            : new DateTimeImmutable((string) $model->expira_en);

        return TokenSso::reconstituir(
            publicId: (string) $model->public_id,
            usuarioId: (int) $model->usuario_id,
            tokenHash: (string) $model->token_hash,
            expiraEn: $expiraEn,
            consumidoEn: $consumidoEn,
            proyectoId: $model->proyecto_id !== null ? (int) $model->proyecto_id : null,
            identificacion: $model->identificacion !== null ? (string) $model->identificacion : null,
            tipoIdentificacionCodigo: $model->tipo_identificacion_codigo !== null ? (string) $model->tipo_identificacion_codigo : null,
            redirectPath: $model->redirect_path !== null ? (string) $model->redirect_path : null,
            ipOrigen: $model->ip_origen !== null ? (string) $model->ip_origen : null,
            userAgent: $model->user_agent !== null ? (string) $model->user_agent : null,
        );
    }

    public function marcarConsumido(string $publicId, DateTimeImmutable $consumidoEn): void
    {
        TokenSsoModel::query()
            ->where('public_id', $publicId)
            ->update(['consumido_en' => $consumidoEn->format('Y-m-d H:i:s')]);
    }
}
