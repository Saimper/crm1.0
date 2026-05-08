<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto;

use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;

enum PasoConfiguracion: string
{
    case DATOS_PROYECTO = 'datos_proyecto';
    case CARTERAS = 'carteras';
    case ESTADOS_CASO = 'estados_caso';
    case TIPOS_GESTION = 'tipos_gestion';
    case RESULTADOS = 'resultados';
    case MOTIVOS_NO_CONTACTO = 'motivos_no_contacto';
    case CATALOGOS_TIPO = 'catalogos_tipo';
    case CAMPOS_PERSONALIZADOS = 'campos_personalizados';
    case RESUMEN = 'resumen';

    public function siguiente(): ?self
    {
        $todos = self::cases();
        $idx = array_search($this, $todos, true);

        if ($idx === false || $idx === count($todos) - 1) {
            return null;
        }

        return $todos[$idx + 1];
    }

    public function anterior(): ?self
    {
        $todos = self::cases();
        $idx = array_search($this, $todos, true);

        if ($idx === false || $idx === 0) {
            return null;
        }

        return $todos[$idx - 1];
    }

    public function indice(): int
    {
        $todos = self::cases();
        $idx = array_search($this, $todos, true);

        if ($idx === false) {
            throw new \LogicException('Paso no registrado en cases().');
        }

        return $idx + 1;
    }

    /**
     * @return list<self>
     */
    public static function todos(): array
    {
        return self::cases();
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::DATOS_PROYECTO => 'Datos del proyecto',
            self::CARTERAS => 'Carteras',
            self::ESTADOS_CASO => 'Estados de caso',
            self::TIPOS_GESTION => 'Tipos de gestión',
            self::RESULTADOS => 'Resultados',
            self::MOTIVOS_NO_CONTACTO => 'Motivos de no contacto',
            self::CATALOGOS_TIPO => 'Catálogos por tipo',
            self::CAMPOS_PERSONALIZADOS => 'Campos personalizados',
            self::RESUMEN => 'Resumen',
        };
    }

    public function esOpcional(): bool
    {
        return $this === self::CAMPOS_PERSONALIZADOS;
    }

    public function esObligatorio(): bool
    {
        return ! $this->esOpcional();
    }

    /**
     * Códigos de catálogos tipo-específicos a configurar en el Paso 7
     * según el tipo de operación del proyecto.
     *
     * @return list<string>
     */
    public static function subPasosCatalogosPorTipo(TipoOperacion $tipo): array
    {
        return match ($tipo) {
            TipoOperacion::COBRANZA => ['tramos_mora', 'tipos_pago'],
            TipoOperacion::CX => ['categorias_ticket', 'prioridades_ticket', 'niveles_sla', 'niveles_escalamiento'],
            TipoOperacion::VENTA => ['productos_venta', 'etapas_embudo'],
            TipoOperacion::SERVICIO => ['tipos_accion_servicio', 'estados_tecnicos'],
        };
    }
}
