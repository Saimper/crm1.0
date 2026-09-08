<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Cobranza\Application\UseCases\CancelarPromesa;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaRota;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Puebla un proyecto de cobranza con una jornada operativa creíble: 90 días de
 * gestiones repartidas entre varios gestores, promesas de pago en los cuatro
 * estados del ciclo, y asignaciones vivas.
 *
 * Existe porque el entorno local tiene cartera importada (17.888 casos) pero
 * casi ninguna actividad (38 gestiones, 0 compromisos), y sin actividad no se
 * puede validar ni un KPI, ni el ciclo de vida de una promesa, ni una jornada
 * de agente.
 *
 * Registra a través de los UseCases reales, no con inserts: así se disparan los
 * listeners, se crean las CTI y se mantienen las desnormalizaciones de `casos`
 * (§4). Un insert directo produciría datos que el dominio nunca habría aceptado.
 *
 * NO SE EJECUTA EN PRODUCCIÓN: aborta si el entorno no es local/testing.
 */
final class EscenarioOperativoCobranzaSeeder extends Seeder
{
    /** Proyecto destino. Sobreescribible con SEED_PROYECTO_ID. */
    private const PROYECTO_POR_DEFECTO = 8;

    /** Días de historia hacia atrás desde hoy. */
    private const DIAS = 90;

    /** Gestiones por gestor y día laborable (se reparte con ruido). */
    private const GESTIONES_DIA = 14;

    /**
     * Semilla fija: dos ejecuciones producen el mismo escenario. Sin esto, cada
     * corrida daría cifras distintas y ningún KPI sería comparable entre pruebas.
     */
    private const SEMILLA = 20260907;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('Este seeder solo corre en local/testing. Abortado.');

