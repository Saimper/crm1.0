# Auditoría F36 — Configuración de un proyecto (read-only)

Inventario exhaustivo de lo que hoy compone "configurar un proyecto" en el CRM. Insumo
para el wizard único de configuración (F36). Este documento es **read-only**: no
propone fixes, no toca código, no modifica `CLAUDE.md`. Fuentes citadas como links
relativos al repo.

Alcance: catálogos por proyecto, carteras, estados de caso, tipos/resultados/causas
de gestión, motivos no contacto, campos personalizados, entidades configurables,
catálogos tipo-específicos (cobranza/cx/venta/servicio).

Fecha de corte: 2026-05-08.

---

## 1. Rutas existentes

Tabla con las rutas que el wizard de configuración consume o sustituye. Filtradas
desde [routes/web.php](routes/web.php) — todas las rutas operativas viven en ese
archivo (los `ServiceProvider` por módulo no registran rutas de configuración; solo
`IntegracionServiceProvider` registra rutas SSO, no tocadas aquí).

### 1.1 Rutas de proyecto activo (`/proyectos/{proyecto_id}/...`, middleware `auth`+`verified`+`proyecto.activo`)

| Método | URL | Middleware adicional | Permiso `can:` | Servida por |
|--------|-----|----------------------|----------------|-------------|
| GET | `/proyectos/{id}/catalogos` | `proyecto.activo` | `catalogos.gestionar` | view `catalogos::page` → 5 Livewire comunes + N tipo-específicas ([page.blade.php](resources/views/modules/catalogos/page.blade.php:1)) |
| GET | `/proyectos/{id}/carteras` | `proyecto.activo` | `catalogos.gestionar` | view `tenancy::admin.carteras-proyecto-page` → `<livewire:tenancy.admin-carteras-proyecto>` ([web.php:153](routes/web.php:153)) |
| GET | `/proyectos/{id}/entidades/{entidad_id}` | `proyecto.activo` | `entidades.ver` | view `entidades::operativo.page` (ejecución de registros, no definición) ([web.php:186](routes/web.php:186)) |

> Nota: la **definición** de campos personalizados y entidades configurables NO
> tiene ruta bajo `/proyectos/{id}/...`. Vive solo en `/admin/...` (ver §1.2).
> El wizard F36 propondría duplicar esas dos pantallas también en contexto de
> proyecto si ADMIN_GLOBAL es quien las usa allí.

### 1.2 Rutas admin global (`/admin/...`, middleware `auth`+`verified`+`admin.global`)

| Método | URL | Middleware adicional | Permiso `can:` | Servida por |
|--------|-----|----------------------|----------------|-------------|
| GET | `/admin/campos-personalizados` | `admin.global` | (implícito por middleware; sin `can:` extra) | view `campos_personalizados::admin.page` → `<livewire:campos-personalizados.admin-campos-personalizados>` ([web.php:200](routes/web.php:200)) |
| GET | `/admin/entidades-configurables` | `admin.global` | (implícito) | view `entidades::admin.page` → `<livewire:entidades-configurables.admin-entidades-configurables>` ([web.php:208](routes/web.php:208)) |
| GET | `/admin/proyectos` | `admin.global` | (implícito) | view `tenancy::admin.proyectos-page` → `<livewire:tenancy.admin-proyectos>` ([web.php:204](routes/web.php:204)) |
| GET | `/admin/mandantes` | `admin.global` | (implícito) | view `tenancy::admin.mandantes-page` → `<livewire:tenancy.admin-mandantes>` ([web.php:202](routes/web.php:202)) |

> El middleware `admin.global` corta el acceso para no-`ADMIN_GLOBAL` antes de
> entrar al Livewire. Las clases reaplican `autorizar()` en cada acción
> (defensa en profundidad F23).

### 1.3 Rutas relacionadas pero fuera del scope del wizard

Listadas aquí solo para evitar ambigüedad — el wizard F36 NO las gestiona.

