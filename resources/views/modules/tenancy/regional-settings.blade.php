<section class="mt-5 rounded-lg border border-ink-200 bg-ink-50 p-4 space-y-4">
    <div>
        <h3 class="font-semibold text-ink-900">Configuración regional</h3>
        <p class="text-xs text-ink-500 mt-1">Zona horaria, fechas y montos de esta operación.</p>
    </div>
    @if($projectId !== null)
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="inherit" /> Usar la configuración del mandante
        </label>
    @endif
    @if(!$inherit)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="field-label">Zona horaria
                <select wire:model.live="regional.zona_horaria" class="select mt-1">
                    @foreach($timezones as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach
                </select>
            </label>
            <label class="field-label">Moneda predeterminada
                <input wire:model.live="regional.moneda" list="regional-currencies-{{ $projectId ?? 'm'.$mandanteId }}" maxlength="3" class="input uppercase mt-1" />
                <datalist id="regional-currencies-{{ $projectId ?? 'm'.$mandanteId }}">@foreach($currencies as $currency)<option value="{{ $currency }}"></option>@endforeach</datalist>
            </label>
            <label class="field-label">Formato de fecha
                <select wire:model.live="regional.formato_fecha" class="select mt-1">
                    <option value="d/m/Y">Día / mes / año</option><option value="m/d/Y">Mes / día / año</option><option value="Y-m-d">Año - mes - día</option>
                </select>
            </label>
            <label class="field-label">Decimales guardados y mostrados
                <select wire:model.live="regional.decimales" class="select mt-1"><option value="2">2 decimales</option><option value="3">3 decimales</option></select>
            </label>
            <label class="field-label">Separador decimal
                <select wire:model.live="regional.separador_decimal" class="select mt-1"><option value=".">Punto (.)</option><option value=",">Coma (,)</option></select>
            </label>
            <label class="field-label">Separador de miles
                <select wire:model.live="regional.separador_miles" class="select mt-1"><option value=",">Coma (,)</option><option value=".">Punto (.)</option><option value=" ">Espacio</option><option value="">Sin separador</option></select>
            </label>
            <label class="field-label">Inicio de semana
                <select wire:model.live="regional.inicio_semana" class="select mt-1"><option value="1">Lunes</option><option value="7">Domingo</option></select>
            </label>
        </div>
    @endif
    <div class="rounded-md border border-brand-200 bg-white p-3 text-sm" aria-live="polite">
        <span class="text-ink-500">{{ $inherit ? 'Heredado del mandante' : 'Vista previa' }}</span>
        <div class="font-mono mt-1">{{ $preview->formatDate('2026-09-10 18:45:00', true) }} · {{ $preview->timezone }}</div>
        <div class="font-mono">{{ $preview->currency }} {{ $preview->formatNumber('1234.567') }}</div>
    </div>
    <p class="text-xs text-ink-500">La moneda se aplica a nuevos registros. Las cuentas existentes conservan su moneda e importes. Los campos de calendario del navegador mantienen su selector habitual.</p>
    @error('regional')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('regional.*') as $messages)
        @foreach($messages as $message)<p class="field-error" role="alert">{{ $message }}</p>@endforeach
    @endforeach
    @if(session('regional-saved'))<p class="text-success-700 text-sm" role="status">{{ session('regional-saved') }}</p>@endif
    <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn btn-primary btn-sm">Guardar configuración regional</button>
</section>
