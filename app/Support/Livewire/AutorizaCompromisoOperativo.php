<?php

declare(strict_types=1);

namespace App\Support\Livewire;

use App\Support\Database\CarterasOperativas;
use Illuminate\Support\Facades\DB;

trait AutorizaCompromisoOperativo
{
    protected function exigirCompromisoOperativo(int $commitmentId, string $permission): object
    {
        abort_unless(app()->bound('tenancy.proyecto_activo'), 403);
        $projectId = (int) app('tenancy.proyecto_activo')->id;
        $commitment = DB::table('compromisos')->where('proyecto_id', $projectId)->where('id', $commitmentId)->whereNull('eliminada_en')->first();
        abort_if($commitment === null, 404);
        try {
            $case = CarterasOperativas::exigirCaso(DB::connection(), $projectId, (int) $commitment->caso_id);
        } catch (\DomainException $error) {
            abort(409, $error->getMessage());
        }
        abort_unless(auth()->user()?->tienePermiso($permission, $projectId, (int) $case->cartera_id) === true, 403);

        return $commitment;
    }
}