| URL | Razón de exclusión |
|-----|--------------------|
| `/proyectos/{id}/usuarios` | Asignación de usuarios al proyecto (operativo, no configuración estructural). |
| `/proyectos/{id}/equipos` | Composición de equipos (operativo). |
| `/proyectos/{id}/admin/roles-custom` | Permisos custom (F33), flujo independiente. |
| `/proyectos/{id}/admin/matriz-permisos` | Lectura de matriz de permisos (F33). |
| `/admin/integracion/secrets` | SSO secrets (F28). |

---

## 2. Livewires de configuración

Cada componente sigue el patrón `final class … extends Component` y se monta vía
`<livewire:…>` desde la vista correspondiente. Permisos `can:` se evalúan en la
ruta (no dentro del Livewire); la mayoría no llama a `$this->authorize(...)` —
excepción explícita: `AdminCamposPersonalizados` y `AdminEntidadesConfigurables`,
que llaman a `autorizar()` en `mount()` y antes de cada acción mutadora (defensa
en profundidad F23).

### 2.1 Núcleo (catálogos comunes y carteras)

| Clase | Archivo | Ruta web | Entidad CRUD | Permiso ruta | Ámbito |
|-------|---------|----------|--------------|--------------|--------|
| `AbstractAdminCatalogo` | [AbstractAdminCatalogo.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AbstractAdminCatalogo.php:16) | (abstract) | — base — | — | proyecto |
| `AdminResultadosProyecto` | [AdminResultadosProyecto.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminResultadosProyecto.php:14) | `/proyectos/{id}/catalogos` (tab) | `resultados` | `catalogos.gestionar` | proyecto |
| `AdminTiposGestion` | [AdminTiposGestion.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminTiposGestion.php:10) | `/proyectos/{id}/catalogos` (tab) | `tipos_gestion` | `catalogos.gestionar` | proyecto |
| `AdminCausasGestion` | [AdminCausasGestion.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminCausasGestion.php:14) | `/proyectos/{id}/catalogos` (tab) | `causas_gestion` | `catalogos.gestionar` | proyecto |
| `AdminMotivosNoContacto` | [AdminMotivosNoContacto.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminMotivosNoContacto.php:10) | `/proyectos/{id}/catalogos` (tab) | `motivos_no_contacto` | `catalogos.gestionar` | proyecto |
| `AdminEstadosCaso` | [AdminEstadosCaso.php](app/Modules/Catalogos/Infrastructure/Http/Livewire/AdminEstadosCaso.php:14) | `/proyectos/{id}/catalogos` (tab) | `estados_caso` | `catalogos.gestionar` | proyecto |
| `AdminCarterasProyecto` | [AdminCarterasProyecto.php](app/Modules/Tenancy/Infrastructure/Http/Livewire/AdminCarterasProyecto.php:28) | `/proyectos/{id}/carteras` | `carteras` (UseCase `RegistrarCartera`) | `catalogos.gestionar` | proyecto |

### 2.2 Campos personalizados y entidades configurables

| Clase | Archivo | Ruta web | Entidad CRUD | Permiso `autorizar()` | Ámbito |
|-------|---------|----------|--------------|------------------------|--------|
| `AdminCamposPersonalizados` | [AdminCamposPersonalizados.php](app/Modules/CamposPersonalizados/Infrastructure/Http/Livewire/AdminCamposPersonalizados.php:19) | `/admin/campos-personalizados` | `campos_personalizados` (+ `opciones_campo_personalizado`) | `campos.definir` (ADMIN_GLOBAL exclusivo) | admin global |
| `FormularioCamposPersonalizados` | [FormularioCamposPersonalizados.php](app/Modules/CamposPersonalizados/Infrastructure/Http/Livewire/FormularioCamposPersonalizados.php:1) | (componente embebido en formularios operativos) | `valores_campo_personalizado` | `campos.editar` | operativo |
| `AdminEntidadesConfigurables` | [AdminEntidadesConfigurables.php](app/Modules/EntidadesConfigurables/Infrastructure/Http/Livewire/AdminEntidadesConfigurables.php:22) | `/admin/entidades-configurables` | `entidades_configurables` (+ `campos_personalizados` ámbito `entidad_configurable`) | `entidades.definir` (ADMIN_GLOBAL exclusivo) | admin global |
| `GestorRegistrosEntidad` | [GestorRegistrosEntidad.php](app/Modules/EntidadesConfigurables/Infrastructure/Http/Livewire/GestorRegistrosEntidad.php:1) | `/proyectos/{id}/entidades/{entidad_id}` | `entidades_registros` (+ valores) | `entidades.ver/crear/editar/eliminar` | proyecto |
| `PanelEntidadesVinculadas` | [PanelEntidadesVinculadas.php](app/Modules/EntidadesConfigurables/Infrastructure/Http/Livewire/PanelEntidadesVinculadas.php:1) | embebido en panel de caso/persona | lectura de `entidades_registros` | `entidades.ver` | proyecto |

