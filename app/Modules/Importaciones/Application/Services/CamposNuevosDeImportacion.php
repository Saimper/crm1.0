<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionSinPermisoCamposException;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;

/**
 * Qué campos personalizados tendría que CREAR una importación.
 *
 * Es la única fuente de esa respuesta. `campos.definir` (§7, F23) protege la
 * definición de campos, no su uso: un SUPERVISOR sin ese permiso puede cargar
 * un archivo cuyas columnas ya existen como campos de la cartera, porque ahí
 * no se define nada. El asistente y el UseCase preguntan aquí; cuando cada
 * uno tenía su propia versión de la regla, la del asistente cortaba en cuanto
 * veía una columna marcada como campo personalizado, existiera o no, y la
 * supervisora que iba a hacer la carga no pudo subir un archivo cuyos 31
 * campos ya estaban creados.
 */
final readonly class CamposNuevosDeImportacion
{
    public function __construct(
        private CampoPersonalizadoImportacionRepository $cpRepo,
    ) {}

    /**
     * Códigos de las columnas que no existen todavía como campo de la cartera.
     *
     * @return list<string>
     */
    public function codigos(EsquemaImportacion $esquema): array
    {
        if ($esquema->carteraId === null || $esquema->modo === ModoImportacion::UPDATE) {
            return [];
        }

        $nuevos = [];

        foreach ($esquema->columnasParaCamposPersonalizados() as $columna) {
            $codigo = $columna->codigoSugerido();

            if (! $this->cpRepo->existeCampo($esquema->proyectoId, $esquema->carteraId, $codigo)) {
                $nuevos[] = $codigo;
            }
        }

        return $nuevos;
    }

    /**
     * Revienta si la importación crearía campos y quien la sube no puede definirlos.
     *
     * @throws ImportacionSinPermisoCamposException
     */
    public function exigirPermisoParaCrear(EsquemaImportacion $esquema, bool $tienePermisoCampos): void
    {
        if ($tienePermisoCampos) {
            return;
        }

        $nuevos = $this->codigos($esquema);

        if ($nuevos !== []) {
            throw new ImportacionSinPermisoCamposException($esquema->proyectoId, $nuevos);
        }
    }
}
