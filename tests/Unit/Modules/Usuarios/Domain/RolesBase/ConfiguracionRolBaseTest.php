<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Usuarios\Domain\RolesBase;

use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ConfiguracionRolBaseTest extends TestCase
{
    public function test_project_inheritance_is_absence_and_denial_is_persisted(): void
    {
        $config = ConfiguracionRolBase::proyecto('GESTOR', 8, [
            'casos.ver' => 'denegar', 'historico.ver' => 'permitir', 'contactos.ver' => 'heredar',
        ]);
        self::assertSame(['casos.ver' => false, 'historico.ver' => true], $config->permisos);
        self::assertSame(8, $config->proyectoId);
    }

    public function test_global_template_normalizes_duplicates_and_can_revoke_every_permission(): void
    {
        $config = ConfiguracionRolBase::plantilla('AUDITOR', ' Auditor ', ' Lectura ', ['historico.ver', 'historico.ver']);
        self::assertSame('Auditor', $config->nombre);
        self::assertSame('Lectura', $config->descripcion);
        self::assertSame(['historico.ver' => true], $config->permisos);
        self::assertSame([], ConfiguracionRolBase::plantilla('GESTOR', 'Gestor', null, [])->permisos);
    }

    public function test_global_admin_identity_is_protected(): void
    {
        $this->expectException(DomainException::class);
        ConfiguracionRolBase::plantilla('ADMIN_GLOBAL', 'Administrador', null, []);
    }

    public function test_privileged_permissions_cannot_be_delegated(): void
    {
        $this->expectException(DomainException::class);
        ConfiguracionRolBase::proyecto('GESTOR', 8, ['roles.gestionar' => 'permitir']);
    }

    public function test_unknown_decision_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        ConfiguracionRolBase::proyecto('GESTOR', 8, ['casos.ver' => 'true']);
    }

    public function test_invalid_project_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        ConfiguracionRolBase::proyecto('GESTOR', 0, []);
    }

    public function test_empty_name_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        ConfiguracionRolBase::plantilla('GESTOR', ' ', null, []);
    }
}