### 2.3 Catálogos tipo-específicos (uno por tipo de operación)

Todos heredan de `AbstractAdminCatalogo`, comparten ruta `/proyectos/{id}/catalogos`
(la pestaña se renderiza condicionalmente en [page.blade.php:14](resources/views/modules/catalogos/page.blade.php:14) según
`$proyecto->tipo_operacion`), comparten permiso `catalogos.gestionar`. Ámbito:
proyecto.

| Clase | Archivo | Tabla | Tipo |
|-------|---------|-------|------|
| `AdminTramosMora` | [AdminTramosMora.php](app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTramosMora.php:1) | `tramos_mora` | cobranza |
| `AdminTiposPago` | [AdminTiposPago.php](app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTiposPago.php:1) | `tipos_pago` | cobranza |
| `AdminCategoriasTicket` | [AdminCategoriasTicket.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminCategoriasTicket.php:1) | `categorias_ticket` | cx |
| `AdminPrioridadesTicket` | [AdminPrioridadesTicket.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminPrioridadesTicket.php:1) | `prioridades_ticket` | cx |
| `AdminNivelesSla` | [AdminNivelesSla.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesSla.php:1) | `niveles_sla` | cx |
| `AdminNivelesEscalamiento` | [AdminNivelesEscalamiento.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesEscalamiento.php:1) | `niveles_escalamiento` | cx |
| `AdminProductosVenta` | [AdminProductosVenta.php](app/Modules/Venta/Infrastructure/Http/Livewire/AdminProductosVenta.php:1) | `productos_venta` | venta |
| `AdminEtapasEmbudo` | [AdminEtapasEmbudo.php](app/Modules/Venta/Infrastructure/Http/Livewire/AdminEtapasEmbudo.php:1) | `etapas_embudo` | venta |
| `AdminTiposAccionServicio` | [AdminTiposAccionServicio.php](app/Modules/Servicio/Infrastructure/Http/Livewire/AdminTiposAccionServicio.php:1) | `tipos_accion_servicio` | servicio |
| `AdminEstadosTecnicos` | [AdminEstadosTecnicos.php](app/Modules/Servicio/Infrastructure/Http/Livewire/AdminEstadosTecnicos.php:1) | `estados_tecnicos` | servicio |

---

## 3. Permisos involucrados

Códigos que el wizard F36 consolida bajo ADMIN_GLOBAL. Fuente:
[PermisosSeeder.php](database/seeders/Usuarios/PermisosSeeder.php) y la matriz
[RolPermisoSeeder.php](database/seeders/Usuarios/RolPermisoSeeder.php).

`ADMIN_GLOBAL` siempre pasa vía `Gate::before` (no aparece explícitamente abajo;
internamente sí se le insertan todas las filas en `rol_permiso` para consistencia
de tabla — ver [RolPermisoSeeder.php:108](database/seeders/Usuarios/RolPermisoSeeder.php:108)).

### 3.1 Permisos directamente afectados por el wizard

