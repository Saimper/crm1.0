<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Entities;

use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\Enums\OperadorFiltro;
use App\Modules\Reportes\Domain\Constructor\Exceptions\AgregacionRequiereAgrupacion;
use App\Modules\Reportes\Domain\Constructor\Exceptions\DefinicionReporteInvalida;
use App\Modules\Reportes\Domain\Constructor\Exceptions\OperadorIncompatibleConTipo;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\ColumnaReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\FiltroReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\OrdenReporte;

/**
 * Entidad de dominio: definición declarativa de un reporte custom.
 *
 * Inmutable después de validar(). El ejecutor usa solo getters.
 *
 * @phpstan-type ConjuntoCampos array<string,\App\Modules\Reportes\Domain\Constructor\ValueObjects\CampoDisponible>
 */
final class DefinicionReporte
{
    /**
     * @param  list<ColumnaReporte>  $columnas
     * @param  list<FiltroReporte>  $filtros
     * @param  list<string>  $agrupaciones  Claves de campo, mismo namespace que columnas.
     * @param  list<OrdenReporte>  $orden
     */
    public function __construct(
        public readonly int $proyectoId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly EntidadRaiz $entidad,
        public readonly array $columnas,
        public readonly array $filtros = [],
        public readonly array $agrupaciones = [],
        public readonly array $orden = [],
        public readonly ?string $descripcion = null,
    ) {}

    /**
     * Verifica invariantes contra el catálogo de campos del proyecto.
     *
     * - Al menos 1 columna.
     * - Código y nombre no vacíos.
     * - Cada campo (columna/filtro/agrupación/orden) presente en el catálogo.
     * - Operadores compatibles con el tipo del campo.
     * - Si hay agregación → al menos 1 agrupación.
     * - Si hay agregación → toda columna sin agregación debe estar en agrupaciones.
     */
    public function validar(CatalogoCamposReporte $catalogo): void
    {
        if (trim($this->codigo) === '') {
            throw DefinicionReporteInvalida::codigoVacio();
        }
        if (trim($this->nombre) === '') {
            throw DefinicionReporteInvalida::nombreVacio();
        }
        if ($this->columnas === []) {
            throw DefinicionReporteInvalida::sinColumnas();
        }

        $tieneAgregacion = false;
        foreach ($this->columnas as $col) {
            $campo = $catalogo->obtener($col->campo); // throws CampoNoPermitido
            if ($col->agregacion !== null) {
                $tieneAgregacion = true;
                if ($col->agregacion->requiereTipoNumerico() && ! $campo->tipo->esNumerico()) {
                    throw OperadorIncompatibleConTipo::combinar(
                        OperadorFiltro::IGUAL,
                        $campo->tipo,
                        $col->campo,
                    );
                }
            }
        }

        foreach ($this->filtros as $f) {
            $campo = $catalogo->obtener($f->campo);
            if (! $campo->tipo->aceptaOperador($f->operador)) {
                throw OperadorIncompatibleConTipo::combinar($f->operador, $campo->tipo, $f->campo);
            }
        }

        foreach ($this->agrupaciones as $clave) {
            $catalogo->obtener($clave);
        }

        foreach ($this->orden as $o) {
            $catalogo->obtener($o->campo);
        }

        if ($tieneAgregacion && $this->agrupaciones === []) {
            throw AgregacionRequiereAgrupacion::sinGroupBy();
        }

        if ($tieneAgregacion) {
            foreach ($this->columnas as $col) {
                if ($col->agregacion === null && ! in_array($col->campo, $this->agrupaciones, true)) {
                    throw AgregacionRequiereAgrupacion::sinGroupBy();
                }
            }
        }
    }
}
