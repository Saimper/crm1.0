# F36 — Configurador unificado de proyecto

Cierre de fase. Documento de arquitectura, mapa de archivos y propuesta de
actualización para `CLAUDE.md §15`.

---

## 1. Resumen ejecutivo

**Problema del cliente.** Configurar un proyecto nuevo exigía navegar entre
seis pantallas inconexas: Carteras (`/proyectos/{id}/carteras`), Catálogos
comunes (`/proyectos/{id}/catalogos` con cinco sub-tabs), catálogos
tipo-específicos en la misma URL con `match($tipoOperacion)` interno, y
Campos personalizados solo accesibles desde el admin global cross-project.
El onboarding de un mandante nuevo requería entender la jerarquía interna
del sistema antes de poder operar el primer caso. Auditoría P0
(`DOCS/AUDITORIA_F36_CONFIGURACION.md`) cuantificó la fragmentación.

**Solución.** Wizard unificado de 9 pasos con dos modos: `wizard`
(onboarding secuencial con bloqueo por dependencia y auto-avance) y
`edicion` (tabs libres para mantenimiento posterior). Cada paso es un
sub-Livewire que recibe el proyecto por prop, valida en su propio Domain
y dispatcha un evento al padre para refrescar el avance. El smart-link del
sidebar lleva al wizard si la configuración no está completa, al modo
edición si ya lo está.

**Impacto en UX.** Una única puerta de entrada
(`/admin/proyectos/{ulid}/configurar`), checklist permanente del progreso,
estado COMPLETADA explícito tras finalizar. SUPERVISOR/GESTOR/AUDITOR no
ven la entrada (es ADMIN_GLOBAL only). El sidebar de proyecto se redujo a
las pantallas operativas reales (Bandeja, Personas, Casos, Compromisos,
Reportes, Trazabilidad, Permisos).

---

## 2. Arquitectura — flujo

```mermaid
flowchart LR
    URL["/admin/proyectos/{ulid}/configurar"] --> Route[routes/web.php]
    Route -->|view + modo='wizard'| Page[configurador-proyecto-page.blade.php]
    Page -->|<livewire:tenancy.configurador-proyecto>| Padre[ConfiguradorProyecto]

    Padre -->|mount + Calculador| Avance[AvanceConfiguracion VO]
    Avance -->|9 verificadores| DB[(DB::table)]

    Padre -->|@switch pasoActivo| Hijo[Sub-Livewire del paso]
    Hijo -->|UPDATE/INSERT| DB
    Hijo -->|dispatch configuracion-paso-completado| Padre
    Padre -->|alCompletarPaso refresca + auto-avance| Padre

    Padre -->|render| StepperVista[Stepper / Tabs según modo]
```

Reglas:

- El padre nunca toca tablas operativas; solo orquesta navegación, autoriza
  y consulta `CalculadorAvanceConfiguracion`.
- Cada hijo es self-contained: prop `:proyecto`, valida en su rules, hace
  INSERT/UPDATE/DELETE via `DB::table()`, dispatcha el evento.
- Multi-tenancy: cada query scoped por `proyecto_id`. Códigos pueden
  repetirse entre proyectos distintos (regresión confirmada por
  `ConfiguradorMultiTenancyTest`).

---

## 3. Mapa de archivos NUEVOS

### Domain (P1)

```
app/Modules/Tenancy/Domain/ConfiguracionProyecto/
├── PasoConfiguracion.php                 enum 9 cases + helpers + subpasos por tipo
├── EstadoConfiguracionProyecto.php       enum BORRADOR / EN_PROGRESO / COMPLETADA
├── AvanceConfiguracion.php               VO final readonly — porcentaje, pasoActual, etc.
├── CalculadorAvanceConfiguracion.php     servicio Domain — agrega 9 verificadores
├── Contracts/
│   └── VerificadorPasoConfiguracion.php  interfaz (1 implementación por paso)
└── Exceptions/
    ├── PasoNoAplicableAlTipoProyecto.php
    └── SaltoDePasoNoPermitido.php
```