| Código | Nombre | SUPERVISOR | GESTOR | AUDITOR | ADMIN_GLOBAL |
|--------|--------|:----------:|:------:|:-------:|:------------:|
| `catalogos.ver` | Ver catálogos del proyecto | ✓ | — | ✓ | ✓ |
| `catalogos.crear` | Crear catálogos | ✓ | — | — | ✓ |
| `catalogos.editar` | Editar catálogos | ✓ | — | — | ✓ |
| `catalogos.eliminar` | Eliminar catálogos | ✓ | — | — | ✓ |
| `catalogos.gestionar` | Gestionar catálogos del proyecto | ✓ | — | — | ✓ |
| `catalogos.administrar` | Administrar catálogos | ✓ | — | — | ✓ |
| `campos.ver` | Ver campos personalizados | ✓ | ✓ | ✓ | ✓ |
| `campos.editar` | Editar valores de campos personalizados | ✓ | ✓ | — | ✓ |
| `campos.definir` | Definir campos personalizados (admin) | — | — | — | ✓ (exclusivo) |
| `entidades.ver` | Ver registros de entidades configurables | ✓ | ✓ | ✓ | ✓ |
| `entidades.crear` | Crear registros de entidades | ✓ | ✓ | — | ✓ |
| `entidades.editar` | Editar registros de entidades | ✓ | ✓ | — | ✓ |
| `entidades.eliminar` | Eliminar registros de entidades | ✓ | — | — | ✓ |
| `entidades.definir` | Definir entidades configurables (admin) | — | — | — | ✓ (exclusivo) |

### 3.2 Permisos colaterales (ADMIN_GLOBAL los tiene; otros roles los conservan)

| Código | Notas |
|--------|-------|
| `roles.gestionar` | Constructor de roles custom F33. Listado en `RolCustom::PERMISOS_VETADOS`. ADMIN_GLOBAL exclusivo. |

> Permisos `*.definir` (`campos.definir`, `entidades.definir`) y `roles.gestionar`
> están en la lista cerrada `RolCustom::PERMISOS_VETADOS` — no pueden delegarse
> a un rol custom. El wizard F36 hereda esa restricción.

---

## 4. Tablas tocadas

Tablas que el wizard pobla. Fuente: migraciones en
[database/migrations/](database/migrations/). Convenciones: `eliminada_en` solo
si la migración la declara; todas usan `creada_en` / `actualizada_en` y motor
InnoDB con charset `utf8mb4_unicode_ci` por defecto.

