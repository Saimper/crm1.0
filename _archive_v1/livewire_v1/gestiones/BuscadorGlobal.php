<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class BuscadorGlobal extends Component
{
    public bool $abierto = false;

    public string $query = '';

    public function abrir(): void
    {
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->query = '';
    }

    public function render(): View
    {
        $texto = trim($this->query);

        $clientes = collect();
        $productos = collect();

        if (mb_strlen($texto) >= 3) {
            $like = "%{$texto}%";

            $clientes = DB::table('clientes')
                ->whereNull('eliminada_en')
                ->where(function ($w) use ($like): void {
                    $w->where('identificacion', 'like', $like)
                        ->orWhere('nombres', 'like', $like)
                        ->orWhere('apellidos', 'like', $like)
                        ->orWhere('razon_social', 'like', $like);
                })
                ->select([
                    'id', 'public_id', 'identificacion',
                    'nombres', 'apellidos', 'razon_social', 'tipo_persona',
                ])
                ->limit(8)
                ->get();

            $productos = DB::table('productos as p')
                ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
                ->whereNull('p.eliminada_en')
                ->where('p.numero_prestamo', 'like', $like)
                ->select([
                    'p.id as producto_id',
                    'p.public_id as producto_public_id',
                    'p.numero_prestamo',
                    'c.public_id as cliente_public_id',
                    'c.identificacion',
                    'c.nombres',
                    'c.apellidos',
                    'c.razon_social',
                    'c.tipo_persona',
                ])
                ->limit(8)
                ->get();
        }

        return view('gestiones::livewire.buscador-global', [
            'clientes' => $clientes,
            'productos' => $productos,
        ]);
    }
}