### Verificadores (P2)

```
app/Modules/Tenancy/Infrastructure/Configuracion/Verificadores/
├── DatosProyectoVerificador.php
├── CarterasVerificador.php
├── EstadosCasoVerificador.php
├── TiposGestionVerificador.php
├── ResultadosVerificador.php
├── MotivosNoContactoVerificador.php
├── CatalogosTipoVerificador.php
├── CamposPersonalizadosVerificador.php
└── ResumenVerificador.php
```

### Livewire del wizard

```
app/Modules/Tenancy/Infrastructure/Http/Livewire/
├── ConfiguradorProyecto.php              padre (P2 + P7 dual mode)
└── ConfiguradorPasos/
    ├── PasoDatosProyecto.php             P3
    ├── PasoCarteras.php                  P3
    ├── PasoEstadosCaso.php               P4
    ├── PasoTiposGestion.php              P4
    ├── PasoResultados.php                P4
    ├── PasoMotivosNoContacto.php         P4
    ├── PasoCatalogosTipo.php             P5 (contenedor con sub-tabs)
    ├── PasoCamposPersonalizados.php      P6
    ├── PasoResumen.php                   P6 (recibe :modo en P7)
    └── CatalogosTipo/
        ├── CatalogoTipoBase.php          P5 (base abstracta)
        ├── CatalogoTramosMora.php
        ├── CatalogoTiposPago.php
        ├── CatalogoCategoriasTicket.php
        ├── CatalogoPrioridadesTicket.php
        ├── CatalogoNivelesSla.php
        ├── CatalogoNivelesEscalamiento.php
        ├── CatalogoProductosVenta.php
        ├── CatalogoEtapasEmbudo.php
        ├── CatalogoTiposAccionServicio.php
        └── CatalogoEstadosTecnicos.php
```

### Vistas

```
resources/views/livewire/tenancy/
├── configurador-proyecto.blade.php       padre (render condicional wizard/edicion)
└── configurador-pasos/
    ├── paso-datos-proyecto.blade.php
    ├── paso-carteras.blade.php
    ├── paso-estados-caso.blade.php
    ├── paso-tipos-gestion.blade.php
    ├── paso-resultados.blade.php
    ├── paso-motivos-no-contacto.blade.php
    ├── paso-catalogos-tipo.blade.php
    ├── paso-campos-personalizados.blade.php
    ├── paso-resumen.blade.php
    └── catalogos-tipo/
        └── catalogo-{tramos-mora|tipos-pago|categorias-ticket|...}.blade.php  (10 vistas)
```

```
resources/views/modules/tenancy/admin/configurador-proyecto-page.blade.php
```

### Tests

```
tests/Unit/Tenancy/ConfiguracionProyecto/
├── PasoConfiguracionTest.php             P1 — 12 tests
├── AvanceConfiguracionTest.php           P1 — 20 tests
└── CalculadorAvanceConfiguracionTest.php P1 — 5 tests

tests/Feature/Tenancy/
├── ConfiguradorProyectoTest.php          P2 + P7 — 7 tests
├── ConfiguradorProyectoEdicionTest.php   P7 — 10 tests
├── ConfiguradorMultiTenancyTest.php      P9 — 4 tests
├── ConfiguradorJourneyCompletoTest.php   P9 — 4 tests (1 por tipo)
└── ConfiguradorPasos/
    ├── PasoDatosProyectoTest.php         P3 — 4 tests
    ├── PasoCarterasTest.php              P3 — 6 tests
    ├── PasoEstadosCasoTest.php           P4 — 4 tests
    ├── PasoTiposGestionTest.php          P4 — 3 tests
    ├── PasoResultadosTest.php            P4 — 4 tests
    ├── PasoMotivosNoContactoTest.php     P4 — 3 tests
    ├── PasoCatalogosTipoTest.php         P5 — 3 tests
    ├── PasoCamposPersonalizadosTest.php  P6 — 5 tests
    ├── PasoResumenTest.php               P6 — 8 tests
    └── CatalogosTipo/
        └── *Test.php                     P5 — 10 archivos × 4 tests = 40 tests

tests/Feature/Sidebar/
└── SidebarConfiguracionTest.php          P8 — 10 tests

tests/Support/InsertaCti.php              P5 — helper compartido para inserts CTI
```