| Tabla | FK `proyecto_id` | Único compuesto | Columna `codigo` | ¿CTI? | Módulo dueño | Migración |
|-------|:----------------:|-----------------|:----------------:|:-----:|--------------|-----------|
| `carteras` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Tenancy | [2026_04_18_100002](database/migrations/2026_04_18_100002_tenancy_create_carteras_table.php:31) |
| `estados_caso` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Catalogos | [2026_04_18_140000](database/migrations/2026_04_18_140000_catalogos_create_estados_caso_table.php:37) |
| `tipos_gestion` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Catalogos | [2026_04_18_160001](database/migrations/2026_04_18_160001_catalogos_create_tipos_gestion_table.php:29) |
| `resultados` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Catalogos | [2026_04_18_160002](database/migrations/2026_04_18_160002_catalogos_create_resultados_table.php:41) |
| `motivos_no_contacto` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Catalogos | [2026_04_18_160003](database/migrations/2026_04_18_160003_catalogos_create_motivos_no_contacto_table.php:29) |
| `causas_gestion` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Catalogos | [2026_04_18_160004](database/migrations/2026_04_18_160004_catalogos_create_causas_gestion_table.php:37) |
| `campos_personalizados` | ✓ (FK `restrict`) | `(proyecto_id, ambito, ambito_id, codigo)` | ✓ | — (polimórfico vía `ambito`+`ambito_id`) | CamposPersonalizados | [2026_04_18_190000](database/migrations/2026_04_18_190000_campos_create_campos_personalizados_table.php:55) |
| `opciones_campo_personalizado` | — (vía `campo_id`) | — | — | — | CamposPersonalizados | [2026_04_18_190001](database/migrations/2026_04_18_190001_campos_create_opciones_campo_personalizado_table.php:1) |
| `valores_campo_personalizado` | — (vía `campo_id`) | (campo_id, entidad_id) único | — | — | CamposPersonalizados | [2026_04_18_190002](database/migrations/2026_04_18_190002_campos_create_valores_campo_personalizado_table.php:1) |
| `tramos_mora` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Cobranza | [2026_04_19_100000](database/migrations/2026_04_19_100000_cobranza_create_tramos_mora_table.php:31) |
| `tipos_pago` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Cobranza | [2026_04_19_100001](database/migrations/2026_04_19_100001_cobranza_create_tipos_pago_table.php:1) |
| `categorias_ticket` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Cx | [2026_04_20_100000](database/migrations/2026_04_20_100000_cx_create_categorias_ticket_table.php:1) |
| `prioridades_ticket` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Cx | [2026_04_20_100001](database/migrations/2026_04_20_100001_cx_create_prioridades_ticket_table.php:1) |
| `niveles_sla` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Cx | [2026_04_20_100002](database/migrations/2026_04_20_100002_cx_create_niveles_sla_table.php:1) |
| `niveles_escalamiento` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` + `(proyecto_id, nivel)` | ✓ | — | Cx | [2026_04_20_100003](database/migrations/2026_04_20_100003_cx_create_niveles_escalamiento_table.php:1) |
| `productos_venta` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Venta | [2026_04_21_100000](database/migrations/2026_04_21_100000_venta_create_productos_venta_table.php:1) |
| `etapas_embudo` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` + `(proyecto_id, nivel)` | ✓ | — | Venta | [2026_04_21_100001](database/migrations/2026_04_21_100001_venta_create_etapas_embudo_table.php:1) |
| `tipos_accion_servicio` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Servicio | [2026_04_22_100000](database/migrations/2026_04_22_100000_servicio_create_tipos_accion_servicio_table.php:1) |
| `estados_tecnicos` | ✓ (FK `restrict`) | `(proyecto_id, codigo)` | ✓ | — | Servicio | [2026_04_22_100001](database/migrations/2026_04_22_100001_servicio_create_estados_tecnicos_table.php:1) |
| `entidades_configurables` | ✓ (FK `restrict`) + `cartera_id` (FK `nullOnDelete`) | `(proyecto_id, codigo)` | ✓ | — (los "tipos" son filas, no tablas) | EntidadesConfigurables | [2026_04_24_160000](database/migrations/2026_04_24_160000_entidades_create_entidades_configurables_table.php:53) |
| `entidades_registros` | (vía `entidad_id`) | — | — | — | EntidadesConfigurables | [2026_04_24_160001](database/migrations/2026_04_24_160001_entidades_create_entidades_registros_table.php:1) |

### 4.1 Tablas globales (NO tocadas por el wizard)

Listadas para evitar confusión: el wizard de **proyecto** no las modifica. Se
seedean una sola vez por instancia ([CatalogosGlobalesSeeder.php](database/seeders/CatalogosGlobalesSeeder.php)).

`tipos_identificacion`, `canales`, `paises`, `monedas`, `tipos_documento`,
`estados_base_sistema`.

---

## 5. Catálogos tipo-específicos

Por cada tipo de operación, lista de tablas + Livewires exclusivos. Esta sección
alimenta el **Paso 7** del wizard (selección condicional según
`proyectos.tipo_operacion`). El multiplexor está implementado hoy en
[catalogos/page.blade.php:14](resources/views/modules/catalogos/page.blade.php:14)
mediante `match($tipoOperacion)`.

### 5.1 Cobranza

| Catálogo | Tabla | Livewire | Notas |
|----------|-------|----------|-------|
| Tramos de mora | `tramos_mora` | [AdminTramosMora.php](app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTramosMora.php:1) | Columnas extra: `dias_desde`, `dias_hasta`. |
| Tipos de pago | `tipos_pago` | [AdminTiposPago.php](app/Modules/Cobranza/Infrastructure/Http/Livewire/AdminTiposPago.php:1) | Forma estándar. |

### 5.2 CX

