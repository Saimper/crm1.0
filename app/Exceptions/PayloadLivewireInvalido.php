<?php

declare(strict_types=1);

namespace App\Exceptions;

use ErrorException;
use Illuminate\Http\JsonResponse;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Exceptions\EventHandlerDoesNotExist;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\NonPublicComponentMethodCall;
use Livewire\Exceptions\PropertyNotFoundException;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLifecycleHooks\DirectlyCallingLifecycleHooksNotAllowedException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Throwable;
use TypeError;

/**
 * Clasifica las excepciones que Livewire lanza al recibir un payload que no
 * corresponde a ningún componente, acción o tipo real: escáneres que sondean
 * `/livewire/update`, snapshots manipulados o clientes de Livewire v2.
 *
 * No son fallos de la aplicación: se responden como 4xx y no se reportan al log,
 * en lugar de producir un 500 con stack trace por cada intento.
 */
final class PayloadLivewireInvalido
{
    private const EXCEPCIONES = [
        ComponentNotFoundException::class,
        CorruptComponentPayloadException::class,
        MethodNotFoundException::class,
        NonPublicComponentMethodCall::class,
        DirectlyCallingLifecycleHooksNotAllowedException::class,
        EventHandlerDoesNotExist::class,
        PublicPropertyNotFoundException::class,
        PropertyNotFoundException::class,
    ];

    /** Raíz del código de Livewire; la hidratación vive en HandleComponents y en los *Synth. */
    private const RAIZ_LIVEWIRE = 'vendor/livewire/livewire/src/';

    public static function aplica(Throwable $e): bool
    {
        foreach (self::EXCEPCIONES as $clase) {
            if ($e instanceof $clase) {
                return true;
            }
        }

        return self::esErrorDeHidratacion($e);
    }

    public static function responder(Throwable $e): JsonResponse
    {
        $status = $e instanceof ComponentNotFoundException ? 404 : 400;

        return new JsonResponse(['message' => 'Payload de Livewire inválido.'], $status);
    }

    /**
     * TypeError o ErrorException originados al asignar valores del tipo incorrecto a
     * propiedades públicas (p. ej. `email[]=` contra una propiedad `string`, o un
     * string donde va un Form object). Solo cuenta si el error se lanza desde la
     * hidratación de Livewire (HandleComponents o un *Synth), nunca desde código de la app.
     */
    private static function esErrorDeHidratacion(Throwable $e): bool
    {
        if (! $e instanceof TypeError && ! $e instanceof ErrorException) {
            return false;
        }

        $archivo = str_replace('\\', '/', $e->getFile());
        $raiz = strpos($archivo, self::RAIZ_LIVEWIRE);
        if ($raiz === false) {
            return false;
        }

        $relativo = substr($archivo, $raiz + strlen(self::RAIZ_LIVEWIRE));

        return str_starts_with($relativo, 'Mechanisms/HandleComponents/') || str_ends_with($relativo, 'Synth.php');
    }
}