### Otros

```
DOCS/AUDITORIA_F36_CONFIGURACION.md       P0
DOCS/F36_CONFIGURADOR.md                  P9 (este documento)
```

---

## 4. Mapa de archivos ELIMINADOS (P9)

Razón: redundantes con el wizard, sin referencias vivas detectadas tras grep
exhaustivo (todas las referencias provenían de tests skipped o del propio
sidebar/dashboard que se limpiaron en P8 + este P9).

### Livewires

```
app/Modules/Tenancy/Infrastructure/Http/Livewire/AdminCarterasProyecto.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AbstractAdminCatalogo.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminCausasGestion.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminEstadosCaso.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminMotivosNoContacto.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminResultadosProyecto.php
app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminTiposGestion.php
app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTramosMora.php
app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTiposPago.php
app/Modules/Cx/Infrastructure/Http/Livewire/AdminCategoriasTicket.php
app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesEscalamiento.php
app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesSla.php
app/Modules/Cx/Infrastructure/Http/Livewire/AdminPrioridadesTicket.php
app/Modules/Venta/Infrastructure/Http/Livewire/AdminEtapasEmbudo.php
app/Modules/Venta/Infrastructure/Http/Livewire/AdminProductosVenta.php
app/Modules/Servicio/Infrastructure/Http/Livewire/AdminEstadosTecnicos.php
app/Modules/Servicio/Infrastructure/Http/Livewire/AdminTiposAccionServicio.php
```

### Vistas

```
resources/views/modules/tenancy/admin/carteras-proyecto-page.blade.php
resources/views/modules/tenancy/admin/carteras-proyecto.blade.php
resources/views/modules/catalogos/page.blade.php
resources/views/modules/catalogos/livewire/_catalogo-simple.blade.php
resources/views/modules/catalogos/livewire/admin-causas-gestion.blade.php
resources/views/modules/catalogos/livewire/admin-estados-caso.blade.php
resources/views/modules/catalogos/livewire/admin-motivos-no-contacto.blade.php
resources/views/modules/catalogos/livewire/admin-resultados.blade.php
resources/views/modules/catalogos/livewire/admin-tipos-gestion.blade.php
resources/views/modules/cobranza/livewire/admin-{tramos-mora,tipos-pago}.blade.php
resources/views/modules/cx/livewire/admin-{categorias,prioridades,niveles-sla,niveles-escalamiento}-ticket.blade.php
resources/views/modules/venta/livewire/admin-{productos-venta,etapas-embudo}.blade.php
resources/views/modules/servicio/livewire/admin-{tipos-accion-servicio,estados-tecnicos}.blade.php
```

### Tests skipped

```
tests/Feature/Modules/Tenancy/AdminCarterasProyectoTest.php           58 tests skipped en total
tests/Feature/Modules/Catalogos/AdminCatalogosProyectoTest.php
tests/Feature/Modules/Catalogos/AdminCatalogosTipoEspecificoTest.php
```

Eran archivos `markTestSkipped('TODO F35: migrar a factories...')` cuyos
únicos consumidores eran los Livewires eliminados.

### Modificaciones (no eliminaciones) en archivos existentes

- `routes/web.php` — quitadas rutas `proyectos.catalogos`, `proyectos.carteras`.
- `app/Modules/{Tenancy,Catalogos,Cobranza,Cx,Venta,Servicio}/Infrastructure/Providers/*ServiceProvider.php`
  — quitados imports y registros Livewire de los componentes eliminados.
- `resources/views/modules/tenancy/proyecto-dashboard.blade.php` — quitado
  el tile zombie a `proyectos.catalogos`.
- `tests/Feature/Modules/UI/PantallasAdminRefactorTest.php` — quitado
  `test_catalogos_refactorizado` (test method orfanado).

