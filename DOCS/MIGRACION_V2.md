# Plan de migración v1 → v2

**Estado:** propuesto (2026-04-17). Requiere aprobación antes de ejecutar.
**Fuente de verdad del destino:** `CLAUDE.md` v2.
**Alcance:** convertir el CRM de cobranza mono-tenant (v1) en plataforma BPO multi-tipo multi-tenant (v2) sin tirar el código funcional ya construido.

---

## 0. Criterios transversales del refactor

### 0.1. Estrategia de migraciones

Estamos en desarrollo. No hay datos productivos. Aplicamos **corte limpio con `migrate:fresh`**:

- Las 26 migraciones v1 se **reemplazan** por un set nuevo v2 (no convivimos). Se archivan en `DOCS/ARCHIVO/migraciones_v1/` para historial; no corren en migrate.
- Cada sub-fase produce sus migraciones; entre sub-fases mayores se corre `migrate:fresh --seed` para validar que todo arranca desde cero.
- Nombres de migración mantienen prefijo de módulo (§12.3 CLAUDE.md).

**No** hacemos migraciones ALTER incrementales complejas. El refactor es un rediseño, no una evolución.

### 0.2. Estrategia de tests

- Los 59 tests existentes se van actualizando por sub-fase. Algunos se eliminan (p.ej. tests de unicidad `clientes.identificacion` global se reemplazan por unicidad `personas(proyecto_id, identificacion)`), otros se refactorizan, otros se mantienen cambiando solo los fixtures.
- **Cada sub-fase cierra con suite verde**. Si no compilan los tests, la sub-fase no se marca completa.
- **Tests nuevos obligatorios por sub-fase**:
  - Multi-tenancy: usuario del Proyecto A **no ve** data del Proyecto B.
  - Scope automático funciona en todos los casos de uso.
  - Invariantes nuevas del dominio (personas aisladas, rol por proyecto).

### 0.3. Orden interno de cada sub-fase

1. **Dominio** nuevo (entidades, VOs, contratos).
2. **Tests unitarios** del dominio.
3. **Migraciones** nuevas (reemplazan las viejas).
4. **Application + Infraestructura** nuevas.
5. **Test integración** + ajuste de tests existentes.
6. **UI Livewire** (si aplica).
7. **Seeders** actualizados.
8. `migrate:fresh --seed`, `phpunit`, `npm run build`. Todo verde.

### 0.4. Seeders base del nuevo mundo

- 1 mandante demo: **"BPO Demo Corp"**.
- 1 proyecto demo de cobranza: **"Cobranza Demo 2026"** (tipo: `cobranza`).
- 1 cartera demo: **"Consumo"**.
- 1 admin global: `admin@crm.local / password` con rol `ADMIN_GLOBAL`.
- 1 supervisor demo: `supervisor.demo@crm.local / password` con rol `SUPERVISOR` en el proyecto demo.
- 1 gestor demo: `gestor.demo@crm.local / password` con rol `GESTOR` en el proyecto demo.
- Los 4 clientes v1 y 5 productos demo se re-siembran como **personas y casos del proyecto demo**.

### 0.5. Convenciones de naming durante el refactor

- Prefijos de módulo (CLAUDE.md §12.3): `tenancy`, `personas`, `casos`, `cobranza`, `compromisos`, `gestiones`, etc.
- Tablas especializadas de CTI: `casos_cobranza`, `casos_ticket_cx`, `compromisos_promesa_pago`, etc.
- Columnas de scope: siempre `proyecto_id` (sin excepciones).
- Índices scoped: empiezan siempre con `proyecto_id`.

### 0.6. Línea de tiempo estimada (orientativa)

| Fase | Alcance | Orden de magnitud |
|---|---|---|
| 0 | Preparación y limpieza | 1 sesión |
| 1 | Refactor del core (multi-tenant + CTI abstracto + campos personalizados + UI scoped) | 8-10 sesiones |
| 2 | Cobranza migrada al nuevo core | 4-5 sesiones |
| 3 | CX (nuevo tipo) | 4-5 sesiones |
| 4 | Venta outbound | 4-5 sesiones |
| 5 | Servicio técnico | 3-4 sesiones |

Una "sesión" ≈ un turno productivo típico de los que hemos venido trabajando. Las estimaciones son orden de magnitud, no compromiso.

---

## Fase 0 — Preparación

**Objetivo:** dejar el repo en estado limpio para empezar el refactor sin fricciones.

### 0.A. Snapshot del estado v1

1. Crear directorio `DOCS/ARCHIVO/v1_estado/`.
2. Copiar dentro:
   - Lista de migraciones v1 (solo nombres).
   - Lista de módulos construidos.
   - Snapshot del test suite (cuántos tests y sus nombres).
3. Este `MIGRACION_V2.md` ya queda como contrato de lo que sigue.

### 0.B. Archivar migraciones v1

1. Mover `database/migrations/2026_04_17_*` (las 26 v1) a `DOCS/ARCHIVO/migraciones_v1/`.
2. El directorio `database/migrations/` queda con solo las 3 base de Laravel (`users`, `cache`, `jobs`).

### 0.C. Limpiar seeders v1 no reutilizables

