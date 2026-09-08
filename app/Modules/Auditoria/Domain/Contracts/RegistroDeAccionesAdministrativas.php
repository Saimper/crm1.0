<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Domain\Contracts;

/**
 * Deja constancia de lo que un administrador hace con las CUENTAS y con los
 * accesos que cuelgan de ellas: dar de alta a alguien, cambiarle el correo,
 * ponerle un rol en el proyecto de un cliente, abrirle todos los clientes a la
 * vez.
 *
 * Es un contrato y no el modelo porque quien administra usuarios vive en el
 * módulo Usuarios y no puede tocar el Eloquent de Auditoría (§3, §13.6).
 *
 * Y se registra a mano, no con el observer genérico, por dos razones que no son
 * de estilo:
 *
 *  a) La mitad de estas acciones no escriben en ninguna fila con modelo. El rol
 *     de un usuario en un proyecto es una fila de `usuario_proyecto_rol`: una
 *     pivote de clave compuesta que ningún observer Eloquent ve pasar.
 *  b) La otra mitad escribe en `users`, la tabla donde viven las credenciales de
 *     todo el mundo. El observer fotografía TODOS los atributos del modelo y
 *     quita después los sensibles por lista negra — una lista que hay que
 *     acordarse de ampliar el día que se añada una columna. Aquí lo que se
 *     guarda lo enumera quien llama, y el hash de la contraseña no está entre
 *     las opciones.
 *
 * Los tres verbos son los tres eventos que la tabla admite. `exportado`, el
 * cuarto, tiene su propio contrato (`RegistroDeExportaciones`).
 */
interface RegistroDeAccionesAdministrativas
{
    /**
     * Algo empezó a existir: una cuenta, un rol sobre un proyecto, el rol global.
     *
     * @param  string  $entidadTipo  La tabla escrita (`users`, `usuario_proyecto_rol`,
     *                               `usuario_global_rol`).
     * @param  int  $entidadId  A QUIÉN le pasó. En las pivotes no hay clave propia que
     *                          citar, y el sujeto del cambio siempre es el usuario cuyo
     *                          acceso se movió.
     * @param  array<string, mixed>  $datos  Lo que quedó escrito, en claro y legible por
     *                                       una persona (el código del rol, no su id).
     *                                       Nunca credenciales.
     * @param  int|null  $mandanteId  De quién es el evento. Si es nulo y hay proyecto, se
     *                                deduce del proyecto; si no, queda sin atribuir antes
     *                                que inventarle un dueño.
     */
    public function alta(
        string $entidadTipo,
        int $entidadId,
        array $datos,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void;

    /**
     * Algo cambió de valor.
     *
     * @param  array<string, array{antes: mixed, despues: mixed}>  $cambios  Mismo formato
     *                                                                       que los cambios del observer, para que el detalle de la
     *                                                                       pantalla los pinte sin un caso aparte. Sin cambios no se
     *                                                                       escribe nada: guardar sin tocar nada no es un evento.
     */
    public function cambio(
        string $entidadTipo,
        int $entidadId,
        array $cambios,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void;

    /**
     * Algo dejó de existir: un rol retirado, un acceso revocado.
     *
     * @param  array<string, mixed>  $datos  Lo que había antes de borrarlo. Es el único
     *                                       sitio donde queda: la fila ya no está.
     */
    public function baja(
        string $entidadTipo,
        int $entidadId,
        array $datos,
        ?int $proyectoId = null,
        ?int $mandanteId = null,
    ): void;
}
