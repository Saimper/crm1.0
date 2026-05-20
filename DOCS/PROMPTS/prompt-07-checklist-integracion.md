# Prompt 7 — Checklist de Integración: Módulo Importaciones Dinámicas

> **Objetivo:** Conectar todas las piezas del refactor de importaciones dinámicas en el proyecto real. Cero campos obligatorios. El sistema se adapta al Excel del tenant.

---

## 1. ImportacionesServiceProvider.php — Completo con todos los bindings

**Archivo:** `app/Modules/Importaciones/Infrastructure/Providers/ImportacionesServiceProvider.php`

**Estado actual:** Ya tiene los bindings de repositorios y Livewire registrados.

**Cambios necesarios:** Agregar el comando Artisan de verificación.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Providers;

use App\Modules\Importaciones\Application\Console\Commands\VerificarImportacionesCommand;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use App\Modules\Importaciones\Infrastructure\Persistence\Repositories\EloquentCampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Persistence\Repositories\EloquentImportacionRepository;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ImportacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('imports.php'), 'imports');

        $this->app->bind(ImportacionRepository::class, EloquentImportacionRepository::class);
        $this->app->bind(CampoPersonalizadoImportacionRepository::class, EloquentCampoPersonalizadoImportacionRepository::class);
    }

    public function boot(): void
    {
        View::addNamespace('importaciones', resource_path('views/modules/importaciones'));
        Livewire::component('importaciones.importar', Importar::class);
        // Deprecated F35-B: reemplazados por importaciones.importar (wizard unificado).
        Livewire::component('importaciones.importar-personas', ImportarPersonas::class);
        Livewire::component('importaciones.importar-casos', ImportarCasos::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerificarImportacionesCommand::class,
            ]);
        }
    }
}
```

**Verificación:**
- [ ] `ImportacionesServiceProvider` ya está registrado en `bootstrap/providers.php` (línea 43)
- [ ] `mergeConfigFrom` apunta a `config_path('imports.php')` — confirmar que `config/imports.php` existe
- [ ] Los binds resuelven: `php artisan tinker` → `app(ImportacionRepository::class)` y `app(CampoPersonalizadoImportacionRepository::class)`

---

## 2. Rutas Web del Módulo — Middleware auth + proyecto.activo + permiso importaciones

**Archivo:** `routes/web.php`

**Estado actual:** Ya existen rutas de importaciones (líneas 121-148), pero usan solo `can:importaciones.crear`.

**Verificación de rutas existentes:**

| Ruta | Middleware | Estado |
|------|-----------|--------|
| `GET /proyectos/{id}/importaciones` | `auth`, `proyecto.activo`, `can:importaciones.crear` | ✅ OK |
| `GET /proyectos/{id}/importaciones/plantilla` | `auth`, `proyecto.activo`, `can:importaciones.crear` | ✅ OK |
| `GET /proyectos/{id}/importaciones/*/exportar` | `auth`, `proyecto.activo`, `can:importaciones.crear` | ✅ OK |

**Acción requerida:** Ninguna. Las rutas ya están correctamente configuradas. El Livewire `importaciones.importar` se renderiza en la vista `importaciones::page` que ya existe.

**Verificación adicional:**
- [ ] La vista `resources/views/modules/importaciones/page.blade.php` existe y contiene `@livewire('importaciones.importar')`
- [ ] El middleware `proyecto.activo` está registrado y funcional (inyecta `tenancy.proyecto_activo`)
- [ ] El permiso `importaciones.crear` está en `PermisosSeeder` (línea 115)

---

## 3. Seeder de Permisos — Permisos existentes, ninguno nuevo requerido

**Archivo:** `database/seeders/Usuarios/PermisosSeeder.php`

**Permisos de importaciones ya registrados (líneas 113-117):**

```php
['codigo' => 'importaciones.ver',       'nombre' => 'Ver importaciones',        'grupo' => 'importaciones', 'activo' => true],
['codigo' => 'importaciones.crear',     'nombre' => 'Cargar importaciones',     'grupo' => 'importaciones', 'activo' => true],
['codigo' => 'importaciones.procesar',  'nombre' => 'Procesar importaciones',   'grupo' => 'importaciones', 'activo' => true],
['codigo' => 'importaciones.eliminar',  'nombre' => 'Eliminar importaciones',   'grupo' => 'importaciones', 'activo' => true],
```

**Permisos usados por el módulo:**

| Permiso | Dónde se usa | Ya existe |
|---------|-------------|-----------|
| `importaciones.crear` | `Importar::mount()`, `subirArchivo()`, `confirmarMapeo()`, rutas web | ✅ |
| `importaciones.procesar` | `Importar::ejecutar()` | ✅ |
| `campos.definir` | `PrepararImportacionDinamica` (validación condicional) | ✅ (línea 129) |

**Acción requerida:** Ninguna. No se necesitan permisos nuevos.

**Verificación:**
- [ ] Ejecutar `php artisan db:seed --class=PermisosSeeder` en staging para asegurar que los permisos estén actualizados
- [ ] Confirmar que los roles base (OPERADOR, SUPERVISOR, ADMIN) tienen `importaciones.crear` asignado según la matriz de permisos

---

## 4. Comando Artisan `php artisan importaciones:verificar`

**Archivo nuevo:** `app/Modules/Importaciones/Application/Console/Commands/VerificarImportacionesCommand.php`

**Propósito:** Valida que todas las piezas del refactor están correctamente conectadas antes de deploy.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Throwable;

final class VerificarImportacionesCommand extends Command
{
    protected $signature = 'importaciones:verificar';
    protected $description = 'Verifica que todos los bindings, migraciones, enums y componentes del módulo de importaciones estén correctamente configurados.';

    /** @var array<int, array{nombre: string, ok: bool, detalle: string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $this->info('Verificando módulo de importaciones dinámicas...');
        $this->newLine();

        $this->verificarBindings();
        $this->verificarColumnasMigracion();
        $this->verificarEnumModo();
        $this->verificarTablaAuditoria();
        $this->verificarJobSerializacion();
        $this->verificarLivewireRegistrado();
        $this->verificarConfigImports();

        $this->imprimirTabla();

        $fallos = array_filter($this->resultados, static fn ($r) => ! $r['ok']);

        if ($fallos !== []) {
            $this->error(count($fallos).' verificación(es) fallaron.');

            return self::FAILURE;
        }

        $this->info('Todas las verificaciones pasaron correctamente.');

        return self::SUCCESS;
    }

    private function verificarBindings(): void
    {
        $this->verificar(
            'Binding: ImportacionRepository',
            function (): bool {
                $resolved = app(ImportacionRepository::class);

                return $resolved !== null;
            },
        );

        $this->verificar(
            'Binding: CampoPersonalizadoImportacionRepository',
            function (): bool {
                $resolved = app(CampoPersonalizadoImportacionRepository::class);

                return $resolved !== null;
            },
        );
    }

    private function verificarColumnasMigracion(): void
    {
        $this->verificar(
            'Columna importaciones.esquema (JSON)',
            static function (): bool {
                return Schema::hasColumn('importaciones', 'esquema');
            },
        );

        $this->verificar(
            'Columna importaciones.insertadas (int)',
            static function (): bool {
                return Schema::hasColumn('importaciones', 'insertadas');
            },
        );

        $this->verificar(
            'Columna importaciones.actualizadas (int)',
            static function (): bool {
                return Schema::hasColumn('importaciones', 'actualizadas');
            },
        );
    }

    private function verificarEnumModo(): void
    {
        $this->verificar(
            'Enum modo incluye insert/update/upsert',
            function (): bool {
                try {
                    $row = DB::selectOne(
                        "SELECT COLUMN_TYPE as tipo FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'importaciones' AND COLUMN_NAME = 'modo'"
                    );

                    if ($row === null) {
                        return false;
                    }

                    $tipo = (string) $row->tipo;

                    return str_contains($tipo, 'insert')
                        && str_contains($tipo, 'update')
                        && str_contains($tipo, 'upsert');
                } catch (Throwable) {
                    return false;
                }
            },
        );
    }

    private function verificarTablaAuditoria(): void
    {
        $this->verificar(
            'Tabla importacion_campos_personalizados existe',
            static function (): bool {
                return Schema::hasTable('importacion_campos_personalizados');
            },
        );
    }

    private function verificarJobSerializacion(): void
    {
        $this->verificar(
            'EjecutarImportacionJob puede instanciarse y serializarse',
            static function (): bool {
                try {
                    $job = new EjecutarImportacionJob(1, 'upsert');
                    $serialized = serialize($job);
                    $unserialized = unserialize($serialized);

                    return $unserialized instanceof EjecutarImportacionJob
                        && $unserialized->importacionId === 1;
                } catch (Throwable) {
                    return false;
                }
            },
        );
    }

    private function verificarLivewireRegistrado(): void
    {
        $this->verificar(
            'Livewire importaciones.importar registrado',
            static function (): bool {
                $components = Livewire::getComponents();

                return isset($components['importaciones.importar'])
                    || in_array(Importar::class, $components, true);
            },
        );
    }

    private function verificarConfigImports(): void
    {
        $this->verificar(
            'Config imports existe (queue, batch_size, job_timeout)',
            static function (): bool {
                return config('imports.queue') !== null
                    && config('imports.batch_size') !== null
                    && config('imports.job_timeout') !== null;
            },
        );
    }

    private function verificar(string $nombre, callable $check): void
    {
        try {
            $ok = $check();
        } catch (Throwable $e) {
            $ok = false;
        }

        $this->resultados[] = [
            'nombre' => $nombre,
            'ok' => (bool) $ok,
            'detalle' => $ok ? 'OK' : 'FALLO',
        ];
    }

    private function imprimirTabla(): void
    {
        $headers = ['Verificación', 'Estado'];
        $rows = array_map(
            static fn ($r) => [$r['nombre'], $r['detalle']],
            $this->resultados,
        );

        $this->table($headers, $rows);
    }
}
```

**Config requerida:** Crear `config/imports.php` si no existe:

```php
<?php

declare(strict_types=1);

return [
    'queue' => env('IMPORT_QUEUE', 'imports'),
    'batch_size' => env('IMPORT_BATCH_SIZE', 1000),
    'job_timeout' => env('IMPORT_JOB_TIMEOUT', 3600),
];
```

**Verificación:**
- [ ] Crear `config/imports.php` con los valores default
- [ ] Ejecutar `php artisan importaciones:verificar` — debe imprimir tabla con todo OK
- [ ] Si algún check falla, el comando retorna `FAILURE` (exit code 1)

---

## 5. Checklist de Prueba Manual en Staging

### Preparación
- [ ] Deploy de la rama a staging
- [ ] Ejecutar `php artisan migrate` (migración `2026_05_20_030431_importaciones_agregar_esquema_dinamico`)
- [ ] Ejecutar `php artisan db:seed --class=PermisosSeeder`
- [ ] Ejecutar `php artisan importaciones:verificar` — todo debe dar OK
- [ ] Redis corriendo y queue worker activo: `php artisan queue:work --queue=imports --tries=3`

---

### Paso 1: GESTOR sin permiso `campos.definir` — bloqueo en paso 3

**Objetivo:** Verificar que un usuario sin permiso no puede crear campos personalizados.

**Preparación:**
1. Crear usuario `test_gestor` con rol `GESTOR` (sin `campos.definir`)
2. Asegurar que tiene `importaciones.crear` e `importaciones.procesar`
3. Preparar Excel con columnas: `Nombre`, `Identificación`, `Teléfono`, `CampoCustom1`, `CampoCustom2`

**Pasos:**
1. Login como `test_gestor`
2. Ir a `/proyectos/{id}/importaciones`
3. Seleccionar target `persona`, subir Excel
4. **Paso 2:** Verificar que las columnas `CampoCustom1` y `CampoCustom2` se infieren como `CREAR_CP`
5. **Paso 3 (confirmar):** Debe mostrar error: *"No tienes permiso para crear campos personalizados. Solicita acceso a un administrador."*
6. La importación NO se crea

**Resultado esperado:** ❌ Bloqueado en paso 3 con mensaje de permiso

---

### Paso 2: ADMIN_GLOBAL creando campos personalizados desde importación

**Objetivo:** Verificar que ADMIN_GLOBAL puede crear campos personalizados durante la importación.

**Preparación:**
1. Login como `ADMIN_GLOBAL`
2. Mismo Excel del Paso 1

**Pasos:**
1. Subir Excel, seleccionar target `persona`
2. **Paso 2:** Verificar tipo inferido de cada columna (booleano → fecha → entero → texto)
3. **Paso 3 (confirmar):** Debe mostrar:
   - "Campos personalizados a crear: CampoCustom1, CampoCustom2"
   - "Campos creados: 2"
4. **Paso 4 (ejecutar):** Verificar progreso con contadores separados:
   - `Insertadas: X`
   - `Actualizadas: Y`
5. Ir a `/admin/campos-personalizados` — verificar que los campos nuevos existen con tipo correcto

**Resultado esperado:** ✅ Campos creados + importación completada

---

### Paso 3: Segunda ejecución diaria — reutilización de campos huérfanos

**Objetivo:** Verificar idempotencia — campos creados en Paso 2 se reutilizan.

**Pasos:**
1. Mismo ADMIN_GLOBAL, mismo Excel (o uno con columnas idénticas)
2. Subir, mapear, confirmar
3. **Paso 3:** Debe mostrar:
   - "Campos reutilizados: 2"
   - "Campos creados: 0"
4. Ejecutar — debe completar sin errores

**Resultado esperado:** ✅ Campos reutilizados, cero duplicados en `campos_personalizados`

---

### Paso 4: Backward compatibility — formato antiguo auto-mapeado

**Objetivo:** Verificar que un Excel con columnas del formato antiguo se auto-mapea.

**Preparación:**
- Excel con columnas del formato legacy: `tipo_documento`, `numero_documento`, `nombre_completo`, `email`, `telefono`, `numero_prestamo`, `monto_total`

**Pasos:**
1. ADMIN_GLOBAL, target `caso_cobranza`, seleccionar cartera activa
2. Subir Excel
3. **Paso 2:** Verificar que `CatalogoCamposSistema` auto-mapeó:
   - `tipo_documento` → `MAPEAR_SISTEMA` (tipo_identificacion_codigo)
   - `numero_documento` → `MAPEAR_SISTEMA` (identificacion)
   - `nombre_completo` → `MAPEAR_SISTEMA` (nombre)
   - `email` → `MAPEAR_SISTEMA` (email)
   - `telefono` → `MAPEAR_SISTEMA` (telefono)
   - `numero_prestamo` → `MAPEAR_SISTEMA` (numero_prestamo)
   - `monto_total` → `MAPEAR_SISTEMA` (monto_total)
4. Confirmar y ejecutar

**Resultado esperado:** ✅ Columnas auto-mapeadas sin intervención del usuario

---

### Paso 5: INSERT con duplicados en el mismo Excel

**Objetivo:** Verificar que modo INSERT ignora duplicados sin error.

**Preparación:**
- Excel con 5 filas donde la fila 2, 3, 4 tienen la misma identificación que la fila 1

**Pasos:**
1. ADMIN_GLOBAL, target `persona`, modo `Insertar`
2. Subir, mapear, confirmar
3. **Paso 4:** Verificar contadores:
   - `Insertadas: 1` (solo la primera)
   - `Omitidas: 3` (duplicados)
   - `Duplicadas: 3`
4. Verificar en DB que solo existe 1 persona nueva

**Resultado esperado:** ✅ 1 insertada, 3 omitidas/duplicadas, cero errores

---

### Paso 6: UPDATE con columnas omitidas (sin identificador)

**Objetivo:** Verificar que modo UPDATE sin identificador es bloqueante.

**Pasos:**
1. ADMIN_GLOBAL, target `persona`, modo `Actualizar`
2. Subir Excel **sin** marcar ninguna columna como identificador
3. **Paso 2:** El sistema debe mostrar advertencia: *"El modo Actualizar requiere una columna identificador."*
4. No debe permitir avanzar al paso 3

**Variante — UPDATE con identificador pero campos inexistentes:**
1. Mismo setup, pero marcar `Identificación` como identificador
2. Incluir columna `CampoInexistente` que no existe como CP
3. **Paso 3:** Debe mostrar advertencia: *"Columna CampoInexistente: no existe como campo personalizado y se omitirá."*
4. Ejecutar — debe actualizar solo las columnas que existen

**Resultado esperado:** ✅ UPDATE sin identificador = bloqueado; UPDATE con campos inexistentes = advertencia + omisión

---

### Paso 7: Verificación multi-tenancy — fuga entre proyectos

**Objetivo:** Verificar que los campos personalizados de un proyecto NO son visibles en otro.

**Preparación:**
- Proyecto A (id=1) y Proyecto B (id=2), ambos con carteras activas
- ADMIN_GLOBAL

**Pasos:**
1. En Proyecto A: importar Excel con columna `CampoExclusivoA`
2. Verificar que se crea en `campos_personalizados` con `proyecto_id = 1`
3. Cambiar a Proyecto B
4. Importar Excel con columna `CampoExclusivoB`
5. Verificar que se crea con `proyecto_id = 2`
6. Ejecutar query directa:
   ```sql
   SELECT id, proyecto_id, ambito_id, codigo, activo
   FROM campos_personalizados
   WHERE codigo IN ('CampoExclusivoA', 'CampoExclusivoB')
   ORDER BY proyecto_id;
   ```
7. Verificar que:
   - `CampoExclusivoA` → `proyecto_id = 1`
   - `CampoExclusivoB` → `proyecto_id = 2`
   - Ningún campo aparece en el proyecto equivocado

**Resultado esperado:** ✅ Cero fuga de datos entre proyectos

---

## Resumen de archivos a crear/modificar

| Archivo | Acción | Prioridad |
|---------|--------|-----------|
| `ImportacionesServiceProvider.php` | Agregar registro del comando | 🔴 Alta |
| `VerificarImportacionesCommand.php` | **Crear nuevo** | 🔴 Alta |
| `config/imports.php` | **Crear nuevo** (si no existe) | 🔴 Alta |
| `routes/web.php` | Sin cambios (ya configurado) | 🟢 OK |
| `PermisosSeeder.php` | Sin cambios (ya tiene permisos) | 🟢 OK |
| Migración `2026_05_20_030431` | Ya existe, ejecutar en staging | 🟡 Verificar |

---

## Comandos de verificación rápida

```bash
# 1. Verificar que todo resuelve
php artisan importaciones:verificar

# 2. Ejecutar tests del módulo
php artisan test tests/Feature/Modules/Importaciones/

# 3. Verificar colas
php artisan queue:work --queue=imports --once

# 4. Verificar config
php artisan config:show imports

# 5. PHPStan nivel 6+ (nivel 8 en Domain/)
./vendor/bin/phpstan analyse app/Modules/Importaciones --level 6

# 6. Laravel Pint
./vendor/bin/pint app/Modules/Importaciones --test
```

---

## Notas críticas para el deploy

1. **Orden de ejecución:** `migrate` → `db:seed` → `importaciones:verificar` → `queue:work`
2. **Queue worker:** Debe reiniciarse después del deploy (`php artisan queue:restart`)
3. **Campos huérfanos:** Aceptado como idempotente. `existeCampo()` los reutiliza en segunda ejecución.
4. **GET_LOCK:** El job usa advisory lock de MySQL. Si otro worker tiene la importación, sale silencioso (no es error).
5. **Backoff del job:** 60s → 180s → 600s. Timeout 3600s. Cola `imports`.
6. **Contadores separados:** `insertadas` y `actualizadas` son columnas independientes en `importaciones`. No sumar para obtener total.