- `DatabaseSeeder.php` se simplifica: comenta llamadas a `CatalogosSeeder`, `UsuariosSeeder`, `DemoDataSeeder`, `AsignacionesDemoSeeder`. Se reescribirán en sub-fase 1.M.
- Los archivos de seeders se mantienen físicamente pero no se invocan; se referencian/reemplazan en sus sub-fases.

### 0.D. Limpiar código de módulos v1 que cambia conceptualmente

Eliminamos **temporalmente** lo que no aplica sin refactor, para evitar confusiones durante la Fase 1:

- `bootstrap/providers.php`: dejar solo `AppServiceProvider` + `VoltServiceProvider` + Breeze. Comentar los providers de módulos (Catalogos, Clientes, etc.) — se descomentan a medida que cada módulo se refactoriza.
- Rutas `/bandeja`, `/trabajo`, `/reportes`, `/clientes/crear`: comentar en `routes/web.php`. Se rehabilitan scoped por proyecto.
- Navigation: quitar links a rutas inactivas; dejar solo Dashboard + Profile + Logout.

### 0.E. Reset de la BD

```bash
php artisan migrate:fresh    # deja solo las 3 tablas base
php artisan db:seed         # no hace nada útil por ahora
./vendor/bin/phpunit        # debe seguir pasando los 2 tests de ejemplo de Laravel
```

**Criterio de done de Fase 0:** servidor arranca, login funciona, tests de Breeze pasan, BD limpia.

---

## Fase 1 — Refactor del core

**Objetivo:** infraestructura multi-tenant funcional + CTI abstracto + campos personalizados + UI base scoped. Sin abrir tipos de operación todavía (cobranza se migra en Fase 2).

### 1.A. Módulo Tenancy — Mandantes, Proyectos, Carteras

**Migraciones nuevas:**
- `tenancy_create_mandantes_table.php` — `id`, `public_id`, `nombre`, `codigo` (único), `tipo_documento_id`, `documento`, `activo`, timestamps, soft delete.
- `tenancy_create_proyectos_table.php` — `id`, `public_id`, `mandante_id` FK, `codigo` (único por mandante), `nombre`, `tipo_operacion` enum (`cobranza|cx|venta|servicio`), `activo`, `fecha_inicio`, `fecha_fin`, timestamps, soft delete.
- `tenancy_create_carteras_table.php` — `id`, `public_id`, `proyecto_id` FK, `codigo` (único por proyecto), `nombre`, `activo`, timestamps, soft delete.

**Domain:**
- `Mandante`, `Proyecto`, `Cartera` entidades.
- VOs: `CodigoMandante`, `CodigoProyecto`, `TipoOperacion` (enum), `CodigoCartera`.
- Contratos: `MandanteRepository`, `ProyectoRepository`, `CarteraRepository`.
- Eventos: `ProyectoCreado`, `CarteraCreada` (por si otros módulos reaccionan).

**Application:**
- UseCases: `RegistrarMandante`, `RegistrarProyecto`, `RegistrarCartera`.

**Infrastructure:**
- Modelos Eloquent y repositorios.
- `TenancyServiceProvider` registrado.
- **Middleware `ResolverProyectoActivo`**: lee `{proyecto_id}` de la URL y lo pone en el container bajo `tenancy.proyecto_activo`.
- **Trait `PerteneceAProyecto`** para modelos scoped: inyecta global scope + `proyecto_id` automático al crear.

**Seeders:**
- `MandantesDemoSeeder` crea BPO Demo Corp.
- `ProyectosDemoSeeder` crea Cobranza Demo 2026.
- `CarterasDemoSeeder` crea Cartera Consumo.

**Tests:**
- Unitarios de las 3 entidades.
- Integración: `RegistrarProyecto` respeta unicidad de código por mandante.
- Multi-tenancy: trait `PerteneceAProyecto` filtra correctamente (test con 2 proyectos y lee data cruzada).

**Criterio de done:** seeders corren, hay 1 mandante / 1 proyecto / 1 cartera en BD. Modelo de tenancy usable desde tinker.

---

### 1.B. Refactor Usuarios → multi-proyecto

**Migraciones nuevas:**
- `usuarios_create_equipos_table.php` — equipos por proyecto.
- `usuarios_alter_users_add_activo.php` — agrega `activo` a `users` (sin `equipo_id` directo; pasa a ser por proyecto).
- `usuarios_create_roles_table.php` — catálogo de roles (`ADMIN_GLOBAL`, `SUPERVISOR`, `GESTOR`, `AUDITOR`) global.
- `usuarios_create_permisos_table.php` — catálogo de permisos.
- `usuarios_create_rol_permiso_table.php` — matriz rol-permiso (pivot).
- `usuarios_create_usuario_proyecto_rol_table.php` — **tripleta**: usuario + proyecto + rol + equipo_id opcional + activo. PK compuesta.
- `usuarios_create_usuario_global_rol_table.php` — para `ADMIN_GLOBAL` que no vive en un proyecto.

**Domain / Application:**
- Refactorizar `UsuariosServiceProvider` con Gate::before que distinga `ADMIN_GLOBAL` de roles por proyecto.
- Método en `User`: `tienePermiso(string $codigo, ?int $proyectoId = null)`. Si `$proyectoId` es null, usa el proyecto activo del container. Si es `ADMIN_GLOBAL`, pasa siempre.
- Método `proyectosAsignados()` que retorna los proyectos donde tiene rol activo.