---

## 5. Mapa de archivos NO ELIMINADOS y por qué

| Archivo | Razón |
|---|---|
| `app/Modules/CamposPersonalizados/Infrastructure/Http/Livewire/AdminCamposPersonalizados.php` | Admin global cross-project (`/admin/campos-personalizados`). Permite a ADMIN_GLOBAL gestionar campos del SISTEMA, no scoped a un proyecto. El wizard reutiliza el enum `TipoCampo` del Domain pero NO depende de este Livewire. |
| `app/Modules/EntidadesConfigurables/Infrastructure/Http/Livewire/AdminEntidadesConfigurables.php` | Idem, admin global. Define entidades configurables por proyecto/cartera; no es absorbida por el wizard (CLAUDE.md §7.7 las excluye explícitamente). |
| `app/Modules/Tenancy/Application/UseCases/RegistrarCartera.php`<br>`.../DTOs/RegistrarCartera{Input,Output}.php`<br>`.../Domain/Contracts/CarteraRepository.php`<br>`.../Persistence/Repositories/EloquentCarteraRepository.php` | Quedan huérfanos tras eliminar `AdminCarterasProyecto`. Per spec ("Si hay dudas, dejarlos"), se conservan: el dominio sigue válido y permite restaurar un flujo basado en UseCase si en el futuro hace falta (ej. importación masiva de carteras). El wizard usa `DB::table()` directo, igual patrón F34B. |

---

## 6. Permisos

| Permiso | Rol que lo gana | Rutas que protege |
|---|---|---|
| `proyectos.configurar` | ADMIN_GLOBAL via `Gate::before` | `admin.proyectos.configurar`, `admin.proyectos.configurar.editar` |
| `campos.definir` | ADMIN_GLOBAL via `Gate::before` (vetado para roles custom F33) | `admin.campos-personalizados` |
| `entidades.definir` | ADMIN_GLOBAL via `Gate::before` (vetado para roles custom F33) | `admin.entidades-configurables` |

`proyectos.configurar` es el único permiso nuevo de F36. Se sembra en
`PermisosSeeder` con grupo `proyectos`. NO se asigna a roles base (mismo
patrón que `roles.gestionar` F33).

---

## 7. Decisiones arquitectónicas

1. **CTI para casos y compromisos** — preservado (CLAUDE.md §3.2). El
   wizard no toca CTI.
2. **`AvanceConfiguracion` como VO `final readonly`** — Livewire no puede
   hidratarlo directamente, por eso `$avance` se expone como `#[Computed]`
   en el padre.
3. **Detección de modo del configurador** — flag explícita pasada desde
   el closure de la ruta al view, con fallback de route name. Razón:
   testeable sin manipular la request global.
4. **Cache de avance en sidebar** — bloque `@php` único en el layout
   resuelve el calculador una sola vez por request HTTP. No requiere
   singleton ni cache externo.
5. **`PasoCamposPersonalizados` reescrito en lugar de reusar
   `AdminCamposPersonalizados`** — el Livewire global no acepta prop
   `:proyecto` y selecciona internamente el primer proyecto del sistema.
   El wizard exige scoping estricto.
6. **`proyectos.configuracion_completada_en` no existe en schema** — la
   "completitud" se detecta en runtime via `CalculadorAvanceConfiguracion`.
   `finalizar()` solo actualiza `actualizada_en` y dispara redirect. NO se
   creó migración nueva.
7. **`PasoResumen.finalizar()` con guard de modo** — defensa F23: si
   alguien dispara la acción en modo edición vía petición manipulada, el
   método retorna sin efecto.
8. **Eventos del wizard**:
   - `configuracion-paso-completado` → padre refresca avance + (en wizard)
     auto-avanza al siguiente.
   - `configuracion-ir-a-paso` → padre cambia paso activo (con validación
     `puedeSaltarA` solo en wizard).
