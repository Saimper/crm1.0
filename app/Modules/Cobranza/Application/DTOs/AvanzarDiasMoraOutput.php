<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\DTOs;

final readonly class AvanzarDiasMoraOutput
{
    /**
     * Los totales son lo que decide si hay que avisar; los desgloses por
     * proyecto son para que el comando pueda informar línea a línea.
     *
     * @param  array<int, int>  $avanzadosPorProyecto  proyecto_id => cuentas avanzadas (o que se avanzarían, en simulacro)
     * @param  int  $enTope  Cuentas que no se tocaron porque el avance las dejaría por encima del techo del VO.
     * @param  int  $sinFecha  Cuentas con mora y sin ancla: no se sabe a qué día corresponde su valor.
     * @param  int  $sinConfirmarHaceTiempo  Cuentas abiertas con mora que ninguna fuente ha confirmado en más de DiasMora::DIAS_SIN_CONFIRMAR_AVISO días.
     * @param  array<int, int>  $enTopePorProyecto
     * @param  array<int, int>  $sinFechaPorProyecto
     * @param  array<int, int>  $sinConfirmarPorProyecto
     */
    public function __construct(
        public array $avanzadosPorProyecto,
        public int $enTope,
        public int $sinFecha,
        public int $sinConfirmarHaceTiempo,
        public array $enTopePorProyecto = [],
        public array $sinFechaPorProyecto = [],
        public array $sinConfirmarPorProyecto = [],
    ) {}

    public function totalAvanzados(): int
    {
        return array_sum($this->avanzadosPorProyecto);
    }
}
