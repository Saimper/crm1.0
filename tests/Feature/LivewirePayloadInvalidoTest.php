<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Los escáneres sondean `/livewire/update` con snapshots inventados, componentes
 * de otros frameworks (Filament, Jetstream), métodos de Livewire v2 y valores del
 * tipo incorrecto. Nada de eso es un fallo de la app: debe responder 4xx sin
 * escribir un stack trace en el log.
 */
final class LivewirePayloadInvalidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Log::spy();
    }

    public function test_snapshot_con_checksum_corrupto_devuelve_400_sin_log(): void
    {
        $snapshot = $this->snapshotLogin();
        $snapshot['checksum'] = str_repeat('0', 64);

        $this->update($snapshot)->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_componente_inexistente_devuelve_404_sin_log(): void
    {
        $snapshot = $this->snapshotInventado('filament.pages.dashboard');

        $this->update($snapshot)->assertStatus(404);

        Log::shouldNotHaveReceived('error');
    }

    public function test_metodo_de_livewire_v2_devuelve_400_sin_log(): void
    {
        $llamada = [['path' => '', 'method' => 'emit', 'params' => ['refresh']]];

        $this->update($this->snapshotLogin(), [], $llamada)->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_propiedad_inexistente_devuelve_400_sin_log(): void
    {
        $this->update($this->snapshotLogin(), ['email' => 'bot@example.com'])->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_array_en_propiedad_string_devuelve_400_sin_log(): void
    {
        $this->update($this->snapshotLogin(), ['form.email' => ['x' => 'y']])->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_string_donde_va_un_form_object_devuelve_400_sin_log(): void
    {
        $this->update($this->snapshotLogin(), ['form' => 'texto'])->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_llamar_hook_de_ciclo_de_vida_devuelve_400_sin_log(): void
    {
        $llamada = [['path' => '', 'method' => 'mount', 'params' => []]];

        $this->update($this->snapshotLogin(), [], $llamada)->assertStatus(400);

        Log::shouldNotHaveReceived('error');
    }

    public function test_payload_valido_sigue_funcionando(): void
    {
        $this->update($this->snapshotLogin(), ['form.email' => 'usuario@example.com'])->assertOk();
    }

    /** @return array<string, mixed> */
    private function snapshotLogin(): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = Volt::test('pages.auth.login')->snapshot;

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function snapshotInventado(string $nombre): array
    {
        $snapshot = [
            'data' => [],
            'memo' => [
                'id' => Str::random(20),
                'name' => $nombre,
                'path' => 'admin',
                'method' => 'GET',
                'children' => [],
                'scripts' => [],
                'assets' => [],
                'errors' => [],
                'locale' => 'es',
            ],
        ];
        $snapshot['checksum'] = Checksum::generate($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $updates
     * @param  list<array<string, mixed>>  $calls
     */
    private function update(array $snapshot, array $updates = [], array $calls = []): TestResponse
    {
        return $this->withHeader('X-Livewire', 'true')->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => $updates,
                'calls' => $calls,
            ]],
        ]);
    }
}
