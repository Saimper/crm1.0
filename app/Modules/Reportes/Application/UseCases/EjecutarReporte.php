<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\UseCases;

use App\Modules\Reportes\Application\DTOs\ResultadoEjecucionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\OperadorFiltro;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\CampoDisponible;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta una DefinicionReporte ya validada y devuelve un generator lazy.
 *
 * Reglas (§ CLAUDE.md):
 * - WHERE proyecto_id = X SIEMPRE primero, antes de cualquier filtro DSL.
 * - Soft delete eliminada_en IS NULL aplicado a entidades históricas.
 * - SELECT/WHERE/GROUP/ORDER usan SOLO expresiones de CampoDisponible::$sql (whitelist).
 * - Los valores de filtro siempre van por bindings parametrizados.
 */
final class EjecutarReporte
{
    public function __construct(
        private readonly ServicioCamposPersonalizadosReporte $servicioCp,
    ) {}

    public function execute(DefinicionReporte $def, ?int $limite = null): ResultadoEjecucionReporte
    {
        $catalogo = new CatalogoCamposReporte(
            $def->entidad,
            $this->servicioCp->obtenerCampos($def->entidad, $def->proyectoId),
        );

        $def->validar($catalogo); // defensivo: el llamador validó al guardar/cargar.

        $clavesUsadas = $this->recolectarClaves($def);
        $campos = [];
        $joinKeys = [];
        $cpJoins = []; // [cpId => CampoDisponible]
        foreach ($clavesUsadas as $clave) {
            $campos[$clave] = $catalogo->obtener($clave);
            $jk = $campos[$clave]->joinKey;
            if ($jk !== null) {
                $joinKeys[] = $jk;
            }
            if ($campos[$clave]->esCampoPersonalizado && $campos[$clave]->campoPersonalizadoId !== null) {
                $cpJoins[$campos[$clave]->campoPersonalizadoId] = $campos[$clave];
            }
        }

        $tabla = $def->entidad->tablaBase();
        $q = DB::table($tabla);

        foreach ($catalogo->joinsPara($joinKeys) as $j) {
            $q->leftJoin($j['tabla'].' as '.$j['alias'], $j['col_a'], '=', $j['col_b']);
        }

        foreach ($cpJoins as $cpId => $campo) {
            $alias = "vcp_{$cpId}";
            $q->leftJoin(
                'valores_campo_personalizado as '.$alias,
                function ($join) use ($alias, $cpId, $tabla): void {
                    $join->on($alias.'.entidad_id', '=', $tabla.'.id')
                        ->where($alias.'.campo_personalizado_id', '=', $cpId);
                },
            );
        }

        $q->where($tabla.'.proyecto_id', '=', $def->proyectoId);

        if (in_array($tabla, ['casos', 'gestiones', 'compromisos', 'personas'], true)) {
            $q->whereNull($tabla.'.eliminada_en');
        }

        $cabeceras = [];
        $selectExprs = [];
        $i = 0;
        foreach ($def->columnas as $col) {
            $alias = "col_{$i}";
            $campo = $campos[$col->campo];
            $expr = $col->agregacion !== null
                ? $col->agregacion->aSql().'('.$campo->sql.')'
                : $campo->sql;
            $selectExprs[] = new Expression($expr.' as '.$alias);
            $cabeceras[] = ['clave' => $col->campo, 'etiqueta' => $col->etiqueta];
            $i++;
        }
        $q->select($selectExprs);

        foreach ($def->filtros as $filtro) {
            $this->aplicarFiltro($q, $campos[$filtro->campo], $filtro->operador, $filtro->valor);
        }

        if ($def->agrupaciones !== []) {
            $q->groupBy(array_map(
                static fn (string $clave) => new Expression($campos[$clave]->sql),
                $def->agrupaciones,
            ));
        }

        foreach ($def->orden as $o) {
            $q->orderBy(new Expression($campos[$o->campo]->sql), $o->direccion);
        }

        if ($limite !== null) {
            $q->limit($limite);
        }

        $generator = (function () use ($q): \Generator {
            foreach ($q->cursor() as $row) {
                yield (array) $row;
            }
        })();

        return new ResultadoEjecucionReporte($cabeceras, $generator);
    }

    /**
     * @return list<string>
     */
    private function recolectarClaves(DefinicionReporte $def): array
    {
        $set = [];
        foreach ($def->columnas as $c) {
            $set[$c->campo] = true;
        }
        foreach ($def->filtros as $f) {
            $set[$f->campo] = true;
        }
        foreach ($def->agrupaciones as $a) {
            $set[$a] = true;
        }
        foreach ($def->orden as $o) {
            $set[$o->campo] = true;
        }

        return array_keys($set);
    }

    private function aplicarFiltro(Builder $q, CampoDisponible $campo, OperadorFiltro $op, mixed $valor): void
    {
        $expr = new Expression($campo->sql);
        match ($op) {
            OperadorFiltro::IGUAL => $q->where($expr, '=', $valor),
            OperadorFiltro::DISTINTO => $q->where($expr, '<>', $valor),
            OperadorFiltro::MAYOR => $q->where($expr, '>', $valor),
            OperadorFiltro::MENOR => $q->where($expr, '<', $valor),
            OperadorFiltro::CONTIENE => $q->where($expr, 'like', '%'.$this->escaparLike((string) $valor).'%'),
            OperadorFiltro::EMPIEZA => $q->where($expr, 'like', $this->escaparLike((string) $valor).'%'),
            OperadorFiltro::TERMINA => $q->where($expr, 'like', '%'.$this->escaparLike((string) $valor)),
            OperadorFiltro::ENTRE => $q->whereBetween($expr, $this->dosValores($valor)),
            OperadorFiltro::EN_LISTA => $q->whereIn($expr, $this->lista($valor)),
            OperadorFiltro::VACIO => $q->whereNull($expr),
            OperadorFiltro::NO_VACIO => $q->whereNotNull($expr),
        };
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    /**
     * @return array{0: mixed, 1: mixed}
     */
    private function dosValores(mixed $valor): array
    {
        if (! is_array($valor) || count($valor) !== 2) {
            throw new \InvalidArgumentException('Operador "entre" requiere un array de dos valores.');
        }

        return [array_values($valor)[0], array_values($valor)[1]];
    }

    /**
     * @return list<mixed>
     */
    private function lista(mixed $valor): array
    {
        if (! is_array($valor) || $valor === []) {
            throw new \InvalidArgumentException('Operador "en_lista" requiere un array no vacío.');
        }

        return array_values($valor);
    }
}
