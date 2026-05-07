<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera plantilla XLSX para que el usuario llene y suba al wizard de importación.
 *
 * - Hoja "Datos": cabeceras coloreadas (req = rojo claro, opt = gris claro) + 1 fila ejemplo.
 * - Hoja "Instrucciones": tabla con campo, etiqueta, requerido, tipo, descripción, ejemplo,
 *   y para tipo=codigo_catalogo, los códigos válidos vigentes en el proyecto activo.
 */
final class DescargarPlantillaImportacionController
{
    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        abort_unless($request->user()?->tienePermiso('importaciones.crear', $proyecto_id) === true, 403);

        $targetValor = (string) $request->query('target', '');
        $target = TargetImportacion::tryFrom($targetValor);
        abort_if($target === null, 404, 'Target de importación inválido.');

        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;
        if (! in_array($target, CatalogoCamposSistema::targetsDisponibles($tipoOperacion), true)) {
            abort(403, 'Este target no aplica al tipo de proyecto.');
        }

        $campos = CatalogoCamposSistema::paraTarget($target);
        $valoresCatalogo = $this->valoresCatalogoPorCampo($campos, $proyecto_id);
        $filename = 'plantilla_'.$target->value.'_'.now()->format('Ymd_His').'.xlsx';

        return new StreamedResponse(function () use ($campos, $valoresCatalogo, $target): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $this->escribirHojaDatos($writer, $campos, $target);
            $writer->addNewSheetAndMakeItCurrent();
            $this->escribirHojaInstrucciones($writer, $campos, $valoresCatalogo);

            $writer->close();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @param list<CampoSistema> $campos */
    private function escribirHojaDatos(Writer $writer, array $campos, TargetImportacion $target): void
    {
        $writer->getCurrentSheet()->setName('Datos');

        $estiloReq = (new Style)
            ->setBackgroundColor('FECACA')
            ->setFontBold()
            ->setFontColor('7F1D1D');
        $estiloOpt = (new Style)
            ->setBackgroundColor('E5E7EB')
            ->setFontBold()
            ->setFontColor('374151');
        $estiloEjemplo = (new Style)
            ->setBackgroundColor('FEF3C7')
            ->setFontColor('78350F');

        $celdasCabecera = [];
        foreach ($campos as $c) {
            $celdasCabecera[] = Cell::fromValue($c->codigo, $c->requerido ? $estiloReq : $estiloOpt);
        }
        $writer->addRow(new Row($celdasCabecera, null));

        $celdasEjemplo = [];
        foreach ($campos as $c) {
            $celdasEjemplo[] = Cell::fromValue($c->ejemplo ?? '', $estiloEjemplo);
        }
        $writer->addRow(new Row($celdasEjemplo, null));
    }

    /**
     * @param  list<CampoSistema>  $campos
     * @param  array<string, list<string>>  $valoresCatalogo
     */
    private function escribirHojaInstrucciones(Writer $writer, array $campos, array $valoresCatalogo): void
    {
        $writer->getCurrentSheet()->setName('Instrucciones');

        $estiloHeader = (new Style)
            ->setBackgroundColor('1E40AF')
            ->setFontBold()
            ->setFontColor('FFFFFF');

        $writer->addRow(Row::fromValues(
            ['Campo (código)', 'Etiqueta', 'Requerido', 'Tipo', 'Ejemplo', 'Valores válidos / Notas'],
            $estiloHeader,
        ));

        foreach ($campos as $c) {
            $valoresValidos = '';
            if ($c->tipo === 'codigo_catalogo' && isset($valoresCatalogo[$c->codigo])) {
                $valoresValidos = implode(', ', $valoresCatalogo[$c->codigo]);
                if ($valoresValidos === '') {
                    $valoresValidos = '(no hay códigos definidos en el proyecto)';
                }
            } elseif ($c->descripcion !== null) {
                $valoresValidos = $c->descripcion;
            }

            $writer->addRow(Row::fromValues([
                $c->codigo,
                $c->etiqueta,
                $c->requerido ? 'Sí' : 'No',
                $c->tipo,
                $c->ejemplo ?? '',
                $valoresValidos,
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Notas generales:'], (new Style)->setFontBold()));
        $writer->addRow(Row::fromValues(['• Las cabeceras (fila 1) son los códigos canónicos. Puedes renombrarlas: el wizard te dejará mapear cualquier nombre.']));
        $writer->addRow(Row::fromValues(['• Las celdas en rojo claro son requeridas; las grises son opcionales.']));
        $writer->addRow(Row::fromValues(['• Fechas en formato ISO: YYYY-MM-DD o YYYY-MM-DD HH:MM:SS.']));
        $writer->addRow(Row::fromValues(['• Decimales con punto: 4500.00 (no coma).']));
        $writer->addRow(Row::fromValues(['• Códigos catálogo deben coincidir con los del proyecto activo (case-insensitive).']));
    }

    /**
     * @param  list<CampoSistema>  $campos
     * @return array<string, list<string>>
     */
    private function valoresCatalogoPorCampo(array $campos, int $proyectoId): array
    {
        $globales = ['tipos_identificacion'];
        $out = [];
        foreach ($campos as $c) {
            if ($c->tipo !== 'codigo_catalogo' || $c->catalogoCodigo === null) {
                continue;
            }
            $tabla = $c->catalogoCodigo;
            $q = DB::table($tabla);
            if (! in_array($tabla, $globales, true)) {
                $q->where('proyecto_id', $proyectoId);
            }
            $codigos = $q->where('activo', true)
                ->orderBy('codigo')
                ->limit(50)
                ->pluck('codigo')
                ->map(static fn ($v): string => (string) $v)
                ->all();

            $out[$c->codigo] = $codigos;
        }

        return $out;
    }
}
