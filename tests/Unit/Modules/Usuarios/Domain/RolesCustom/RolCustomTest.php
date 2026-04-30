<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Usuarios\Domain\RolesCustom;

use App\Modules\Usuarios\Domain\RolesCustom\Entities\RolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\PermisoNoAsignableARolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Exceptions\RolCustomSinPermisos;
use App\Modules\Usuarios\Domain\RolesCustom\ValueObjects\CodigoRolCustom;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RolCustomTest extends TestCase
{
    public function test_crea_rol_custom_valido(): void
    {
        $rol = RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['casos.ver', 'gestiones.crear'],
        );

        $this->assertSame(1, $rol->proyectoId);
        $this->assertSame('CUSTOM_X', $rol->codigo->asString());
        $this->assertSame(['casos.ver', 'gestiones.crear'], $rol->permisos);
        $this->assertTrue($rol->activo);
    }

    public function test_rechaza_permiso_vetado_campos_definir(): void
    {
        $this->expectException(PermisoNoAsignableARolCustom::class);
        RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['casos.ver', 'campos.definir'],
        );
    }

    public function test_rechaza_permiso_vetado_entidades_definir(): void
    {
        $this->expectException(PermisoNoAsignableARolCustom::class);
        RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['entidades.definir'],
        );
    }

    public function test_rechaza_permiso_vetado_roles_gestionar(): void
    {
        $this->expectException(PermisoNoAsignableARolCustom::class);
        RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['roles.gestionar'],
        );
    }

    public function test_rechaza_lista_de_permisos_vacia(): void
    {
        $this->expectException(RolCustomSinPermisos::class);
        RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: [],
        );
    }

    public function test_rechaza_nombre_vacio(): void
    {
        $this->expectException(DomainException::class);
        RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: '   ',
            descripcion: null,
            permisos: ['casos.ver'],
        );
    }

    public function test_actualizar_genera_nueva_instancia_y_valida(): void
    {
        $rol = RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['casos.ver'],
        );

        $actualizado = $rol->actualizar(
            nombre: 'Custom X v2',
            descripcion: 'desc',
            permisos: ['casos.ver', 'gestiones.crear'],
        );

        $this->assertSame('Custom X v2', $actualizado->nombre);
        $this->assertSame(['casos.ver', 'gestiones.crear'], $actualizado->permisos);
    }

    public function test_actualizar_rechaza_permiso_vetado(): void
    {
        $rol = RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['casos.ver'],
        );

        $this->expectException(PermisoNoAsignableARolCustom::class);
        $rol->actualizar('Custom X', null, ['campos.definir']);
    }

    public function test_puede_asignar_permiso_devuelve_false_para_vetados(): void
    {
        $this->assertFalse(RolCustom::puedeAsignarPermiso('campos.definir'));
        $this->assertFalse(RolCustom::puedeAsignarPermiso('entidades.definir'));
        $this->assertFalse(RolCustom::puedeAsignarPermiso('roles.gestionar'));
    }

    public function test_puede_asignar_permiso_devuelve_true_para_normales(): void
    {
        $this->assertTrue(RolCustom::puedeAsignarPermiso('casos.ver'));
        $this->assertTrue(RolCustom::puedeAsignarPermiso('gestiones.crear'));
        $this->assertTrue(RolCustom::puedeAsignarPermiso('compromisos.resolver'));
    }

    public function test_desactivar_marca_inactivo_y_preserva_resto(): void
    {
        $rol = RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: 'desc',
            permisos: ['casos.ver'],
        );

        $inactivo = $rol->desactivar();
        $this->assertFalse($inactivo->activo);
        $this->assertSame($rol->permisos, $inactivo->permisos);
        $this->assertSame($rol->nombre, $inactivo->nombre);
    }

    public function test_deduplicate_permisos(): void
    {
        $rol = RolCustom::nuevo(
            proyectoId: 1,
            codigo: new CodigoRolCustom('CUSTOM_X'),
            nombre: 'Custom X',
            descripcion: null,
            permisos: ['casos.ver', 'casos.ver', 'gestiones.crear'],
        );

        $this->assertCount(2, $rol->permisos);
    }
}