            return;
        }

        mt_srand(self::SEMILLA);

        $proyectoId = (int) (env('SEED_PROYECTO_ID') ?: self::PROYECTO_POR_DEFECTO);
        $catalogo = $this->catalogo($proyectoId);

        if ($catalogo === null) {
            $this->command?->error("El proyecto {$proyectoId} no es de cobranza o le faltan catálogos.");

            return;
        }

        $gestores = $this->asegurarGestores($proyectoId);
        $casos = $this->casosGestionables($proyectoId);

        if ($casos === []) {
            $this->command?->error("El proyecto {$proyectoId} no tiene casos con contacto.");

            return;
        }

        $this->command?->info(sprintf(
            'Sembrando %d días sobre proyecto %d: %d gestores, %d casos disponibles.',
            self::DIAS, $proyectoId, count($gestores), count($casos)
        ));

        $hoy = new DateTimeImmutable('today');
        $registrar = app(RegistrarGestion::class);

        $gestionesCreadas = 0;
        $promesasCreadas = [];
        $casoCursor = 0;

        for ($d = self::DIAS; $d >= 0; $d--) {
            $dia = $hoy->sub(new DateInterval("P{$d}D"));

            // Domingo no se gestiona; sábado a media máquina. Sin esta curva la
            // serie temporal sale plana y cualquier gráfico de tendencia miente.
            $diaSemana = (int) $dia->format('N');
            if ($diaSemana === 7) {
                continue;
            }
            $factor = $diaSemana === 6 ? 0.4 : 1.0;

            foreach ($gestores as $gestor) {
                $cuota = (int) round(self::GESTIONES_DIA * $factor * $gestor['ritmo'] * $this->ruido());

                for ($i = 0; $i < $cuota; $i++) {
                    $caso = $casos[$casoCursor % count($casos)];
                    $casoCursor++;

                    $combo = $this->comboAleatorio($catalogo['combos']);
                    $momento = $dia->setTime(8, 0)->add(new DateInterval('PT'.mt_rand(0, 9 * 3600).'S'));

                    $datosPromesa = null;
                    if ($combo['requiere_compromiso']) {
                        // Vencimientos repartidos: unos ya pasaron, otros están por venir.
                        $diasHasta = mt_rand(-20, 25);
                        $datosPromesa = new DatosPromesaPago(
                            monto: new MontoPromesa(number_format(mt_rand(2500, 180000) / 100, 2, '.', '')),
                            fechaVencimiento: new FechaPromesa(
                                $diasHasta >= 0
                                    ? $momento->add(new DateInterval("P{$diasHasta}D"))->setTime(0, 0)
                                    : $momento->sub(new DateInterval('P'.abs($diasHasta).'D'))->setTime(0, 0)
                            ),
                            tipoPagoId: $catalogo['tipo_pago_id'],
                        );
                    }

                    try {
                        $salida = $registrar->execute(new RegistrarGestionInput(
                            publicId: (string) Str::ulid(),
                            proyectoId: $proyectoId,
                            casoId: $caso['id'],
                            personaId: $caso['persona_id'],
                            contactoId: $caso['contacto_id'],
                            canalId: $this->canalAleatorio($catalogo['canales']),
                            tipoGestionId: $combo['tipo_gestion_id'],
                            resultadoId: $combo['resultado_id'],
                            motivoNoContactoId: null,
                            causaId: $combo['requiere_causa'] ? $this->uno($catalogo['causas']) : null,
                            usuarioId: $gestor['id'],
                            notas: $this->nota($combo['resultado_codigo']),
                            duracion: new DuracionSegundos(mt_rand(45, 900)),
                            creadaEn: $momento,
                            datosCompromiso: $datosPromesa,
                        ));
                    } catch (\Throwable $e) {
                        $this->command?->warn('Gestión descartada: '.$e->getMessage());

                        continue;
                    }

                    $gestionesCreadas++;

                    if ($datosPromesa !== null) {
                        $promesasCreadas[] = [
                            'gestion_id' => $salida->id,
                            'momento' => $momento,
                        ];
                    }
                }
            }
        }

        // El listener sella el compromiso con `new DateTimeImmutable` (hoy), no
        // con la fecha de la gestión que lo originó. Para una historia de 90
        // días eso amontona los 400 compromisos en el día de la carga y arruina
        // cualquier serie temporal, así que aquí se realinean.
        foreach ($promesasCreadas as $p) {
            DB::table('compromisos')
                ->where('gestion_origen_id', $p['gestion_id'])
                ->update(['creada_en' => $p['momento']->format('Y-m-d H:i:s')]);
        }

        $this->command?->info("Gestiones registradas: {$gestionesCreadas}. Promesas creadas: ".count($promesasCreadas).'.');

        $this->resolverPromesas($proyectoId, $hoy);
        $this->repartirAsignaciones($proyectoId, $gestores);

        $this->resumen($proyectoId);
    }

    /**
     * Lleva parte de las promesas a un estado terminal, dejando a propósito un
     * grupo VENCIDO Y AÚN PENDIENTE: es el estado que el sistema produce hoy por
     * sí solo, porque nada automatiza el paso pendiente → roto.
     */
    private function resolverPromesas(int $proyectoId, DateTimeImmutable $hoy): void
    {
        $cumplida = app(MarcarPromesaCumplida::class);
        $rota = app(MarcarPromesaRota::class);
        $cancelada = app(CancelarPromesa::class);

        $vencidas = DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->whereDate('fecha_vencimiento', '<', $hoy->format('Y-m-d'))
            ->orderBy('id')
            ->get(['id', 'fecha_vencimiento']);

        $c = $r = $x = $intactas = 0;

        foreach ($vencidas as $i => $compromiso) {
            $vencimiento = new DateTimeImmutable((string) $compromiso->fecha_vencimiento);
            $resolucion = $vencimiento->add(new DateInterval('P'.mt_rand(0, 3).'D'));
            if ($resolucion > $hoy) {
                $resolucion = $hoy;
            }
            $input = new ResolverCompromisoInput((int) $compromiso->id, $resolucion);

            // 45% se paga, 35% se rompe, 8% se cancela, 12% se queda tal cual.
            $dado = $i % 100;
            match (true) {
                $dado < 45 => [$cumplida->execute($input), $c++],
                $dado < 80 => [$rota->execute($input), $r++],
                $dado < 88 => [$cancelada->execute($input), $x++],
                default => $intactas++,
            };
        }

        $this->command?->info("Promesas vencidas resueltas — cumplidas: {$c}, rotas: {$r}, canceladas: {$x}, sin tocar: {$intactas}.");
    }

    /**
     * Reparte casos con actividad entre los gestores para que la bandeja de cada
     * uno y la del equipo tengan contenido.
     *
     * @param  list<array{id:int,nombre:string,ritmo:float}>  $gestores
     */
    private function repartirAsignaciones(int $proyectoId, array $gestores): void
    {
        $candidatos = DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->whereNotNull('fecha_ultima_gestion')
            ->whereNotExists(fn ($q) => $q->from('asignaciones')
                ->whereColumn('asignaciones.caso_id', 'casos.id')
                ->where('asignaciones.proyecto_id', $proyectoId))
            ->orderByDesc('fecha_ultima_gestion')
            ->limit(600)
            ->pluck('id');

        $filas = [];
        $ahora = now();
        foreach ($candidatos as $i => $casoId) {
            $gestor = $gestores[$i % count($gestores)];
            $filas[] = [
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'caso_id' => $casoId,
                'usuario_id' => $gestor['id'],
                'fecha_asignacion' => $ahora->toDateString(),
                'prioridad' => $i % 10,
                // El estado se sortea aparte del gestor a propósito: repartir con
                // el mismo `% count($gestores)` los correlaciona, y cada gestor
                // acaba con un único estado en su bandeja.
                'estado' => match (mt_rand(0, 9)) {
                    0, 1 => 'cerrada',
                    2, 3, 4 => 'en_trabajo',
                    default => 'pendiente',
                },
                'cerrada_en' => null,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('asignaciones')->insert($lote);
        }

        $this->command?->info('Asignaciones creadas: '.count($filas).'.');
    }

    /**
     * Garantiza un plantel de gestores con ritmos distintos: un reporte de
     * productividad donde todos rinden igual no permite comprobar si ordena bien.
     *
     * @return list<array{id:int,nombre:string,ritmo:float}>
     */
    private function asegurarGestores(int $proyectoId): array
    {
        $rolGestor = DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $plantel = [
            ['email' => 'ana.morales@demo.local', 'nombre' => 'Ana Morales', 'ritmo' => 1.25],
            ['email' => 'luis.paredes@demo.local', 'nombre' => 'Luis Paredes', 'ritmo' => 1.0],
            ['email' => 'karen.rojas@demo.local', 'nombre' => 'Karen Rojas', 'ritmo' => 0.75],
            ['email' => 'diego.santos@demo.local', 'nombre' => 'Diego Santos', 'ritmo' => 0.55],
        ];

        $gestores = [];

        foreach ($plantel as $p) {
            $id = DB::table('users')->where('email', $p['email'])->value('id');

            if ($id === null) {
                $id = DB::table('users')->insertGetId([
                    'name' => $p['nombre'],
                    'email' => $p['email'],
                    'password' => Hash::make('demo1234'),
                    'email_verified_at' => now(),
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('usuario_proyecto_rol')->updateOrInsert(
                ['usuario_id' => $id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolGestor],
                []
            );

            $gestores[] = ['id' => (int) $id, 'nombre' => $p['nombre'], 'ritmo' => $p['ritmo']];
        }

        // Los gestores que ya existían en el proyecto siguen contando.
        $preexistentes = DB::table('usuario_proyecto_rol')
            ->where('proyecto_id', $proyectoId)
            ->where('rol_id', $rolGestor)
            ->whereNotIn('usuario_id', array_column($gestores, 'id'))
            ->pluck('usuario_id');

        foreach ($preexistentes as $uid) {
            $gestores[] = [
                'id' => (int) $uid,
                'nombre' => (string) DB::table('users')->where('id', $uid)->value('name'),
                'ritmo' => 0.9,
            ];
        }

        return $gestores;
    }

    /**
     * Casos con al menos un contacto: gestionar sin contacto es posible en el
     * modelo (contacto_id es nullable) pero no es el caso realista a medir.
     *
     * @return list<array{id:int,persona_id:int,contacto_id:int|null}>
     */
    private function casosGestionables(int $proyectoId): array
    {
        $filas = DB::table('casos as c')
            ->leftJoin('contactos as ct', function ($j) {
                $j->on('ct.persona_id', '=', 'c.persona_id')->whereNull('ct.eliminada_en');
            })
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en')
            ->groupBy('c.id', 'c.persona_id')
            ->orderBy('c.id')
            ->limit(2500)
            ->get(['c.id', 'c.persona_id', DB::raw('MIN(ct.id) as contacto_id')]);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
            'persona_id' => (int) $f->persona_id,
            'contacto_id' => $f->contacto_id !== null ? (int) $f->contacto_id : null,
        ])->all();
    }

    /**
     * Lee del proyecto lo que de verdad tiene configurado. No se inventan
     * catálogos: si el proyecto no declara una combinación tipo × resultado, no
     * se usa, porque el UseCase la rechazaría.
     *
     * @return array{combos:list<array<string,mixed>>,canales:list<int>,causas:list<int>,tipo_pago_id:int|null}|null
     */
    private function catalogo(int $proyectoId): ?array
    {
        $esCobranza = DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('tipo_operacion', 'cobranza')
            ->exists();

        if (! $esCobranza) {
            return null;
        }

        $combos = DB::table('resultado_tipo_gestion as rtg')
            ->join('resultados as r', 'r.id', '=', 'rtg.resultado_id')
            ->where('rtg.proyecto_id', $proyectoId)
            ->get(['rtg.tipo_gestion_id', 'rtg.resultado_id', 'r.codigo as resultado_codigo', 'r.requiere_compromiso', 'r.requiere_causa'])
            ->map(fn ($c) => [
                'tipo_gestion_id' => (int) $c->tipo_gestion_id,
                'resultado_id' => (int) $c->resultado_id,
                'resultado_codigo' => (string) $c->resultado_codigo,
                'requiere_compromiso' => (bool) $c->requiere_compromiso,
                'requiere_causa' => (bool) $c->requiere_causa,
            ])->all();

        $canales = DB::table('canal_proyecto')->where('proyecto_id', $proyectoId)->pluck('canal_id')
            ->map(fn ($v) => (int) $v)->all();
        $causas = DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->pluck('id')
            ->map(fn ($v) => (int) $v)->all();

        if ($combos === [] || $canales === []) {
            return null;
        }

        return [
            'combos' => $combos,
            'canales' => $canales,
            'causas' => $causas,
            'tipo_pago_id' => DB::table('tipos_pago')->where('proyecto_id', $proyectoId)->value('id'),
        ];
    }

    /**
     * Distribución realista de resultados: el contacto efectivo es minoría y la
     * promesa lo es aún más. Con una uniforme, la contactabilidad daría ~50% y
     * ningún KPI de cobranza se parecería a la realidad.
     *
     * @param  list<array<string,mixed>>  $combos
     * @return array<string,mixed>
     */
    private function comboAleatorio(array $combos): array
    {
        $pesos = [
            'OBTUVE1' => 6,
            'PROMESA_DE_PAGO_FRACCIONADO' => 4,
            'CONTACTO_CON_TITULAR' => 18,
            'CONTACTO_CON_FAMILIAR' => 12,
            'CONTACTO_CON_REFERENCIA' => 8,
            'RENUENTE' => 10,
            'PROMESA_INCUMPLIDA' => 7,
            'FALLECIDO' => 2,
            'DETENIDO' => 2,
        ];

        $bolsa = [];
        foreach ($combos as $c) {
            $peso = $pesos[$c['resultado_codigo']] ?? 5;
            for ($i = 0; $i < $peso; $i++) {
                $bolsa[] = $c;
            }
        }

        return $bolsa[mt_rand(0, count($bolsa) - 1)];
    }

    /** Teléfono manda; los demás canales son cola larga. */
    private function canalAleatorio(array $canales): int
    {
        return mt_rand(1, 100) <= 65 ? $canales[0] : $this->uno($canales);
    }

    private function uno(array $valores): int
    {
        return $valores[mt_rand(0, count($valores) - 1)];
    }

    private function ruido(): float
    {
        return mt_rand(70, 130) / 100;
    }

    private function nota(string $resultado): string
    {
        $frases = [
            'OBTUVE1' => 'Titular acepta el compromiso y confirma medio de pago.',
            'PROMESA_DE_PAGO_FRACCIONADO' => 'Acuerda abonar en parcialidades; se registra la primera cuota.',
            'CONTACTO_CON_TITULAR' => 'Se conversa con el titular, solicita volver a llamar.',
            'CONTACTO_CON_FAMILIAR' => 'Atiende un familiar, se deja recado sin detalle de la deuda.',
            'CONTACTO_CON_REFERENCIA' => 'Referencia confirma el número pero no ubica al titular.',
            'RENUENTE' => 'Se niega a llegar a un acuerdo en esta gestión.',
            'PROMESA_INCUMPLIDA' => 'No se registró el pago comprometido; se reagenda.',
            'FALLECIDO' => 'Se informa fallecimiento del titular, pendiente documentación.',
            'DETENIDO' => 'Se informa que el titular se encuentra detenido.',
        ];

        return $frases[$resultado] ?? 'Gestión registrada.';
    }

    private function resumen(int $proyectoId): void
    {
        $g = DB::table('gestiones')->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')->count();
        $estados = DB::table('compromisos')->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
            ->selectRaw('estado, count(*) c')->groupBy('estado')->pluck('c', 'estado');
        $vencidasPendientes = DB::table('compromisos')->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')->whereNull('eliminada_en')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())->count();

        $this->command?->newLine();
        $this->command?->info("Escenario listo en proyecto {$proyectoId}:");
        $this->command?->line("  gestiones: {$g}");
        foreach ($estados as $estado => $n) {
            $this->command?->line("  compromisos {$estado}: {$n}");
        }
        $this->command?->line("  compromisos VENCIDOS que siguen 'pendiente': {$vencidasPendientes}");
    }
}
