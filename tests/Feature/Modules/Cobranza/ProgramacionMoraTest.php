<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * El scheduler corre en UTC y cada cliente tiene su medianoche: el avance de
 * la mora va cada hora, y siempre antes de reclasificar tramos, que se calculan
 * sobre `dias_mora`.
 */
final class ProgramacionMoraTest extends TestCase
{
    public function test_el_avance_de_la_mora_esta_programado_cada_hora_antes_que_los_tramos(): void
    {
        $eventos = $this->eventos();

        $avance = $this->buscar($eventos, 'cobranza:avanzar-dias-mora');
        $tramos = $this->buscar($eventos, 'cobranza:asignar-tramos-mora');

        $this->assertNotNull($avance, 'El envejecimiento tiene que estar en el scheduler.');
        $this->assertNotNull($tramos, 'La reclasificación tiene que seguir en el scheduler.');

        // Cada hora, no a una hora fija: a las 03:50 UTC en Panamá todavía es ayer.
        $this->assertSame('10 * * * *', $avance->getExpression());
        $this->assertSame('25 * * * *', $tramos->getExpression());

        // Antes en el reloj y antes en la lista: los tramos leen lo que el
        // avance escribe.
        $this->assertLessThan($this->minuto($tramos), $this->minuto($avance));
        $this->assertLessThan($eventos->search($tramos), $eventos->search($avance));
    }

    public function test_ninguna_de_las_dos_tareas_se_solapa_consigo_misma(): void
    {
        $eventos = $this->eventos();

        foreach (['cobranza:avanzar-dias-mora', 'cobranza:asignar-tramos-mora'] as $comando) {
            $evento = $this->buscar($eventos, $comando);
            $this->assertNotNull($evento);
            $this->assertTrue($evento->withoutOverlapping, "{$comando} sin withoutOverlapping.");
        }
    }

    /** @return Collection<int, Event> */
    private function eventos(): Collection
    {
        return collect($this->app->make(Schedule::class)->events());
    }

    /** @param  Collection<int, Event>  $eventos */
    private function buscar(Collection $eventos, string $comando): ?Event
    {
        return $eventos->first(fn (Event $e): bool => str_contains((string) $e->command, $comando));
    }

    private function minuto(Event $evento): int
    {
        return (int) explode(' ', $evento->getExpression())[0];
    }
}
