<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

/**
 * Marca las excepciones cuyo `getMessage()` puede enseñarse tal cual al
 * supervisor y guardarse en `importaciones.error_global`.
 *
 * Es una lista blanca y no una regla por jerarquía a propósito. La regla
 * «DomainException → mensaje limpio» parece razonable hasta que se mira lo
 * que interpolan los dominios vecinos: `DatosContactoInvalidos('Correo
 * inválido: x@y')`, `DatosCasoCobranzaInvalidos` con montos, y una
 * `QueryException` lleva el INSERT completo con los datos de la persona.
 * Sólo la implementan las excepciones de ESTE módulo cuyo texto es fijo y no
 * lleva datos de nadie; todo lo demás pasa por el descriptor, que resume la
 * clase y deja el detalle en el log bajo una referencia.
 */
interface MensajeAptoParaPantalla {}
