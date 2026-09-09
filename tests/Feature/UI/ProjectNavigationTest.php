<?php

declare(strict_types=1);

namespace Tests\Feature\UI;

use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ProjectNavigationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /** @return iterable<string, array{string, string}> */
    public static function operationsAndRoles(): iterable
    {
        foreach (['cobranza', 'cx', 'venta', 'servicio'] as $operation) {
            foreach (['GESTOR', 'SUPERVISOR', 'AUDITOR'] as $role) {
                yield $operation.' '.$role => [$operation, $role];
            }
        }
    }

    #[DataProvider('operationsAndRoles')]
    public function test_every_project_navigation_link_can_be_opened(string $operation, string $role): void
    {
        $this->seed(DatabaseSeeder::class);
        $project = $this->crearProyecto($operation);
        $user = $this->crearUsuarioConRol($project, $role);
        $response = $this->actingAs($user)->get(route('proyectos.dashboard', $project->id))->assertOk();

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $links = (new DOMXPath($document))->query('//nav[@id="primary-navigation"]//a/@href');
        $this->assertNotFalse($links);
        $destinations = [];
        foreach ($links as $link) {
            $path = parse_url($link->nodeValue, PHP_URL_PATH);
            if (str_starts_with($path, '/proyectos/'.$project->id)) {
                $destinations[] = $path;
            }
        }
        $this->assertNotEmpty($destinations);
        foreach (array_unique($destinations) as $destination) {
            $this->get($destination)->assertOk();
        }
    }
}
