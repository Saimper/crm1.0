<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

/**
 * La importación crearía campos personalizados y quien la sube no tiene
 * `campos.definir`. Nombra los campos: quien lee el mensaje tiene que poder
 * pedirlos a un administrador global o marcar esas columnas como «Ignorar»,
 * y sin la lista sólo le queda adivinar cuál de las cuarenta columnas es.
 */
class ImportacionSinPermisoCamposException extends \DomainException implements MensajeAptoParaPantalla
{
    /**
     * @param  list<string>  $codigosNuevos
     */
    public function __construct(int $proyectoId, array $codigosNuevos)
    {
        parent::__construct(sprintf(
            'No tienes permiso para crear campos personalizados en el proyecto %d. '
            .'Esta carga crearía %d campo(s) nuevo(s) en la cartera: %s. '
            .'Pide a un administrador global que los cree, o marca esas columnas como «Ignorar».',
            $proyectoId,
            count($codigosNuevos),
            implode(', ', $codigosNuevos),
        ));
    }
}
