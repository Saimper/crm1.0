<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

/**
 * Qué clase de contacto trae una columna, además de lo que ya haga con ella.
 *
 * Es ortogonal a `AccionColumna`: una columna puede guardarse como campo
 * personalizado —para que siga viéndose en la ficha— y además generar filas en
 * `contactos`. Son dos cosas distintas y las dos hacen falta.
 *
 * Las tres formas salen de los datos reales, no de un catálogo inventado:
 * columnas con un teléfono por celda, columnas con varios separados por
 * espacios, y columnas de referencias con el nombre de quien contesta entre
 * paréntesis.
 */
enum RolContacto: string
{
    case NINGUNO = 'ninguno';
    case TELEFONO = 'telefono';
    case CORREO = 'correo';
    case REFERENCIA = 'referencia';

    public static function desdeEncabezado(string $header): self
    {
        $normalized = \Normalizer::normalize(mb_strtolower($header), \Normalizer::FORM_D);
        $normalized = (string) preg_replace('/[\p{Mn}\s_\-\d]+/u', '', (string) $normalized);

        return match ($normalized) {
            'correo', 'correos', 'correoelectronico', 'email', 'emails', 'mail' => self::CORREO,
            'telefono', 'telefonos', 'telefonocelular', 'telefonofijo', 'celular', 'celulares',
            'movil', 'moviles', 'contacto', 'whatsapp', 'phone', 'mobile' => self::TELEFONO,
            'telefonoreferencia', 'telefonosreferencias', 'contactoreferencia' => self::REFERENCIA,
            default => self::NINGUNO,
        };
    }

    public function etiquetaClave(): string
    {
        return 'importaciones.rol_contacto.'.$this->value;
    }
}
