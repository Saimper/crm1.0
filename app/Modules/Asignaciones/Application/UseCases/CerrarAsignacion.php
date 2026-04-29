<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Domain\Contracts\AsignacionRepository;
use App\Modules\Asignaciones\Domain\Entities\Asignacion;
use App\Modules\Asignaciones\Domain\Events\AsignacionCerrada;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CerrarAsignacion
{
    public function __construct(
        private AsignacionRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(int $asignacionId, DateTimeImmutable $cerradaEn): void
    {
        $this->db->transaction(function () use ($asignacionId, $cerradaEn): void {
            $a = $this->repositorio->buscarPorId($asignacionId);
            $cerrada = $a->cerrar($cerradaEn);
            $persistida = $this->repositorio->save($cerrada);

            $this->eventos->dispatch(new AsignacionCerrada(
                asignacionId: (int) $persistida->id,
                proyectoId:   $persistida->proyectoId,
                casoId:       $persistida->casoId,
                usuarioId:    $persistida->usuarioId,
                cerradaEn:    $cerradaEn,
            ));
        });
    }
}