| Catálogo | Tabla | Livewire | Notas |
|----------|-------|----------|-------|
| Categorías ticket | `categorias_ticket` | [AdminCategoriasTicket.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminCategoriasTicket.php:1) | Forma estándar. |
| Prioridades ticket | `prioridades_ticket` | [AdminPrioridadesTicket.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminPrioridadesTicket.php:1) | Forma estándar. |
| Niveles SLA | `niveles_sla` | [AdminNivelesSla.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesSla.php:1) | Columnas extra: `horas_respuesta`, `horas_resolucion`. |
| Niveles escalamiento | `niveles_escalamiento` | [AdminNivelesEscalamiento.php](app/Modules/Cx/Infrastructure/Http/Livewire/AdminNivelesEscalamiento.php:1) | Único adicional `(proyecto_id, nivel)`. |

### 5.3 Venta

| Catálogo | Tabla | Livewire | Notas |
|----------|-------|----------|-------|
| Productos | `productos_venta` | [AdminProductosVenta.php](app/Modules/Venta/Infrastructure/Http/Livewire/AdminProductosVenta.php:1) | Forma estándar. |
| Etapas embudo | `etapas_embudo` | [AdminEtapasEmbudo.php](app/Modules/Venta/Infrastructure/Http/Livewire/AdminEtapasEmbudo.php:1) | Único adicional `(proyecto_id, nivel)`. Orden lineal del embudo. |

### 5.4 Servicio

| Catálogo | Tabla | Livewire | Notas |
|----------|-------|----------|-------|
| Tipos de acción | `tipos_accion_servicio` | [AdminTiposAccionServicio.php](app/Modules/Servicio/Infrastructure/Http/Livewire/AdminTiposAccionServicio.php:1) | Forma estándar. |
| Estados técnicos | `estados_tecnicos` | [AdminEstadosTecnicos.php](app/Modules/Servicio/Infrastructure/Http/Livewire/AdminEstadosTecnicos.php:1) | Forma estándar. |

---

## 6. Sidebar actual

Snapshot de las entradas del sidebar que tocan configuración. Fuente única:
[layouts/app.blade.php](resources/views/layouts/app.blade.php) (no hay archivo
`sidebar.blade.php` aparte; el sidebar está inline en el layout).

### 6.1 Grupo "Datos" (proyecto activo, líneas 179-220)

| Entrada visible | Ruta destino | `@can` |
|-----------------|--------------|--------|
| Catálogos | `route('proyectos.catalogos', …)` | `catalogos.gestionar` |
| Carteras | `route('proyectos.carteras', …)` | `catalogos.gestionar` (mismo bloque `@can`) |
| Importaciones | `route('proyectos.importaciones', …)` | `importaciones.crear` |
| {nombre entidad configurable} (loop dinámico) | `route('proyectos.entidades.registros', …)` | `entidades.ver` |

### 6.2 Grupo "Administración" (admin global, líneas 268-312)

| Entrada visible | Ruta destino | Visibilidad |
|-----------------|--------------|-------------|
| Panel Admin | `route('admin.dashboard')` | `$esAdmin` |
| Proyectos | `route('admin.proyectos')` | `$esAdmin` |
| Mandantes | `route('admin.mandantes')` | `$esAdmin` |
| Usuarios | `route('admin.usuarios')` | `$esAdmin` |
| Campos Personalizados | `route('admin.campos-personalizados')` | `$esAdmin` |
| Entidades Configurables | `route('admin.entidades-configurables')` | `$esAdmin` |
| Auditoría global | `route('admin.auditoria')` | `$esAdmin` |
| SSO secrets | `route('admin.integracion.secrets')` | `$esAdmin` |

> No hay actualmente una entrada única "Configurar proyecto" — los flujos
> Catálogos / Carteras (proyecto) y Campos personalizados / Entidades
> configurables (admin global) están desconectados visualmente.

---

## 7. Dependencias entre pasos

Orden secuencial obligatorio derivado de FKs y reglas de dominio. Lo que un paso
posterior puede referenciar tiene que existir cuando ese paso corre.

### 7.1 Diagrama mermaid