**Seeders actualizados:**
- `PermisosSeeder` mantiene los 27 permisos v1, ajustando nombres si aplica.
- `RolesSeeder` mantiene los 4 roles.
- `RolPermisoSeeder` con la matriz actualizada (ADMIN_GLOBAL recibe todo).
- Nuevo `UsuarioAdminGlobalSeeder` crea `admin@crm.local` con rol `ADMIN_GLOBAL` (no ligado a proyecto).
- Nuevo `UsuariosDemoSeeder` crea supervisor.demo y gestor.demo con rol en proyecto Cobranza Demo 2026.

**Tests:**
- Que un usuario con rol en Proyecto A no pueda ejecutar acciones en Proyecto B.
- `ADMIN_GLOBAL` pasa Gate en cualquier proyecto.
- Gate sin proyecto activo (fuera de request) retorna false o excepción clara.

**Criterio de done:** login con admin/password funciona. Admin es ADMIN_GLOBAL. Supervisor y gestor demo existen con rol en el proyecto demo.

---

### 1.C. Scope global automático + URL con proyecto_id

**Sin migraciones** (es infraestructura pura).

**Infrastructure:**
- Middleware `ResolverProyectoActivo` (ya creado en 1.A) se registra globalmente para rutas `/proyectos/{proyecto_id}/*`.
- Middleware verifica: el usuario autenticado tiene rol activo en ese proyecto (o es `ADMIN_GLOBAL`). Si no, 403.
- Global Scope `ScopePorProyectoActivo` aplicado a todos los modelos scoped vía trait `PerteneceAProyecto`.