9. **Subresultados omitidos** — la tabla `subresultados` no existe
   (auditoría P0 riesgo #1). El wizard no la implementa para evitar
   inventar schema.
10. **Resultados no liga FK a `tipos_gestion`** — schema confirma que no
    hay columna; el acoplamiento es solo operacional (CLAUDE.md §7.2).

---

## 8. Propuesta de actualización para `CLAUDE.md §15`

> **Nota.** Aplicar SOLO con acuerdo explícito por §13.16. Borrador a
> continuación.

```markdown
| Configurador unificado de proyecto F36 — wizard de 9 pasos
  (DATOS_PROYECTO, CARTERAS, ESTADOS_CASO, TIPOS_GESTION, RESULTADOS,
  MOTIVOS_NO_CONTACTO, CATALOGOS_TIPO, CAMPOS_PERSONALIZADOS, RESUMEN)
  con Domain `ConfiguracionProyecto` (enum `PasoConfiguracion`, VO
  `AvanceConfiguracion`, contrato `VerificadorPasoConfiguracion`,
  servicio `CalculadorAvanceConfiguracion`). Permiso nuevo
  `proyectos.configurar` exclusivo ADMIN_GLOBAL (mismo patrón
  `roles.gestionar` F33). Dual mode `wizard|edicion`: wizard secuencial
  con auto-avance, edición tabs libres con badge "Configurado". Smart-link
  del sidebar resuelve el destino según `AvanceConfiguracion::estaCompleto`
  (1 cálculo por render del layout, container singleton implícito). 17
  Livewires huérfanos eliminados (AdminCarterasProyecto + 16 admins de
  catálogos por proyecto/tipo) con sus vistas y aliases. Conservados los
  Livewires admin global cross-project (`AdminCamposPersonalizados`,
  `AdminEntidadesConfigurables`). Multi-tenancy estricto verificado:
  códigos pueden repetirse entre proyectos sin choque, eliminar en X no
  afecta Y, verificadores devuelven resultado independiente por proyecto.
  Cero migraciones, cero columnas nuevas, cero seeders.
  ✅ F36 |
```

Insertar como nueva fila en la tabla §15 al final del bloque
"Funcionalidades operativas completas", antes (o después) de la fila F35.

---

## 9. Checklist final P0–P9

- [x] **P0** — Auditoría inicial: `DOCS/AUDITORIA_F36_CONFIGURACION.md`.
- [x] **P1** — Domain `ConfiguracionProyecto` (enum + VO + servicio + contrato + excepciones). 37 unit tests verdes.
- [x] **P2** — Wizard shell + 9 verificadores + permiso `proyectos.configurar` + ruta + sidebar entry. 6 feature tests.
- [x] **P3** — `PasoDatosProyecto` + `PasoCarteras`. 10 feature tests.
- [x] **P4** — 4 sub-Livewires de catálogos comunes. 14 feature tests.
- [x] **P5** — Paso 7 contenedor + 10 sub-Livewires tipo-específicos. 43 feature tests.
- [x] **P6** — `PasoCamposPersonalizados` + `PasoResumen` + acción finalizar. 13 feature tests.
- [x] **P7** — Modo edición dual + smart-link sidebar. 10 feature tests + 1 regresión.
- [x] **P8** — Limpieza visual del sidebar (2 entradas removidas). 10 tests de regresión.
- [x] **P9** — Limpieza efectiva (17 Livewires + vistas + 3 tests skipped + 2 rutas). 8 tests E2E (4 multi-tenancy + 4 journey por tipo). Documento de cierre.
- [x] Suite global verde, sin regresiones.
- [x] Pint limpio.
- [x] Cero migraciones, cero columnas inventadas, cero seeders nuevos en P9.

**Conteo final de tests F36:**

- Unit (P1): 37
- Feature wizard padre + edición: 17
- Feature sub-Livewires (P3–P6): 90
- Feature sidebar (P8): 10
- Feature E2E (P9): 8
- **Total: ~162 tests dedicados a F36**

---

> Generado en P9 (cierre F36). Sin modificaciones a `CLAUDE.md` — la
> propuesta de §8 vive aquí como borrador hasta acuerdo explícito (§13.16).