```mermaid
flowchart TD
    P0[Proyecto creado<br/>tipo_operacion fijado]:::raiz

    P1[Carteras]:::core
    P2[Estados de caso]:::core
    P3[Tipos de gestión]:::core
    P4[Resultados<br/>banderas: efectivo, requiere_compromiso, requiere_causa]:::core
    P5[Causas de gestión]:::core
    P6[Motivos no contacto]:::core

    P7[Catálogos tipo-específicos<br/>cobranza | cx | venta | servicio]:::tipo

    P8[Campos personalizados ámbito caso<br/>FK lógica → cartera]:::campos
    P9[Campos personalizados ámbito gestión<br/>FK lógica → tipo de gestión]:::campos
    P10[Campos personalizados ámbito compromiso<br/>FK lógica → enum tipo_compromiso]:::campos

    P11[Entidades configurables<br/>FK opcional → cartera, relación → caso o persona]:::entidades
    P12[Campos personalizados ámbito entidad_configurable<br/>FK lógica → entidad]:::campos

    P0 --> P1
    P0 --> P2
    P0 --> P3
    P0 --> P6
    P0 --> P7
    P3 --> P4
    P4 -.requiere_causa.-> P5
    P1 --> P8
    P3 --> P9
    P0 --> P10
    P1 -.opcional.-> P11
    P0 --> P11
    P11 --> P12

    classDef raiz fill:#1f2937,color:#fff,stroke:#111,stroke-width:1px;
    classDef core fill:#dbeafe,stroke:#1d4ed8,color:#0f172a;
    classDef tipo fill:#fef3c7,stroke:#b45309,color:#0f172a;
    classDef campos fill:#dcfce7,stroke:#15803d,color:#0f172a;
    classDef entidades fill:#fae8ff,stroke:#a21caf,color:#0f172a;
```

### 7.2 Justificación de cada arista

| Origen → destino | Razón |
|------------------|-------|
| Proyecto → Carteras | `carteras.proyecto_id` FK obligatoria. |
| Proyecto → Estados de caso / Tipos de gestión / Motivos no contacto | FK directa a `proyectos.id`. |
| Tipos de gestión → Resultados | El registro de gestión usa `tipo_gestion_id` y `resultado_id` simultáneamente; no hay FK física entre las dos tablas, pero la UI de gestión lista resultados en función del tipo seleccionado (acoplamiento operacional). |
| Resultados → Causas de gestión | `resultados.requiere_causa = true` exige que existan causas en el catálogo cuando la regla aplica (§5 invariante de dominio). |
| Carteras → Campos personalizados ámbito `caso` | `campos_personalizados.ambito_id` referencia `carteras.id` cuando `ambito = 'caso'` (FK lógica, no física — ver §7 CLAUDE.md). |
| Tipos de gestión → Campos personalizados ámbito `gestion` | `ambito_id` referencia `tipos_gestion.id` cuando `ambito = 'gestion'`. |
| Proyecto → Campos personalizados ámbito `compromiso` | `ambito_id` referencia el enum `tipo_compromiso` (string-encoded). No depende de carteras/tipos. |
| Carteras → Entidades configurables | `entidades_configurables.cartera_id` opcional (`nullOnDelete`). |
| Entidades configurables → Campos personalizados ámbito `entidad_configurable` | Mismo patrón que ámbitos `caso` / `gestion` — `ambito_id` referencia `entidades_configurables.id`. |
| Proyecto → Catálogos tipo-específicos | Solo aplican si `proyectos.tipo_operacion` matchea (multiplexor en la UI). |

### 7.3 Orden lineal sugerido para el wizard (1 ruta válida)

1. Crear proyecto (con `tipo_operacion` fijado, inmutable después).
2. Carteras.
3. Estados de caso.
4. Tipos de gestión.
5. Resultados (con banderas).
6. Causas de gestión.
7. Motivos no contacto.
8. Catálogos tipo-específicos (paso condicional según `tipo_operacion`).
9. Campos personalizados (sub-pasos por ámbito: caso × cartera, gestión × tipo, compromiso × tipo).
10. Entidades configurables y sus campos.