**Rutas:**
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', fn () => redirect()->route('seleccionar-proyecto'));
    Route::get('/seleccionar-proyecto', ...)->name('seleccionar-proyecto');

    Route::prefix('proyectos/{proyecto_id}')
        ->middleware('proyecto.activo')
        ->group(function () {
            Route::view('/bandeja', 'asignaciones::bandeja-page')->name('bandeja');
            // etc.
        });

    Route::prefix('admin')
        ->middleware('can:es_admin_global')
        ->group(function () {
            Route::view('/mandantes', ...)->name('admin.mandantes');
            Route::view('/proyectos', ...)->name('admin.proyectos');
        });
});
```

**UI:**
- Componente `SelectorProyecto` en el header: dropdown con proyectos del usuario. Al cambiar, redirige a `/proyectos/{nuevo_id}/bandeja`.
- Pantalla `/seleccionar-proyecto`: cuando el usuario tiene >1 proyecto, lo fuerza a elegir uno antes de operar. Si tiene 1, redirige directo.
- Navegación con el `{proyecto_id}` en todas las rutas operativas.

**Tests:**
- Acceder a `/proyectos/99/bandeja` sin asignación → 403.
- Acceder a `/proyectos/{id}/bandeja` con asignación → 200.
- Admin global accede a cualquier proyecto.
- Global Scope se aplica automáticamente en queries de cualquier modelo con trait.

**Criterio de done:** login → pantalla selector → elige proyecto → bandeja con data scoped. Cambio de proyecto via dropdown funciona.

---

### 1.D. Refactor Clientes → Personas (scope por proyecto)

**Migración nueva:**
- `personas_create_personas_table.php`:
  - `id`, `public_id`
  - `proyecto_id` FK NOT NULL
  - `tipo_persona` enum (fisica, juridica)
  - `tipo_identificacion_id` FK (catálogo global)
  - `identificacion`
  - `nombres`, `apellidos`, `razon_social`, `fecha_nacimiento`, `hash_identidad` (opcional, para futura dedupe técnica interna)
  - timestamps + soft delete
  - **Índice único compuesto** `(proyecto_id, tipo_identificacion_id, identificacion)`

**Domain:**
- Entidad `Persona` en `app/Modules/Personas/Domain/Entities/`.
- VOs `Identificacion`, `TipoPersona` (existen en v1, se mantienen con ajustes si aplica).
- Excepciones: `DatosPersonaInvalidos`, `IdentificacionYaRegistradaEnProyecto`.
- Contrato `PersonaRepository`.

**Application:**
- DTOs `RegistrarPersonaInput/Output`.
- UseCase `RegistrarPersona` — recibe `proyectoId` en el DTO (obligatorio), valida unicidad por proyecto, no globalmente.
- UseCase `BuscarPersonas` scoped al proyecto activo.

**Infrastructure:**
- `PersonaModel` con trait `PerteneceAProyecto`.
- `EloquentPersonaRepository`.
- Módulo `Personas` con ServiceProvider.

**UI:**
- Refactorizar `CrearCliente` → `CrearPersona` scoped. La ruta ahora es `/proyectos/{id}/personas/crear`.
- Redirige tras crear a `/proyectos/{id}/trabajo/{persona.public_id}`.

**Seeders:**
- `PersonasDemoSeeder` crea Juan, María, Austral, Carlos como personas del Proyecto Cobranza Demo 2026.

**Tests:**
- Unitarios de `Persona` (mantener los 6 de `ClienteTest`, adaptar).
- Integración `RegistrarPersona`: unicidad scoped por proyecto (no global).
- Multi-tenancy: crear 2 proyectos con misma identificación, ambos OK; lectura desde un proyecto no ve la persona del otro.

**Criterio de done:** crear persona en proyecto demo desde UI; lista de personas solo muestra las del proyecto activo; buscador scoped.

---

### 1.E. Refactor Contactos

**Migración nueva:**
- `contactos_create_contactos_table.php`:
  - `id`, `persona_id` FK
  - Ya no `cliente_id`. El scope por proyecto es heredado via `persona.proyecto_id`, pero agregamos `proyecto_id` explícito para índices.
  - `tipo` enum, `valor`, `etiqueta`, `es_principal`, `activo`, timestamps.
  - Índices: `(proyecto_id, persona_id, tipo)`, `(proyecto_id, persona_id, es_principal)`.

**Domain / Application / Infra:**
- Entidad `Contacto` (igual que v1, con VO `TipoContacto` enum).
- UseCase `RegistrarContacto`.
- Modal Livewire inline "Agregar contacto" en Vista de Trabajo se mantiene, refactorizado al nuevo modelo.

**Seeders:**
- `ContactosDemoSeeder` re-siembra los 7 contactos demo asociados a las personas ya en el proyecto.

**Criterio de done:** contactos de personas del proyecto demo visibles. Crear contacto funciona desde Vista de Trabajo.

---

### 1.F. Casos (núcleo abstracto CTI)

**Migración nueva:**
- `casos_create_casos_table.php`:
  - `id`, `public_id`
  - `proyecto_id` FK NOT NULL
  - `cartera_id` FK NOT NULL
  - `persona_id` FK NOT NULL
  - `tipo_caso` enum (`cobranza|ticket_cx|lead_venta|servicio`)
  - `estado_caso_id` FK catálogo por proyecto
  - `fecha_ingreso`, `prioridad`
  - `cerrado_en`
  - Desnormalizados: `fecha_ultima_gestion`, `resultado_ultima_gestion_id`, `usuario_ultima_gestion_id`, `tiene_compromiso_vigente`
  - timestamps + soft delete
  - Índices: `(proyecto_id, cartera_id, persona_id)`, `(proyecto_id, estado_caso_id)`, `(proyecto_id, fecha_ultima_gestion)`.

**Domain:**
- Entidad abstracta `Caso` con factory `registrar(tipo, proyectoId, carteraId, personaId, ...)`.
- Enum `TipoCaso`.
- Contrato `CasoRepository`.
- Eventos genéricos: `CasoCreado`, `CasoCerrado`.

**Application:**
- UseCases núcleo: `CerrarCaso`.
- UseCases específicos viven en módulos de tipo (Fase 2+).

**Infrastructure:**
- `CasoModel` con trait `PerteneceAProyecto`.
- El modelo carga su especialización via relación polimórfica 1:1 con su tabla `casos_<tipo>` (se define en Fase 2 para cobranza).

**Criterio de done:** tabla `casos` creada; se puede insertar un caso con `tipo_caso` definido (aunque la especialización no exista todavía, el núcleo compila).

---

### 1.G. Compromisos (núcleo abstracto)

**Migración nueva:**
- `compromisos_create_compromisos_table.php`:
  - `id`, `public_id`
  - `proyecto_id` FK, `caso_id` FK, `gestion_origen_id` FK único
  - `tipo_compromiso` enum
  - `estado` enum (`pendiente|cumplido|roto|cancelado`)
  - `fecha_vencimiento`, `fecha_resolucion`
  - `usuario_id`, timestamps + soft delete
  - Índices: `(proyecto_id, fecha_vencimiento, estado)`, `(proyecto_id, caso_id)`.

**Domain:**
- Entidad abstracta `Compromiso` con factory base + transiciones comunes (marcar cumplido/roto/cancelado) — en realidad las transiciones viven en la especialización, pero el estado y los eventos son comunes.
- Enum `EstadoCompromiso`, `TipoCompromiso`.
- Evento base `EventoCompromisoResuelto` + subclases `CompromisoCumplido`, `CompromisoRoto`, `CompromisoCancelado`.

**Listener genérico en Casos:**
- `RecalcularBanderaCompromisoVigente` reacciona a eventos de resolución de compromiso, actualiza `casos.tiene_compromiso_vigente`.

**Criterio de done:** tabla `compromisos` creada; listener registrado; test unit de transiciones abstractas verde.

---

### 1.H. Gestiones — refactor al nuevo core

**Migración nueva:**
- `gestiones_create_gestiones_table.php`:
  - `id`, `public_id`
  - `proyecto_id`, `caso_id`, `persona_id` (desnormalizado)
  - `contacto_id` (nullable)
  - `canal_id` (catálogo global)
  - `tipo_gestion_id`, `resultado_id`, `subresultado_id` (catálogos por proyecto)
  - `motivo_no_contacto_id` nullable
  - `notas`, `duracion_segundos`
  - Snapshots opcionales (agnósticos; cada tipo agrega los suyos en su sub-fase)
  - `usuario_id`, timestamps + soft delete
  - Índices obligatorios §4.5

**Domain:**
- Reutilizar `Gestion` entidad v1 con ajustes:
  - Agregar `proyectoId` al constructor.
  - `productoId` renombra a `casoId`.
  - `clienteId` renombra a `personaId`.
  - Banderas del resultado siguen siendo `requiere_promesa` → **`requiere_compromiso`** (más genérico), `es_contacto_efectivo`, `requiere_causa`.
- VOs `DatosCompromiso` (reemplaza `DatosPromesa` pero más genérico, tipo-polimórfico). Para Fase 1, solo la forma abstracta; cobranza usa `DatosPromesaPago` en Fase 2.
- Evento `GestionRegistrada` con los nuevos campos.

**Application:**
- UseCase `RegistrarGestion` refactorizado (input con `proyectoId`, `casoId`, etc.).
- Transacción se mantiene igual; dispara `GestionRegistrada`.

**Infrastructure:**
- `GestionModel` con trait `PerteneceAProyecto`.
- `EloquentGestionRepository` actualizado.
- `ConsultaResultado` adapter en Catálogos lee resultado scoped por proyecto (ver 1.J).

**Listeners** que se mantienen pero actualizados:
- Productos listener (1.K) → actualiza desnormalizados en `casos`.
- Promesas listener → ahora Compromisos (Fase 2).

**Tests:**
- Actualizar `GestionTest.php` unit con el nuevo input.
- Actualizar `RegistrarGestionTest.php` feature.
- Agregar test multi-tenancy: crear gestión en proyecto A con caso de proyecto B → rechazo.

**Criterio de done:** `RegistrarGestion` acepta input scoped, emite `GestionRegistrada`. Sin promesa aún (se trabaja en Fase 2).

---

### 1.I. Campañas y Asignaciones

**Migraciones nuevas:**
- `campanas_create_campanas_table.php` — con `proyecto_id`, resto igual que v1.
- `asignaciones_create_asignaciones_table.php` — con `proyecto_id`, `caso_id` (antes producto_id).

**Domain / Infra:**
- Adaptaciones mínimas: agregar `proyecto_id` a entidades y repositorios existentes, trait `PerteneceAProyecto`.
- Listener `IniciarTrabajoDesdeGestion` (1.I v1 ya existía) ajustado: busca asignaciones por `proyecto_id + caso_id + usuario_id`.

**UI:**
- `Bandeja` refactorizado a `/proyectos/{id}/bandeja`. Filtros + buscador + link a Vista de Trabajo funcional.
- Cada query inicia con el scope por proyecto activo.

**Seeders:**
- `AsignacionesDemoSeeder` re-siembra 1 campaña y 5 asignaciones para el gestor demo en el proyecto demo.

**Criterio de done:** Bandeja del gestor demo muestra 5 asignaciones del proyecto demo. Click "Trabajar" → Vista de Trabajo funcional (aunque cobranza-específica se detalla en Fase 2).

---

### 1.J. Catálogos — global vs por-proyecto

**Migraciones nuevas:**

Globales (sin `proyecto_id`):
- `catalogos_create_tipos_identificacion_table.php`
- `catalogos_create_canales_table.php`
- `catalogos_create_paises_table.php`
- `catalogos_create_monedas_table.php`
- `catalogos_create_tipos_documento_table.php`
- `catalogos_create_estados_base_sistema_table.php`

Los 3 de usuarios ya hechos (roles/permisos/rol_permiso) son globales.

Por proyecto (con `proyecto_id` FK):
- `catalogos_create_resultados_table.php` — con columna `requiere_compromiso`, `es_contacto_efectivo`, `requiere_causa` como booleanos típicos (no en metadata JSON) para ser más explícito.
- `catalogos_create_subresultados_table.php` — FK a resultado.
- `catalogos_create_tipos_gestion_table.php`
- `catalogos_create_motivos_no_contacto_table.php`
- `catalogos_create_estados_caso_table.php`
- `catalogos_create_prioridades_table.php`
- `catalogos_create_sla_table.php`
- `catalogos_create_colas_table.php`
- `catalogos_create_scripts_table.php`

Las tablas específicas de cobranza (`causas_mora`, `tramos_mora`, `tipos_pago`) se crean en Fase 2.

**Infra:**
- Modelos Eloquent de cada catálogo.
- Trait `EsCatalogoGlobal` vs `EsCatalogoDeProyecto`.
- Adapter `ConsultaResultado` actualizado para leer scoped.

**Seeders:**
- Globales: `CatalogosGlobalesSeeder` siembra tipos_identificacion (4), canales (6), estados_base (3).
- Por proyecto: `CatalogosProyectoDemoSeeder` siembra los 12 resultados, 7 tipos de gestión, motivos de no contacto, etc. del proyecto demo (equivalente al seeder v1 pero scoped).

**Criterio de done:** catálogos globales compartidos, catálogos del proyecto demo sembrados y consultables desde la UI.

---

### 1.K. Productos → eliminar como módulo, migrar listeners a Casos

**Eliminación:**
- El módulo `Productos` v1 desaparece como módulo con dominio propio (se absorbe en Casos + módulo especializado Cobranza en Fase 2).
- Archivar `app/Modules/Productos/` en `DOCS/ARCHIVO/modulos_v1/`.

**Listeners en Casos:**
- `ActualizarDesnormalizadosDesdeGestion` se mueve al módulo `Casos` (módulo núcleo).
- Reacciona a `GestionRegistrada`, actualiza `casos.fecha_ultima_gestion`, `resultado_ultima_gestion_id`, `usuario_ultima_gestion_id`.
- `RecalcularBanderaCompromisoVigente` → ya ubicado en Casos en 1.G.

**Criterio de done:** no hay referencias a "Productos" como módulo. Los listeners que antes estaban en Productos operan ahora sobre `casos`.

---

### 1.L. Módulo CamposPersonalizados

**Migraciones nuevas:**
- `campos_personalizados_create_campos_personalizados_table.php` — según esquema §7.3 CLAUDE.md.
- `campos_personalizados_create_valores_campo_personalizado_table.php`.
- `campos_personalizados_create_opciones_catalogo_cp_table.php` — opciones de los campos de tipo selección.

**Domain:**
- Entidad `CampoPersonalizado` con tipo (enum), reglas declarativas, ámbito.
- VOs: `CodigoCampo`, `ReglasCampo`.
- Servicio de aplicación de reglas (valida un valor contra un campo).

**Application:**
- UseCases: `DefinirCampoPersonalizado`, `EditarReglas`, `DesactivarCampo`.
- Servicio `CargarValoresCampos($ambito, $ambitoId, $entidadId)` que trae valores para una entidad.
- Servicio `GuardarValoresCampos` que valida y persiste.

**Infrastructure:**
- Modelos Eloquent, repositorios.
- Componente Livewire genérico `FormularioCamposPersonalizados` que renderiza fields según tipo.

**Seeders:**
- **No** sembramos campos personalizados en Fase 1; se habilita en Fase 2 (cobranza) con 1-2 campos demo por cartera.

**Tests:**
- Unit: validar reglas declarativas (obligatorio, min, max, regex, opciones cerradas).
- Integración: definir campo, guardar valor, recuperar valor.
- Multi-tenancy: campo del Proyecto A no se aplica a entidades del Proyecto B.

**Criterio de done:** módulo listo para que Fase 2 lo use. No hay UI de admin de campos aún (solo via seeder).

---

### 1.M. UI — rehabilitación scoped

- `Bandeja` ya scoped (1.I).
- `Vista de Trabajo` abstracta: identidad de persona + selector de casos del proyecto + formulario genérico de gestión + historial. Los datos específicos del caso (saldos, categoría, lead) se renderizan desde slots delegados al módulo de especialización. En Fase 1 se ve vacío/genérico; en Fase 2 cobranza llena su slot.
- `BuscadorGlobal` scoped al proyecto activo.
- `Reportes`: dashboard operativo con KPIs **comunes** (gestionadas, intentadas, compromisos vigentes/vencidos). Los específicos por tipo (saldos en cobranza, SLA en cx) se agregan en fases posteriores.
- Menú navigation: `/proyectos/{id}/bandeja`, `/admin/mandantes`, etc.

### 1.N. Cierre de Fase 1

**Checklist:**
- [ ] Todas las migraciones v2 corren desde cero con `migrate:fresh`.
- [ ] Seeders del mundo demo crean mandante + proyecto + cartera + personas + casos (vacíos de especialización) + contactos + campañas + asignaciones + catálogos + usuarios.
- [ ] Login con admin global → pantalla selector → accede a todos los proyectos.
- [ ] Login con gestor demo → entra directo al único proyecto → Bandeja con asignaciones.
- [ ] Registrar gestión funcional sobre un caso del proyecto demo (sin compromiso específico aún).
- [ ] Scope multi-tenancy: test suite incluye 3+ tests de cross-proyecto rechazado.
- [ ] Suite PHPUnit completa en verde.
- [ ] `npm run build` en verde.
- [ ] Todos los módulos tienen su propio `ServiceProvider` y están registrados en `bootstrap/providers.php`.

---

## Fase 2 — Cobranza migrada al nuevo core

**Objetivo:** el módulo `Cobranza` queda 100% funcional como especialización del núcleo. Paridad de features con v1 (registrar gestión con promesa, resolver promesa) pero ahora scoped por proyecto.

### 2.A. Especialización Caso → CasoCobranza

**Migración:**
- `cobranza_create_casos_cobranza_table.php`:
  - `caso_id` PK + FK 1:1 a `casos`.
  - `numero_prestamo` único por proyecto.
  - `cartera_externa_id` (FK a catálogo por proyecto si aplica) — nota: "Cartera" v1 era el tipo de producto (consumo/micro); ahora queda como catálogo por proyecto si el mandante lo necesita.
  - `estado_producto_id` (FK catálogo por proyecto — renombrar a `estado_cobranza_id` si aplica).
  - `tramo_mora_id`, `monto_original`, `saldo_capital`, `saldo_total`, `cuota_mensual`, `dias_mora`, `cuotas_totales`, `cuotas_pagadas`, `moneda`, `fecha_desembolso`, `fecha_vencimiento`.

**Migraciones de catálogos de cobranza:**
- `cobranza_create_causas_mora_table.php` — por proyecto.
- `cobranza_create_tramos_mora_table.php` — por proyecto.
- `cobranza_create_tipos_pago_table.php` — por proyecto.
- `cobranza_create_estados_cobranza_table.php` — por proyecto (estados específicos de un préstamo: vigente, mora, judicial, castigado, etc.).

**Domain / Application:**
- Entidad `CasoCobranza` con factory específica (recibe saldos, mora, etc.) + delegación al `Caso` base.
- UseCase `RegistrarCasoCobranza` (crea `Caso` + `CasoCobranza` en la misma transacción).

**Seeders:**
- `CobranzaCatalogosSeeder` siembra causas de mora, tramos, tipos de pago para el proyecto demo.
- `CasosCobranzaDemoSeeder` re-siembra los 5 préstamos v1 como `casos` + `casos_cobranza` del proyecto demo.

**Criterio de done:** bandeja demo muestra 5 casos de cobranza con datos financieros.

---

### 2.B. Especialización Compromiso → CompromisoPromesaPago

**Migración:**
- `cobranza_create_compromisos_promesa_pago_table.php`:
  - `compromiso_id` PK + FK 1:1.
  - `monto`, `moneda`, `tipo_pago_id`.

**Domain:**
- Entidad `CompromisoPromesaPago` con VOs `MontoCompromiso`, `FechaCompromiso`.
- Transiciones (cumplir/romper/cancelar) heredan del `Compromiso` abstracto.

**Application:**
- UseCases especializados: `CrearPromesaDesdeGestion` (listener a `GestionRegistrada` cuando el resultado.requiere_compromiso=true y tipo_operacion del proyecto = cobranza).
- `MarcarPromesaCumplida`, `MarcarPromesaRota`, `CancelarPromesa` se mantienen como casos de uso específicos de cobranza (aunque internamente operan sobre `Compromiso` abstracto).

**Listeners:**
- Listener `CrearPromesaAlRegistrarGestion` ajustado: verifica que el proyecto activo sea de tipo `cobranza` antes de crear `CompromisoPromesaPago` (evita crear promesas en proyectos de otros tipos).

**Criterio de done:** registrar gestión con resultado `PROMESA_PAGO` en proyecto cobranza demo crea una promesa. Resolver promesa desde la UI funciona.

---

### 2.C. UI especializada de cobranza

- **Vista de Trabajo (slot cobranza)**: muestra los datos financieros del caso (saldo total, capital, cuota, días mora, tramo).
- **Formulario Nueva Gestión (slot cobranza)**: revela campos de promesa (monto + fecha) + causa de mora cuando el resultado lo requiere.
- **Resolver Promesa**: botones Cumplida / Rota / Cancelada con modal de fecha.
- **Reportes operativos** (slot cobranza): KPIs específicos (cartera total en mora, efectividad por tramo, montos prometidos vigentes).

### 2.D. Campos personalizados de demo en cobranza

Para validar el módulo de campos personalizados:
- Campo custom en la cartera Consumo: "Operador externo que origina la cuenta" (texto_corto, opcional).
- Campo custom en el tipo de gestión "Confirmación de pago": "Número de referencia bancaria" (texto_corto, obligatorio).
- Probar que en la UI aparecen los campos correspondientes y se persisten en la tabla.

### 2.E. Cierre de Fase 2

**Checklist:**
- [ ] Paridad funcional con v1: registrar gestión con promesa, resolver promesa, actualizar bandera vigente del caso.
- [ ] Los 5 casos demo visibles en bandeja del proyecto demo.
- [ ] Reportes operativos muestran KPIs de cobranza scoped al proyecto.
- [ ] Campo personalizado funciona end-to-end.
- [ ] Tests verdes.

---

## Fase 3 — CX (primer tipo nuevo)

**Objetivo:** validar que la plataforma soporta un tipo de operación distinto.

### Alcance

- Módulo `Cx` con `CasoTicketCx` (categoría, prioridad, SLA), `CompromisoResolucionTicket` (fecha SLA, nivel de escalamiento).
- Catálogos específicos por proyecto: `categorias_ticket`, `prioridades_ticket`, `niveles_sla`, `niveles_escalamiento`.
- Proyecto demo adicional: "Soporte Demo 2026" (tipo cx) del mismo mandante BPO Demo Corp.
- Vista de Trabajo slot cx: muestra categoría, SLA restante, nivel de escalamiento.
- Formulario Nueva Gestión slot cx: campos específicos (categoría sub, escalamiento).
- Reportes operativos slot cx: tickets abiertos, SLA en riesgo, efectividad por categoría.

### Criterio de done

- Usuario gestor tiene acceso a ambos proyectos (cobranza demo y cx demo).
- Cambiar de proyecto via dropdown → diferente Vista de Trabajo, diferente bandeja, diferentes reportes.
- Tests multi-tenancy: dos proyectos del mismo mandante no mezclan data.

---

## Fase 4 — Venta outbound

**Objetivo:** tercer tipo de operación.

### Alcance

- Módulo `Venta` con `CasoLeadVenta` (producto interés, valor estimado, etapa embudo), `CompromisoCierreVenta` (monto, fecha estimada).
- Catálogos por proyecto: `productos_venta`, `etapas_embudo`, `razones_rechazo`.
- Proyecto demo: "Venta Demo 2026".
- UI slots de venta.
- Reportes de venta: leads por etapa, tasa de conversión, cierres del mes.

### Criterio de done

- Igual que Fase 3, extendido a venta.

---

## Fase 5 — Servicio técnico

**Objetivo:** cuarto tipo de operación, cierra el alcance planificado.

### Alcance

- Módulo `Servicio` con `CasoServicio` (tipo de servicio, estado técnico), `CompromisoAccionServicio` (tipo de acción, fecha programada).
- Catálogos por proyecto: `tipos_accion_servicio`, `estados_tecnicos`.
- Proyecto demo: "Servicio Técnico Demo 2026".
- UI slots.
- Reportes.

### Criterio de done

- Los 4 tipos funcionan con paridad. La plataforma es verificable end-to-end para cualquier operación BPO.

---

## Anexo A — Mapeo v1 → v2

### Tablas

| v1 | v2 |
|---|---|
| `clientes` | `personas` (con `proyecto_id`) |
| `productos` | `casos` + `casos_cobranza` (CTI) |
| `contactos` | `contactos` (con `proyecto_id` explícito) |
| `gestiones` | `gestiones` (con `proyecto_id`, `caso_id` en vez de `producto_id`) |
| `promesas` | `compromisos` + `compromisos_promesa_pago` (CTI) |
| `campanas` | `campanas` (con `proyecto_id`) |
| `asignaciones` | `asignaciones` (con `proyecto_id`, `caso_id`) |
| `tipos_identificacion` | idem (global) |
| `canales` | idem (global) |
| `resultados` | idem pero **por proyecto**, banderas explícitas |
| `tipos_gestion` | idem pero **por proyecto** |
| `causas_mora` | `cobranza.causas_mora` (por proyecto) |
| `carteras` | `cobranza.carteras_externas` (por proyecto) — y `carteras` nuevas (del núcleo Tenancy) |
| `tramos_mora` | `cobranza.tramos_mora` (por proyecto) |
| `estados_producto` | `cobranza.estados_cobranza` (por proyecto) |
| `motivos_no_contacto` | idem (por proyecto) |
| `tipos_pago` | `cobranza.tipos_pago` (por proyecto) |
| `usuario_rol` | `usuario_proyecto_rol` (tripleta) + `usuario_global_rol` (para ADMIN_GLOBAL) |
| `roles` | idem (global) |
| `permisos` | idem (global) |
| `rol_permiso` | idem (global) |
| `equipos` | idem pero `proyecto_id` FK |
| `auditoria`, `importaciones`, `importacion_filas` | idem pero con `proyecto_id` donde aplique |

### Módulos v1 → módulos v2

| v1 | v2 |
|---|---|
| `Clientes` | `Personas` |
| `Productos` | absorbido por `Casos` (núcleo) + `Cobranza` (especialización) |
| `Contactos` | `Contactos` (renombra algunos atributos) |
| `Gestiones` | `Gestiones` (refactor) |
| `Promesas` | absorbido por `Compromisos` (núcleo) + `Cobranza` (especialización `CompromisoPromesaPago`) |
| `Catalogos` | dividido en `CatalogosGlobales` (núcleo) y catálogos por proyecto distribuidos en cada módulo |
| `Usuarios` | `Usuarios` (refactor profundo para multi-proyecto) |
| `Asignaciones` | idem (refactor con `proyecto_id`) |
| — | nuevo: `Tenancy` (Mandantes, Proyectos, Carteras) |
| — | nuevo: `Casos` (núcleo abstracto) |
| — | nuevo: `Compromisos` (núcleo abstracto) |
| — | nuevo: `CamposPersonalizados` |
| — | nuevo: `Cobranza` (especialización) |
| — | nuevo (fase 3): `Cx` |
| — | nuevo (fase 4): `Venta` |
| — | nuevo (fase 5): `Servicio` |

---

## Anexo B — Riesgos identificados

1. **Complejidad del scope por proyecto.** Todo queda scoped y cualquier omisión de `proyecto_id` en una query es una fuga de datos. Mitigación: tests de multi-tenancy obligatorios por módulo; Global Scope automático y difícil de desactivar.
2. **Tamaño del refactor.** Fase 1 son ~10 sesiones. Riesgo de perder el hilo. Mitigación: sub-fases pequeñas y cerradas con checklist. Pausas para validar.
3. **Paridad de cobranza.** Si Fase 2 no replica 100% v1, el cliente pierde funcionalidad. Mitigación: checklist de features v1, tests que prueban los mismos flujos.
4. **Campos personalizados mal modelados caen en Vtiger.** Mitigación: §7 CLAUDE.md con tipos cerrados y validaciones declarativas, no extensibles por UI.
5. **Admin global demasiado poderoso.** Un `ADMIN_GLOBAL` mal gestionado puede filtrar información cross-mandante. Mitigación: auditoría especial para sus acciones, UI diferenciada (acceso solo por `/admin/*`).

---

## Anexo C — Cómo arrancar el refactor

1. Revisar este documento contigo. Ajustar si algo no cuadra.
2. Ejecutar **Fase 0** (preparación) en una sola sesión.
3. Arrancar **Fase 1** sub-fase por sub-fase, con check de criterios después de cada una.
4. No pasar de Fase 1 a Fase 2 hasta que la checklist 1.N esté 100% verde.

**Este documento se actualiza** a medida que ejecutamos. Cada sub-fase completada se marca aquí con fecha y link al commit.

---

## Historial del plan

- **2026-04-17**: Versión inicial del plan. Pendiente de ejecución.
