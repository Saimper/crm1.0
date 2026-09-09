<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Application\Console\Commands;

use App\Modules\Contactos\Domain\Contracts\AltaContactosEnLote;
use App\Modules\Contactos\Domain\ValueObjects\ContactoExtraido;
use App\Modules\Contactos\Domain\ValueObjects\ExtractorDeContactos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saca contactos de los campos personalizados que ya están cargados.
 *
 * La generación desde la importación sólo alcanza a lo que se importe de aquí en
 * adelante, y en la base hay 14.692 personas sin un solo contacto: el selector
 * «Contacto usado» de la Vista de Trabajo sale vacío para todas. Los teléfonos
 * están ahí, en campos de texto que la importación creó como texto porque no
 * sabía otra cosa.
 *
 * No corre solo. Se ejecuta a mano, por proyecto, y con `--dry-run` primero:
 * mete decenas de miles de filas y conviene mirar los números antes.
 *
 *   php artisan contactos:extraer-de-campos --proyecto=8 \
 *       --telefono=telefonos --referencia=referencias --correo=correo --dry-run
 */
final class ExtraerContactosDeCamposCommand extends Command
{
    protected $signature = 'contactos:extraer-de-campos
                            {--proyecto= : Proyecto sobre el que trabajar (obligatorio)}
                            {--telefono=* : Códigos de campo que contienen teléfonos}
                            {--correo=* : Códigos de campo que contienen correos}
                            {--referencia=* : Códigos de campo con nombre y número emparejados}
                            {--dry-run : Solo informa qué pasaría}';

    protected $description = 'Da de alta contactos a partir de campos personalizados ya cargados';

    public function handle(AltaContactosEnLote $alta): int
    {
        $proyectoId = (int) $this->option('proyecto');

        if ($proyectoId <= 0) {
            $this->error('Indica --proyecto=N.');

            return self::FAILURE;
        }

        /** @var array<string, list<string>> $porRol */
        $porRol = [
            'telefono' => (array) $this->option('telefono'),
            'correo' => (array) $this->option('correo'),
            'referencia' => (array) $this->option('referencia'),
        ];

        if (array_sum(array_map('count', $porRol)) === 0) {
            $this->error('Indica al menos un campo con --telefono, --correo o --referencia.');

            return self::FAILURE;
        }

        $campos = $this->resolverCampos($proyectoId, $porRol);

        if ($campos === []) {
            $this->error('Ninguno de esos códigos existe como campo de este proyecto.');

            return self::FAILURE;
        }

        $extractor = new ExtractorDeContactos;
        $seco = (bool) $this->option('dry-run');

        $totalPersonas = 0;
        $totalContactos = 0;

        // Los valores del ámbito caso se guardan contra el caso, así que hay que
        // saltar al caso para llegar a la persona, que es de quien cuelgan los
        // contactos.
        $query = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->join('casos as cs', 'cs.id', '=', 'v.entidad_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.ambito', 'caso')
            ->whereIn('c.id', array_keys($campos))
            ->whereNotNull('v.valor_texto_corto')
            ->where('v.valor_texto_corto', '!=', '')
            ->whereNull('cs.eliminada_en')
            ->orderBy('cs.persona_id')
            ->select(['cs.persona_id', 'c.id as campo_id', 'c.etiqueta', 'v.valor_texto_corto as bruto']);

        /** @var array<int, array<string, ContactoExtraido>> $porPersona */
        $porPersona = [];

        foreach ($query->cursor() as $fila) {
            $rol = $campos[(int) $fila->campo_id];
            $bruto = (string) $fila->bruto;

            $extraidos = match ($rol) {
                'telefono' => $extractor->telefonos($bruto, (string) $fila->etiqueta),
                'correo' => $extractor->correos($bruto),
                'referencia' => $extractor->referencias($bruto),
                default => [],
            };

            foreach ($extraidos as $contacto) {
                $porPersona[(int) $fila->persona_id][$contacto->clave()] = $contacto;
            }

            if (count($porPersona) >= 500) {
                [$p, $c] = $this->volcar($alta, $proyectoId, $porPersona, $seco);
                $totalPersonas += $p;
                $totalContactos += $c;
                $porPersona = [];
            }
        }

        [$p, $c] = $this->volcar($alta, $proyectoId, $porPersona, $seco);
        $totalPersonas += $p;
        $totalContactos += $c;

        $verbo = $seco ? 'se darían de alta' : 'dados de alta';
        $this->info("{$totalContactos} contactos {$verbo} sobre {$totalPersonas} personas.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $porRol
     * @return array<int, string> [campo_id => rol]
     */
    private function resolverCampos(int $proyectoId, array $porRol): array
    {
        $campos = [];

        foreach ($porRol as $rol => $codigos) {
            foreach ($codigos as $codigo) {
                $id = DB::table('campos_personalizados')
                    ->where('proyecto_id', $proyectoId)
                    ->where('codigo', $codigo)
                    ->value('id');

                if ($id === null) {
                    $this->warn("  El campo «{$codigo}» no existe en el proyecto {$proyectoId}; se salta.");

                    continue;
                }

                $campos[(int) $id] = $rol;
            }
        }

        return $campos;
    }

    /**
     * @param  array<int, array<string, ContactoExtraido>>  $porPersona
     * @return array{0: int, 1: int}
     */
    private function volcar(AltaContactosEnLote $alta, int $proyectoId, array $porPersona, bool $seco): array
    {
        $personas = 0;
        $contactos = 0;

        foreach ($porPersona as $personaId => $mapa) {
            $lista = array_values($mapa);
            $personas++;

            if ($seco) {
                $contactos += count($lista);

                continue;
            }

            $contactos += $alta->alta($proyectoId, $personaId, $lista, 'importacion');
        }

        return [$personas, $contactos];
    }
}