Pasos 2-7 se pueden paralelizar visualmente si la UI lo permite, pero pasos 5
(resultados) y 6 (causas) tienen dependencia funcional débil; el wizard puede
exigir 5 antes de 6, o relajar y avisar al usuario.

---

## 8. Riesgos detectados

Lista corta de fricciones, duplicaciones y mismatches encontrados durante la
auditoría. **Sin proponer fixes** — sólo registro.

1. **Subresultados y scripts no existen como tablas.** `CLAUDE.md` §8 los lista
   como catálogos por proyecto (`subresultados`, `scripts`), pero no hay
   migración ni Livewire que los cubra. El campo `subresultado_id` aparece en
   §5 de `CLAUDE.md` como atributo de `Gestion`, pero el repositorio no lo
   implementa. El wizard no debería incluirlos hasta confirmar si entran en F36.
2. **`/admin/campos-personalizados` y `/admin/entidades-configurables` no usan
   `can:` en la ruta.** Se apoyan únicamente en `admin.global` middleware + la
   reautorización del Livewire. Si se moviera la ruta a contexto de proyecto,
   habría que duplicar la lógica de `autorizar()` en cada acción mutadora (igual
   que hace F23).
3. **El multiplexor de catálogos tipo-específicos vive en una vista Blade.**
   [page.blade.php:14](resources/views/modules/catalogos/page.blade.php:14) usa
   `match($tipoOperacion)` directamente. No hay servicio de dominio que enumere
   los catálogos por tipo — duplicar la lista en el wizard significaría
   duplicar la fuente de verdad.
4. **Permiso `catalogos.gestionar` cubre Catálogos y Carteras simultáneamente.**
   El sidebar (líneas 181-192) las agrupa bajo el mismo `@can`. SUPERVISOR
   gestiona ambas en el mismo permiso; si el wizard quiere separarlas, no hay
   permiso granular hoy.
5. **Permisos `*.crear/editar/eliminar` de catálogos son seedeados pero no
   utilizados.** En las rutas y Livewires solo aparece `catalogos.gestionar` (y
   `catalogos.administrar` no aparece en grep alguno). Los granulares quedan
   muertos en `permisos`.
6. **Campos personalizados ámbito `compromiso` siguen "pendiente" según
   docstring** ([AdminCamposPersonalizados.php:17](app/Modules/CamposPersonalizados/Infrastructure/Http/Livewire/AdminCamposPersonalizados.php:17)).
   El comentario dice que solo cubre `caso` (× cartera) y `gestion` (× tipo de
   gestión). Si el wizard expone los 3 ámbitos hoy, `compromiso` puede quedar
   incompleto.
7. **Sin entrada "Configurar proyecto" en sidebar.** Los pasos están
   fragmentados entre grupo "Datos" (proyecto) y grupo "Administración"
   (admin global). Onboarding de mandante nuevo requiere navegar entre dos
   secciones desconectadas.
8. **`AbstractAdminCatalogo` no llama a `authorize`.** Confía solo en `can:` de
   ruta. Si el wizard expone catálogos vía Livewire montado dinámicamente sin
   pasar por la ruta original, se pierde la verificación de permiso (a
   diferencia de `AdminCamposPersonalizados` y `AdminEntidadesConfigurables`,
   que reaplican `autorizar()`).
9. **`entidades_configurables.relacion_con` es enum cerrado `ninguna|caso|persona`.**
   `CLAUDE.md` §7.7 lo confirma como restricción intencional, pero implica que el
   wizard debe forzar al usuario a elegir entre esas tres opciones — no admite
   relaciones futuras sin migración.
10. **Sidebar lista entidades configurables del proyecto activo con query
    inline en Blade** ([app.blade.php:202-210](resources/views/layouts/app.blade.php:202)).
    `LIMIT 15` y orden por `nombre`. Si el wizard genera más de 15, las
    extras no aparecen en el sidebar — friction silenciosa.

---

> Generado durante F36 (read-only). Sin fixes propuestos. Sin archivos
> modificados fuera de este documento.
