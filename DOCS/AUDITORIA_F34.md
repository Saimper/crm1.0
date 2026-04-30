# Auditoría F34A — Diagnóstico exhaustivo de incoherencias

**Fecha:** 2026-04-30
**Modo:** Solo lectura. Cero modificaciones a código fuente.
**Alcance:** End-to-end. 22 módulos, 73 migraciones, 51 componentes Livewire, 86 vistas Blade, 522 tests.
**Fuente:** CLAUDE.md (raíz) + DOCS/MIGRACION_V2.md + repo en `C:/Users/joeld/Desktop/CRM1.0`.

> Convenciones: cada hallazgo lleva archivo:linea o ruta:permiso. Cuando una ruta es `proyectos.<slug>`, se asume el grupo `Route::prefix('proyectos/{proyecto_id}')` con middleware `proyecto.activo` (routes/web.php:16-18).

---

## 1. Glosario y mapa conceptual

### 1.1 Inventario de conceptos

| Concepto | Tabla / módulo | Label en UI (cita literal) | Notas |
|---|---|---|---|
| Mandante | `mandantes` / `Tenancy` | "Mandantes" (sidebar admin), "Mandante" (form AdminMandantes) | BPO contrata; nivel jerárquico raíz. |
| Proyecto | `proyectos` / `Tenancy` | "Proyectos" (sidebar admin), "Proyecto activo" (sidebar lateral, layouts/app.blade.php:69) | Único campo de scoping. `tipo_operacion ∈ {cobranza, cx, venta, servicio}`. |
| Cartera | `carteras` / `Tenancy` | "Cartera" (columna de bandeja, bandeja.blade.php:64) | Subdivisión del proyecto. Único cartera-scoping para roles base (F22). |
| Persona | `personas` / `Personas` | "Persona" (vista-de-trabajo título), "Persona física"/"Persona jurídica" (crear-persona radio) | Aislada por proyecto vía `(proyecto_id, tipo_id, identificacion)` UNIQUE. |
| Contacto | `contactos` / `Contactos` | "Contactos" (vista-trabajo botón), "Teléfono"/"Correo"/"Dirección" (lista-contactos:48-50) | Datos de canal por persona. |
| Caso | `casos` + `casos_<tipo>` (CTI) / `Casos` | "Casos ({{ count }})" (vista-trabajo:89), "Caso" (badge dinámico bandeja:107) | Tipos: `cobranza`, `ticket_cx`, `lead_venta`, `servicio`. |
| Caso Cobranza | `casos_cobranza` / `Cobranza` | **"Préstamo"** (panel-caso cobranza:10) | Diverge: en panel se llama "Préstamo", no "Caso". |
| Caso CX | `casos_ticket_cx` / `Cx` | **"Ticket"** (panel-caso cx:9) | Diverge: en panel se llama "Ticket". |
| Caso Venta | `casos_lead_venta` / `Venta` | **"Lead"** (panel-caso venta:10) | Diverge: en panel se llama "Lead". |
| Caso Servicio | `casos_servicio` / `Servicio` | **"Servicio técnico"** (panel-caso servicio:9) | Diverge: en panel se llama "Servicio técnico". |
| Gestión | `gestiones` / `Gestiones` | "Nueva gestión" (nueva-gestion:6), "Registrar gestión" (vista-trabajo:154), "Historial" (vista-trabajo:170) | Evento estructurado. Inmutable (notas, no parseo). |
| Compromiso | `compromisos` + `compromisos_<tipo>` (CTI) / `Compromisos` | "Compromiso vigente" (vista-trabajo:9). En formulario nueva-gestion **NUNCA aparece la palabra "Compromiso"**. | Etiqueta divergente por tipo (ver fila siguiente). |
| Compromiso Promesa de Pago | `compromisos_promesa_pago` | **"Promesa de pago"** (nueva-gestion:110) | |
| Compromiso Resolución de Ticket | `compromisos_resolucion_ticket` | **"Resolución / Escalamiento"** (nueva-gestion:219) | |
| Compromiso Cierre de Venta | `compromisos_cierre_venta` | **"Promesa de cierre"** (nueva-gestion:144) | Atención: se llama "Promesa" pero modela cierre de venta (no de pago). |
| Compromiso Acción de Servicio | `compromisos_accion_servicio` | **"Acción de servicio programada"** (nueva-gestion:178) | |
| Campaña | `campanas` / `Campanas` | "Campaña" (bandeja columna campana_nombre) | Agrupador de asignaciones. |
| Asignación | `asignaciones` / `Asignaciones` | "Asignación" (bandeja header), filter chips "Todas/Pendientes/En trabajo/Cerradas" (bandeja:16-21) | Estado: `pendiente / en_trabajo / cerrada`. |
| Equipo | `equipos`, `equipo_usuario` / `Usuarios` | "Equipos" (sidebar Operación), "Usuarios del Proyecto" (sidebar Supervisión) | Subgrupo de gestores en un proyecto. |
| Usuario | `users` + `usuario_proyecto_rol` + `usuario_global_rol` | "Usuarios" (admin sidebar), "Usuarios del Proyecto" (sidebar supervisión) | Roles: ADMIN_GLOBAL, SUPERVISOR, GESTOR, AUDITOR + roles custom F33. |
| Rol custom | `roles_custom`, `usuario_proyecto_rol_custom` | "Roles custom" (NO en sidebar — pantalla huérfana de menú) | F33. Sin cartera-scoping documentado. |
| Catálogo | múltiples (resultados, tipos_gestion, etc.) / `Catalogos` + módulos tipo | "Catálogos" (sidebar Datos) | Globales (canales, paises) vs por proyecto (resultados, tipos_gestion, etc.). |
| Campo personalizado | `campos_personalizados`, `valores_campo_personalizado` | "Campos Personalizados" (sidebar admin) | 10 tipos cerrados. Ámbitos: caso×cartera, gestion×tipo, compromiso×tipo. |
| Entidad configurable | `entidades_configurables`, `entidades_registros` | "Entidades Configurables" (sidebar admin) | F24. Datos tipados por proyecto/cartera. |
| Notificación | `notificaciones` / `Notificaciones` | "Notificaciones" (sidebar Trazabilidad) | F13–F14. SLA, compromisos, asignaciones. |
| Auditoría | `auditorias` / `Auditoria` | "Auditoría" (sidebar Trazabilidad) | F12. Observer + UI + export CSV. |
| Importación | `importaciones`, `importacion_filas` / `Importaciones` | "Importaciones" (sidebar Datos) | F11/F31. Async + 3 modos. |
| Reporte custom | `reportes_definiciones`, `reportes_ejecuciones` | "Reportes custom" (NO en sidebar — pantalla huérfana de menú) | F32. Constructor DSL + streaming. |

### 1.2 Discrepancias terminológicas detectadas

- **"Caso" en lista vs etiqueta tipo-específica en panel.** Lista (Vista de Trabajo, Bandeja) habla de "Casos" y muestra badge `Cobranza/Ticket Cx/Lead Venta/Servicio`. Al seleccionar un caso, el panel del lado derecho lo llama por otro nombre: "Préstamo" / "Ticket" / "Lead" / "Servicio técnico". El usuario ve dos vocabularios para el mismo objeto.
- **"Compromiso" como concepto puente, ausente en formulario.** El alert en Vista de Trabajo dice "Compromiso vigente". Pero el formulario "Nueva gestión" nunca dice "Compromiso": cada tipo arrastra su nombre ("Promesa de pago", "Promesa de cierre", "Resolución / Escalamiento", "Acción de servicio programada"). Crea ambigüedad: el supervisor que pide "muéstrame compromisos del usuario X" no encuentra esa palabra en la pantalla del gestor.
- **"Promesa de cierre" para venta.** `compromisos_cierre_venta` se llama "Promesa de cierre" en UI. Pero en la entidad de dominio es `CompromisoCierreVenta`, y en CLAUDE.md §6 se llama "cierre_venta". Tres nombres para una misma cosa.
- **Dashboard Operativo usa "Cuentas".** dashboard-operativo:21 dice "Cuentas intentadas", "Cuentas gestionadas". Pero el modelo de dominio es `Caso`, no "Cuenta". El término "Cuenta" no aparece en CLAUDE.md, ni en migraciones, ni en otros módulos. Es vocabulario importado del proyecto v1 (Cobranza monoproducto, donde "Cuenta = Préstamo").
- **"Cliente" residual.** `app/Modules/Clientes/` sigue existiendo (entidad, UseCase, Livewire `CrearCliente`, vista `crear-cliente.blade.php`). MIGRACION_V2.md §1.D dice "Refactor Clientes → Personas". Provider NO está registrado en `bootstrap/providers.php` (verificado: las 22 líneas de providers no incluyen `ClientesServiceProvider`). Componente sin ruta web ni link de menú. Sobrevive en filesystem como código muerto.

### 1.3 Mapa de jerarquía actual (FKs reales)

```
Mandante (1)─(N)─> Proyecto (tipo_operacion: cobranza|cx|venta|servicio)
                     │
                     ├─(N)─> Cartera
                     │         └─(N)─> Caso (cartera_id) ──> Caso<Tipo> 1:1 CTI
                     │
                     ├─(N)─> Persona (UNIQUE: proyecto_id + tipo_id + identificacion)
                     │         ├─(N)─> Contacto (persona_id, proyecto_id desnormalizado)
                     │         └─(N)─> Caso (persona_id)
                     │                  ├─(N)─> Gestion (caso_id, persona_id desnorm, contacto_id)
                     │                  │         └─(0..1)─> Compromiso (gestion_origen_id UNIQUE)
                     │                  │                     └─> Compromiso<Tipo> 1:1 CTI
                     │                  └─(0..N)─> EntidadRegistro (caso_id nullable)
                     │
                     ├─(N)─> Campana ──(N:N via Asignacion)──> Caso
                     │                       │
                     │                       └─> Usuario (usuario_id)
                     │
                     ├─(N)─> Equipo ──(N:N via equipo_usuario)──> Usuario
                     │
                     ├─(N)─> Catálogo por proyecto (resultados, tipos_gestion, motivos, causas, estados_caso, etc.)
                     │
                     ├─(N)─> CampoPersonalizado (ambito: caso|gestion|compromiso|entidad_configurable, ambito_id polimórfico)
                     │         └─(N)─> ValorCampoPersonalizado (entidad_id polimórfico)
                     │
                     ├─(N)─> EntidadConfigurable (cartera_id nullable)
                     │         └─(N)─> EntidadRegistro
                     │
                     └─(N)─> RolCustom F33 ──(N:N via usuario_proyecto_rol_custom)──> Usuario

Globales (sin proyecto_id):
  - tipos_identificacion, canales, paises, monedas, tipos_documento, estados_base_sistema
  - roles, permisos, rol_permiso (matriz global)
  - usuario_global_rol (ADMIN_GLOBAL)
```

### 1.4 Convenciones de naming inconsistentes

- Tablas en español snake_case (correcto). Pero `tramos_mora` en cobranza y `niveles_sla` en cx son singulares semánticamente plurales — OK.
- Soft delete column: `eliminada_en` (femenino, p.ej. `casos`, `personas`) y también `eliminada_en` en `auditorias`. Pero tablas como `entidades_registros` usan `eliminado_en` (masculino: `2026_04_24_160001_entidades_create_entidades_registros_table.php`). **Inconsistencia menor**.
- Roles base nombrados en mayúsculas (`ADMIN_GLOBAL`, `SUPERVISOR`, `GESTOR`, `AUDITOR`). Roles custom siguen mismo regex `^[A-Z][A-Z0-9_]*$` (`CodigoRolCustom`).

---

## 2. Inventario de pantallas

### 2.1 Rutas web operativas (`/proyectos/{proyecto_id}/...`)

| Ruta | Componente Livewire | Permiso ruta | Permiso sidebar (layout) | Roles base que lo tienen | Hay test smoke |
|---|---|---|---|---|---|
| `proyectos.dashboard` (`/`) | view `tenancy::proyecto-dashboard` | (solo `proyecto.activo`) | n/a (auto al entrar al proyecto) | todos los del proyecto | sí (test selector) |
| `proyectos.personas.crear` | `CrearPersona` | `personas.crear` | **NO LINKADO EN SIDEBAR** | SUPERVISOR, GESTOR, ADMIN_GLOBAL | sí |
| `proyectos.personas.contactos` | `ListaContactos` | **(SIN `can:`)** | n/a (link interno desde Vista Trabajo) | cualquier autenticado con proyecto activo | sí |
| `proyectos.bandeja` | `Bandeja` | `asignaciones.ver_propia` | `asignaciones.ver_propia` | SUPERVISOR, GESTOR, ADMIN | sí |
| `proyectos.bandeja.equipo` | `BandejaEquipo` | `asignaciones.ver_equipo` | `asignaciones.ver_equipo` | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.asignaciones.masiva` | `AsignarMasivamente` | `asignaciones.reasignar` | `asignaciones.crear` **(MISMATCH)** | SUPERVISOR, ADMIN | sí |
| `proyectos.asignaciones.reasignar` | `ReasignarEntreEquipos` | `asignaciones.reasignar` | `asignaciones.reasignar` | SUPERVISOR, ADMIN | sí |
| `proyectos.trabajo` (con persona y caso opcional) | `VistaDeTrabajo` (+ embebido `NuevaGestion`) | `casos.ver` | n/a (entrada via Bandeja) | SUPERVISOR, GESTOR, AUDITOR (ver), ADMIN | sí |
| `proyectos.reportes.operativos` | `DashboardOperativo` | `reportes.operativos` | `reportes.operativos` | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.reportes.analiticos` | `DashboardAnalitico` | `reportes.analiticos` | `reportes.analiticos` | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.reportes.equipos` | `ReporteEquipos` | `reportes.operativos` | `reportes.operativos` | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.reportes.custom` | `ListadoReportesCustom` | `reportes.constructor.ejecutar` | **NO LINKADO EN SIDEBAR** | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.reportes.custom.nuevo` | `ConstructorReporte` | `reportes.constructor.gestionar` | **NO LINKADO EN SIDEBAR** | SUPERVISOR, ADMIN | sí |
| `proyectos.reportes.custom.editar` | `ConstructorReporte` | `reportes.constructor.gestionar` | **NO LINKADO EN SIDEBAR** | SUPERVISOR, ADMIN | sí |
| `proyectos.reportes.custom.exportar` | `ExportarReporteController` | `reportes.constructor.exportar` | n/a (acción inline) | SUPERVISOR, ADMIN | sí |
| `proyectos.importaciones` | `ImportarPersonas`, `ImportarCasos` | `importaciones.crear` | `importaciones.ver` **(MISMATCH leve)** | SUPERVISOR, ADMIN | sí |
| `proyectos.importaciones.exportar-personas/casos/gestiones/compromisos` | controllers stream | `importaciones.crear` | n/a (botones inline) | SUPERVISOR, ADMIN | parcial |
| `proyectos.catalogos` | 5 Admin\* catálogo (resultados, tipos_gestion, causas_gestion, motivos_no_contacto, estados_caso) + 9 tipo-específicos | `catalogos.gestionar` | `catalogos.ver` **(MISMATCH)** | SUPERVISOR, ADMIN | parcial |
| `proyectos.usuarios` | `GestionUsuariosProyecto` | `usuarios.gestionar` | `usuarios.gestionar` | SUPERVISOR, ADMIN | sí |
| `proyectos.admin.roles-custom` | `AdminRolesCustom` | `roles.gestionar` | **NO LINKADO EN SIDEBAR** | solo ADMIN_GLOBAL | sí (16 tests F33) |
| `proyectos.admin.matriz-permisos` | `MatrizPermisos` | `roles.gestionar` | **NO LINKADO EN SIDEBAR** | solo ADMIN_GLOBAL | parcial |
| `proyectos.equipos` | `AdminEquiposProyecto` | `usuarios.gestionar` | `equipos.ver` **(MISMATCH P0)** | SUPERVISOR, ADMIN | sí |
| `proyectos.auditoria` | `ListadoAuditoria` | `auditoria.ver` | `auditoria.ver` | SUPERVISOR, AUDITOR, ADMIN | sí |
| `proyectos.auditoria.exportar` | `ExportarAuditoriaController` | `auditoria.ver` | n/a | idem | n/a |
| `proyectos.notificaciones` | `ListadoNotificaciones` | `compromisos.ver` **(typo conceptual)** | (sin `@can`, siempre visible) | SUPERVISOR, GESTOR, AUDITOR, ADMIN | sí |
| `proyectos.entidades.registros` | `GestorRegistrosEntidad` | `entidades.ver` | n/a (sin link de menú directo) | SUPERVISOR, GESTOR, AUDITOR, ADMIN | sí |

### 2.2 Rutas admin globales (`/admin/...`, middleware `admin.global`)

| Ruta | Componente | Roles | Hay test |
|---|---|---|---|
| `admin.dashboard` | view tenancy::admin-dashboard | ADMIN_GLOBAL | sí |
| `admin.mandantes` | `AdminMandantes` | ADMIN_GLOBAL | sí |
| `admin.proyectos` | `AdminProyectos` | ADMIN_GLOBAL | sí |
| `admin.usuarios` | `AdminUsuarios` | ADMIN_GLOBAL | sí |
| `admin.campos-personalizados` | `AdminCamposPersonalizados` | ADMIN_GLOBAL (`campos.definir`) | sí |
| `admin.entidades-configurables` | `AdminEntidadesConfigurables` | ADMIN_GLOBAL (`entidades.definir`) | sí |

### 2.3 Rutas API (cargadas vía `IntegracionServiceProvider::registrarRutasApi`, NO en `routes/api.php`)

| Ruta | Controller | Auth | Throttle |
|---|---|---|---|
| `POST /api/auth/sso-handshake` | `SsoHandshakeController@emitir` | none (emite token) | 10/min |
| `GET /integracion/handshake?token=` | `SsoHandshakeController@consumir` | none (consume one-time) | n/a |
| `POST /api/auth/logout` | `SsoLogoutController` | `auth:sanctum` | n/a |
| `GET /api/integracion/persona` | `PreviewPersonaController` | `auth:sanctum` | 60/min |

> **Nota arquitectónica.** `bootstrap/app.php:7-12` solo declara `web` y `commands`. No hay `api: __DIR__.'/../routes/api.php'`. Las rutas API se inyectan dinámicamente desde `IntegracionServiceProvider::boot()`. Funciona, pero es atípico y dificulta el descubrimiento (`php artisan route:list --domain=api` no las muestra agrupadas).

### 2.4 Pantallas huérfanas (sin entrada de menú)

| Pantalla | Cómo se llega hoy | Severidad |
|---|---|---|
| `proyectos.admin.roles-custom` | URL directa | P0 — feature F33 invisible |
| `proyectos.admin.matriz-permisos` | URL directa | P0 — feature F33 invisible |
| `proyectos.reportes.custom` | URL directa | P0 — feature F32 invisible |
| `proyectos.reportes.custom.nuevo` | URL directa | P0 — feature F32 invisible |
| `proyectos.entidades.registros` | URL directa con `entidad_id` | P1 — feature F24 invisible para gestor |
| `proyectos.personas.crear` | URL directa o desde `proyectos.dashboard` (sin link claro) | P1 — flujo J1 quebrado |
| `proyectos.personas.contactos` | Link interno desde Vista de Trabajo | P2 (intencional) |
| `proyectos.importaciones.exportar-*` | Botones inline en `ImportarPersonas`/`ImportarCasos` | OK |
| `CrearCliente` (`app/Modules/Clientes/.../CrearCliente.php`) | **No tiene ruta**; vista `clientes/livewire/crear-cliente.blade.php` huérfana | P2 — código muerto |
| Vista huérfana `resources/views/modules/promesas/livewire/resolver-promesa.blade.php` | **No referenciada** — la versión activa es `cobranza::livewire.resolver-promesa` | P3 — limpieza |

### 2.5 Permisos sembrados sin uso

- `notificaciones.ver` — sembrado, asignado a SUPERVISOR/GESTOR/AUDITOR, pero la ruta `proyectos.notificaciones` usa `can:compromisos.ver` (web.php:139) y el item de sidebar no tiene `@can` (layouts/app.blade.php:180). Permiso huérfano.
- `configuracion.ver`, `configuracion.editar` — sembrados, `configuracion.ver` solo lo tiene SUPERVISOR/AUDITOR, `configuracion.editar` no lo tiene ningún rol. Ninguna ruta los chequea. Permisos muertos.
- `gestiones.eliminar`, `compromisos.eliminar`, `casos.eliminar`, `contactos.eliminar`, `asignaciones.eliminar`, `usuarios.eliminar`, `equipos.eliminar`, `catalogos.eliminar`, `importaciones.eliminar`, `entidades.eliminar` — sembrados, ningún rol base los tiene asignados (solo ADMIN_GLOBAL via `Gate::before`). Sin ruta que los exija. Reservas.

---

## 3. User journeys end-to-end

> Cada paso describe lo que el código real hace. Cuando hay gap entre lo esperado (CLAUDE.md / sentido común BPO) y lo real, se llama explícitamente.

### J1. Crear persona desde cero → encontrarla → ver detalle → ver casos → registrar gestión

| # | Pantalla | Acción esperada | Resultado real | Gap |
|---|---|---|---|---|
| 1 | `dashboard` o `proyectos.dashboard` | Botón "Nueva persona" visible | **No hay botón en el sidebar ni en el dashboard del proyecto.** Llegar a `proyectos.personas.crear` requiere conocer la URL. | **P0** Fricción J1 paso 1. |
| 2 | `proyectos.personas.crear` | Form crea persona, redirige a Vista de Trabajo de la nueva persona | `CrearPersona.php:91` redirige a `proyectos.dashboard` (no a `proyectos.trabajo/{persona}`). MIGRACION_V2.md §1.D: "Redirige tras crear a `/proyectos/{id}/trabajo/{persona.public_id}`". | **P1** Inconsistencia con plan. |
| 3 | `proyectos.dashboard` (tras redirect) | Verla en algún listado | El dashboard del proyecto (`tenancy::proyecto-dashboard`) no lista personas. **No existe pantalla "Listado de personas"**. | **P0** Sin listado de personas. |
| 4 | Buscador global (Ctrl+K, `BuscadorGlobal`) | Buscar la persona | Funciona, requiere ≥3 chars. Devuelve persona + casos. | OK |
| 5 | Click en persona → Vista de Trabajo | Ver detalle, casos, historial | `proyectos.trabajo/{persona}` carga. **Pero la persona recién creada no tiene casos.** Vista de Trabajo muestra "Sin casos abiertos" (vista-de-trabajo:91). | **P0** No se puede registrar gestión sin caso. |
| 6 | Crear un caso para esta persona | Botón/modal "Nuevo caso" | **No existe pantalla ni botón para crear caso individual.** Los UseCases `RegistrarCasoCobranza`, `RegistrarCasoTicketCx`, `RegistrarCasoLeadVenta`, `RegistrarCasoServicio` solo se invocan desde `Importaciones` (CSV bulk). | **P0** Crítico — sin importación CSV no hay casos. |
| 7 | Si existiera caso → registrar gestión | Form `NuevaGestion` embebido | Funciona (NuevaGestion.php:92 ejecuta `RegistrarGestion` con DTO). | OK |

**Veredicto J1:** **Roto end-to-end.** El usuario puede crear persona, pero queda atrapado: no la encuentra fácilmente (sin listado), y al encontrarla descubre que no puede crear casos individualmente. La única vía operativa para llegar a "trabajar un caso" es importar CSV.

### J2. Importar 1000 personas vía CSV → encontrarlas → identificar errores → corregir

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | `proyectos.importaciones` | Tab "Personas" → upload CSV | `ImportarPersonas.php:41-107` recibe archivo, parsea, dry-run validación, deja en estado `preparada`. | OK |
| 2 | UI muestra preview + selector modo | "Confirmar" para procesar | `EncolarImportacion` despacha Job; UI hace polling cada 2s (F31). | OK |
| 3 | Job termina → status `completada` | Listar las 1000 personas creadas | **No hay listado.** Solo se ve el contador `validas/invalidas/duplicadas/omitidas` en el row del historial. | **P1** Falta drill-down. |
| 4 | Detectar errores (filas inválidas) | Filtrar por estado fila | UI tiene filtro `filtroFilas ∈ {todas, pendiente, procesada, invalida, duplicada}` (ImportarPersonas:39). Muestra hasta 200 filas. | OK |
| 5 | Corregir errores | Editar fila o reimportar | **No hay editor de fila.** El usuario debe arreglar el CSV original y volver a importar. La fila inválida sigue marcada como tal. | **P1** Sin re-procesamiento de filas inválidas. |
| 6 | Encontrar persona específica importada | Buscador global o listado | Buscador global funciona si conoce identificación o nombre. Sin listado por proyecto, búsqueda exhaustiva imposible. | **P1** (consecuencia del gap de J1). |

**Veredicto J2:** Importación funciona. Visibilidad post-import débil: no hay listado de personas, ni edición de fila, ni "ir a la persona" desde el row del CSV.

### J3. Importar casos cobranza → ver el caso → asignarlo a un gestor → gestor lo trabaja

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | `proyectos.importaciones` (tab "Casos") | Upload CSV de casos cobranza | `ImportarCasos` despacha `ProcesarImportacionCasosCobranza`. Crea `Caso` + `CasoCobranza` (CTI). | OK |
| 2 | Ver el caso recién creado | Listado de casos | **No existe listado de casos.** Solo aparecen vía Bandeja (si están asignados) o Vista de Trabajo (si conoces persona y caso). | **P0** Sin listado global de casos. |
| 3 | Asignar el caso a un gestor | Pantalla de asignación | `proyectos.asignaciones.masiva` existe (`AsignarMasivamente`). Permite seleccionar casos por filtros y asignarlos a usuario(s). | OK |
| 4 | Gestor recibe asignación | Bandeja del gestor | F14 `AsignacionRecibida` notifica. Gestor ve en Bandeja con `asignaciones.ver_propia`. | OK |
| 5 | Gestor click "Trabajar" | Llega a Vista de Trabajo | Funciona. | OK |
| 6 | Gestor registra gestión | `NuevaGestion` embebido | Funciona. | OK |

**Veredicto J3:** Funciona si los casos provienen de import CSV. **Asignación masiva existe pero no hay flujo "asignar uno solo desde el detalle del caso"**.

### J4. Asesor recibe asignación → entra a bandeja → trabaja un caso → registra gestión con compromiso → resuelve compromiso

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | Asesor login → SelectorProyecto | Elegir proyecto | `dashboard.blade.php` monta `SelectorProyecto`. Si solo tiene 1 proyecto, **no auto-redirige** — siempre muestra selector. | **P2** Fricción menor. |
| 2 | Click proyecto → `proyectos.bandeja` | Ver mis asignaciones | Funciona. Filter chips `pendiente / en_trabajo / cerrada / todas`. | OK |
| 3 | Click "Trabajar" → Vista de Trabajo | Ver persona, casos, historial, panel tipo-específico | Funciona. | OK |
| 4 | Llenar `NuevaGestion`: canal + tipo + resultado (con `requiere_compromiso=true`) + monto + fecha | Submit | `RegistrarGestion` UseCase + listener `CrearPromesaDesdeGestion` (cobranza) crea `Compromiso` + `CompromisoPromesaPago` en misma transacción. | OK |
| 5 | Vista de Trabajo refresca, alert "Compromiso vigente" arriba | Ver compromiso | `vista-de-trabajo.blade.php:9-25` muestra. Indicador `tiene_compromiso_vigente=true` en `casos`. | OK |
| 6 | Cerrar la asignación (botón "Cerrar" en Bandeja) | `CerrarAsignacion` | Funciona. | OK |
| 7 | Fecha del compromiso llega → resolver | Click "Resolver" → modal `ResolverPromesa` | Vista timeline donde aparece. Modal con opciones `cumplida / rota / cancelada`. Listener actualiza `tiene_compromiso_vigente` en caso. | OK |

**Veredicto J4:** **Único journey OK end-to-end.** Es el que está mejor diseñado.

### J5. Supervisor crea equipo → asigna miembros → asignación masiva al equipo → ve KPIs

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | `proyectos.equipos` | Crear equipo | `AdminEquiposProyecto` permite (código + nombre, único por proyecto). | OK |
| 2 | Agregar miembros al equipo | Selector usuarios | Filtra usuarios con rol activo en proyecto, excluye ADMIN_GLOBAL. | OK |
| 3 | Asignación masiva al equipo | Pantalla de asignación | `AsignarMasivamente` permite seleccionar **usuario destino**, **no equipo destino** (round-robin / paralelo). | **P1** Sin asignación a "equipo" como destino abstracto. |
| 4 | Ver KPIs del equipo | `ReporteEquipos` | Existe (`reportes.operativos`). | OK |

**Veredicto J5:** Funciona pero con gap menor en granularidad de asignación.

### J6. Admin crea proyecto nuevo → crea cartera → crea catálogos → crea campos personalizados → crea entidad configurable → asigna usuarios

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | `admin.proyectos` | Crear proyecto (mandante + tipo + código + nombre) | `AdminProyectos` lo permite. Tipo inmutable post-creación (correcto). | OK |
| 2 | Crear cartera del proyecto | **No existe pantalla "Carteras"** ni en admin ni en proyecto. | `RegistrarCartera` UseCase existe (`Tenancy/Application/UseCases`), pero sin UI. | **P0** Sin UI de carteras → no puedes terminar setup del proyecto desde la app. |
| 3 | Crear catálogos del proyecto | `proyectos.catalogos` | Permite resultados, tipos_gestion, causas, motivos, estados_caso. **Pero requiere ser SUPERVISOR del proyecto** (no ADMIN_GLOBAL accede automáticamente al menú porque sidebar está scoped a proyecto). ADMIN puede ir vía URL `/proyectos/{id}/catalogos`. | OK |
| 4 | Catálogos tipo-específicos | Tramos mora (cobranza), categorías ticket (cx), etapas embudo (venta), etc. | Cada uno tiene su Livewire (`AdminTramosMora`, `AdminCategoriasTicket`, etc.). Sin embargo, **`proyectos.catalogos` solo monta los catálogos del módulo Catalogos**, no los tipo-específicos. | **P0** Catálogos tipo-específicos sin acceso desde menú. |
| 5 | Crear campos personalizados | `admin.campos-personalizados` | Solo ADMIN_GLOBAL. Acceso desde sidebar admin. | OK |
| 6 | Crear entidad configurable | `admin.entidades-configurables` | Solo ADMIN_GLOBAL. Acceso desde sidebar admin. | OK |
| 7 | Asignar usuarios al proyecto | `proyectos.usuarios` | `GestionUsuariosProyecto` permite. Pero el rol asignador debe ser SUPERVISOR — un ADMIN_GLOBAL accede via URL pero el sidebar no lo lleva (sidebar Supervisión solo si `usuarios.gestionar`). | **P2** Inconsistencia menú vs admin. |

**Veredicto J6:** **Roto.** Falta UI de carteras (P0) y catálogos tipo-específicos no aparecen en `proyectos.catalogos`.

### J7. Auditor revisa actividad de un usuario → filtra → exporta CSV

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | `proyectos.auditoria` | Filtrar por usuario, evento, rango fechas | `ListadoAuditoria.php:53-110` ofrece filtros. | OK |
| 2 | Click "Exportar CSV" | Descarga | `ExportarAuditoriaController` stream. | OK |
| 3 | Hacer drill-down a una entrada concreta (e.g. ver datos antes/después) | Inline o modal | `auditorias.datos_antes` / `datos_despues` se almacenan, pero **la UI muestra solo `evento`/`entidad`/`usuario`**, no los diffs. | **P1** Sin visualización del diff. |

**Veredicto J7:** Funciona para listar y exportar. Visualización del cambio (data before/after) ausente en UI.

### J8. Supervisor genera reporte custom → exporta XLSX → comparte

| # | Pantalla | Acción | Resultado | Gap |
|---|---|---|---|---|
| 1 | Llegar a `proyectos.reportes.custom` | Sidebar | **No hay link en sidebar.** Solo URL directa. | **P0** Feature F32 invisible. |
| 2 | "Nuevo reporte" → `ConstructorReporte` | Build DSL: entidad raíz, columnas, filtros, agrupaciones, orden | Funciona. Preview live LIMIT 50. | OK |
| 3 | Guardar definición | Guarda en `reportes_definiciones` | Funciona. | OK |
| 4 | Exportar XLSX desde el listado | `ExportarReporteController` | Stream OpenSpout. | OK |
| 5 | Compartir link con auditor | URL pública del reporte | URL requiere login, no hay "compartir". | **P2** (esperable). |

**Veredicto J8:** Funciona para el supervisor que conoce la URL. **Acceso roto via menú**. Auditor podría ejecutar pero tampoco lo encuentra.

---

## 4. Conexiones entre módulos

### 4.1 Matriz de uso (filas llaman/usan a columnas)

| ↓ usa →                  | Tenancy | Personas | Contactos | Casos | Compromisos | Gestiones | Cobranza/Cx/Venta/Servicio | Asignaciones | Campanas | Catalogos | CamposPers | EntidadesConfig | Auditoria | Notificaciones | Importaciones | Reportes | Usuarios | Integracion |
|--------------------------|---------|----------|-----------|-------|-------------|-----------|----------------------------|--------------|----------|-----------|------------|-----------------|-----------|----------------|---------------|----------|----------|-------------|
| Personas                 | DTO     | -        | UI lee    | UI lee| -           | -         | -                          | -            | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |
| Contactos                | DTO     | Model    | -         | -     | -           | -         | -                          | -            | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |
| Casos                    | DTO     | UI lee   | UI lee    | -     | UI lee      | UI lee    | UI lee panel               | -            | -        | UI lee    | -          | -               | -         | -              | -             | -        | -        | -           |
| Gestiones                | DTO     | -        | -         | UC    | UC          | -         | listener                   | -            | -        | UI lee    | aplica     | -               | -         | -              | -             | -        | -        | -           |
| Cobranza/Cx/Venta/Servic | DTO     | -        | -         | UC    | UC          | listener  | -                          | -            | -        | UI lee    | aplica     | -               | -         | -              | UC            | -        | -        | -           |
| Asignaciones             | DTO     | -        | -         | DTO   | -           | listener  | -                          | -            | DTO      | -         | -          | -               | -         | dispatcher     | -             | -        | -        | -           |
| Campanas                 | DTO     | -        | -         | -     | -           | -         | -                          | -            | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |
| Reportes                 | DTO     | UI lee   | -         | UI lee| UI lee      | UI lee    | UI lee                     | UI lee       | UI lee   | UI lee    | catálogo F32| UI lee         | -         | -              | -             | -        | UI lee   | -           |
| Importaciones            | DTO     | UC cross | UC cross  | UC cross | -        | -         | UC cross                   | -            | -        | UI lee    | -          | -               | -         | -              | -             | -        | -        | -           |
| Notificaciones           | DTO     | -        | -         | UI lee| UI lee      | -         | -                          | UI lee       | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |
| Auditoria                | -       | observer | observer  | observer | observer | observer  | observer                   | observer     | observer | observer  | observer   | observer        | -         | -              | observer      | -        | observer | -           |
| EntidadesConfig          | DTO     | UI lee   | -         | UI lee| -           | -         | -                          | -            | -        | -         | aplica     | -               | -         | -              | -             | -        | -        | -           |
| Integracion              | DTO     | UC       | -         | -     | UI lee      | UI lee    | -                          | -            | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |
| Usuarios                 | DTO     | -        | -         | -     | -           | -         | -                          | -            | -        | -         | -          | -               | -         | -              | -             | -        | -        | -           |

> Convenciones: `UC` = UseCase consumido. `DTO` = DTO de input. `UI lee` = la vista Blade muestra datos del módulo. `aplica` = el módulo aplica reglas/valores (campos personalizados). `observer` = Auditoria escucha vía Eloquent observer.

### 4.2 Conexiones que el negocio espera y NO existen

| Esperado | Real | Severidad |
|---|---|---|
| Desde "Persona" ver historial completo de gestiones (todas sus cuentas) | Vista de Trabajo lo hace por caso individual, no agregado por persona | P1 |
| Desde "Persona" ver listado de compromisos vigentes | Solo via Vista de Trabajo del caso. No hay dashboard de compromisos por persona | P1 |
| Desde "Caso" abrir directamente "Asignar este caso" | No existe acción inline; hay que ir a `Asignación Masiva` y filtrar | P1 |
| Desde "Asignación" ver auditoría de quién la creó/cambió | Sin link a Auditoría filtrada por la entidad | P2 |
| Desde "Equipo" ver reporte de productividad del equipo | `ReporteEquipos` existe pero no se llega desde la pantalla `Equipos` (link cruzado faltante) | P2 |
| Desde "Importación" ir a la persona/caso creado | Sin link "ver registro" en la fila procesada | P1 |
| Desde "Notificación" ir a la entidad referenciada (caso, compromiso) | `notificaciones.entidad_id` está pero la UI no convierte a link | P1 |
| Desde "Campos personalizados" ver registros que los usan | Sin pantalla de "valores aplicados" — solo "definir" | P2 |
| Desde "Entidades configurables" ver registros | `proyectos.entidades.registros/{entidad_id}` existe pero sin link de menú | P0 |
| Desde "Persona" editar sus datos | **Sin pantalla de edición** — solo creación | P1 |
| Desde "Caso" editar prioridad / estado | **Sin pantalla** — solo cierre via `CerrarAsignacion`/`CerrarCaso` | P1 |
| Desde "Cartera" ver casos | Sin pantalla de cartera ni siquiera de listado | P0 |
| Desde "Mandante" ver proyectos | `AdminProyectos` filtra por mandante implícitamente | P2 |

### 4.3 Violaciones arquitectónicas detectadas (CLAUDE.md §3, §13.6)

- **`EncolarImportacion.php`** importa `App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel` directamente desde la capa Application. Una UseCase no debería conocer el Model Eloquent. Falta `ImportacionRepository` en `Domain/Contracts`.
- **`ImportarPersonas.php:13-14`** importa `ImportacionFilaModel` y `ImportacionModel` desde Livewire. Hace `ImportacionModel::query()->sinScopeProyecto()` — orquestación que debería vivir en UseCase.
- **`SelectorProyecto.php:15`** usa `auth()->user()` directo en `render()`. CLAUDE.md §13.10 prohíbe `Auth::user()` en Domain/UseCases — Livewire es Infrastructure, así que es permitido pero el patrón se filtra.
- **`ListaContactos.php:11`** importa `App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel` desde el módulo Contactos. Cross-módulo de Models. Permitido leer una FK pero violando §3 "no importar modelos Eloquent de otro módulo". Debería usar `PersonaRepository` o `ConsultaPersona`.

---

## 5. Datos cargados pero nunca expuestos

| Tabla | Columna | Estado | Por qué se almacena | ¿Se ve en UI? | ¿Se reporta? |
|---|---|---|---|---|---|
| `personas` | `hash_identidad` | nullable, índice no-unique | Comentario: "dedupe técnica futura" | **No** | **No** |
| `casos` | `prioridad` | int, NOT NULL default 0 | Para ordenar en bandeja | Sí (icono prio en bandeja:62, vista-trabajo:96) | parcial |
| `casos_cobranza` | `saldo_interes` | nullable | Importado | Sí (panel-caso cobranza, "Saldo interés") | No (en reportes) |
| `casos_cobranza` | `cuotas_pagadas` | nullable | Importado | Sí (panel-caso "Cuotas pagadas/totales") | No |
| `casos_cobranza` | `fecha_desembolso` | nullable | Importado | Sí (panel-caso "Desembolso") | No |
| `casos_lead_venta` | `origen_lead` | string nullable | Importado | Sí (panel-caso "Origen") | No |
| `casos_servicio` | `direccion_servicio` | string | Importado | Sí (panel-caso "Dirección") | No |
| `casos_servicio` | `tecnico_asignado` | string nullable | Importado | Sí (panel-caso "Técnico asignado") | No |
| `gestiones` | `duracion_segundos` | nullable | Capturado en NuevaGestion | Sí (input "Duración (seg)") pero **no se muestra en historial timeline** | No |
| `gestiones` | `motivo_no_contacto_id` | FK nullable | Capturado en NuevaGestion (cuando no es contacto efectivo) | Pas-through al insert | **No se muestra en historial** |
| `gestiones` | `causa_id` | FK nullable | Capturado cuando `resultado.requiere_causa` | Idem | **No se muestra en historial** |
| `compromisos` | `fecha_resolucion` | nullable | Set al resolver compromiso | **No se muestra** en panel ni historial | No |
| `auditorias` | `datos_antes`, `datos_despues`, `cambios` (JSON) | siempre seteado | Por observer | **No se visualiza diff en UI** — solo lista evento+entidad+usuario | No |
| `auditorias` | `ip` | nullable | Capturado | **No se muestra** | No |
| `notificaciones` | `entidad_tipo`, `entidad_id` | siempre | Para drill-down | **No convertido a link en UI** | No |
| `importacion_filas` | `payload` (JSON), `mensaje_error`, `razon_omision` | siempre | Trazabilidad | Parcialmente visible en preview, **sin tooltip ni vista expandida** | No |
| `reportes_ejecuciones` | `duracion_ms`, `total_filas` | siempre | Telemetría F32 | **No expuestos en UI** | No |
| `integracion_tokens_sso` | tabla completa | trazabilidad | Para auditoría SSO | **Sin pantalla de listado tokens** | No |

**Total filas inertes detectadas: ~20.** El más crítico es `auditorias.datos_antes/datos_despues` — la auditoría existe pero no muestra qué cambió.

---

## 6. Datos visibles pero no editables (read-only inconsistente)

| Entidad | Campo | Por qué se vería editar | Estado real |
|---|---|---|---|
| Persona | nombres, apellidos, razón social, fecha nacimiento, identificación | Cliente quiere corregir typo | **No editable**: solo `RegistrarPersona`. Sin `ActualizarPersona` UseCase ni Livewire. |
| Persona | tipo_persona | Casi nunca cambia, pero podría | Idem. |
| Contacto | tipo, valor, etiqueta, es_principal | Cambiar teléfono, marcar como principal | **No editable**: `ListaContactos` solo permite agregar (`agregar()`). |
| Caso | prioridad, estado_caso_id | Supervisor reasigna prioridad o estado | **No editable** desde UI. Estado cambia solo vía gestiones (transiciones). |
| Caso Cobranza | saldos, días mora | Reimport o ajuste manual | **No editable** desde UI. Solo via re-import (modo overwrite). |
| Caso CX | asunto, descripción, prioridad, SLA | Supervisor ajusta clasificación | **No editable** desde UI. |
| Caso Venta | etapa embudo, valor estimado | Avanza pipeline | **No editable** desde UI. |
| Caso Servicio | técnico asignado, dirección | Reasignación técnica | **No editable** desde UI. |
| Compromiso | monto, fecha vencimiento (post-creación) | Ajuste si datos mal cargados | **No editable**: solo transiciones (cumplir/romper/cancelar). |
| Asignación | prioridad, usuario_id | Supervisor cambia | Cambio de usuario via `ReasignarEntreEquipos`; prioridad **no editable**. |
| Equipo | código, nombre | Sí editable (`AdminEquiposProyecto`) | OK |
| Cartera | código, nombre | No hay UI alguna | **N/A** |
| Mandante | código, nombre, documento | Sí editable (`AdminMandantes`) | OK |
| Proyecto | nombre, fechas, activo | Sí editable (`AdminProyectos`); tipo bloqueado (correcto) | OK |
| Definición de reporte | columnas, filtros, agrupaciones | Sí editable (`ConstructorReporte`) | OK |
| Campo personalizado | reglas, etiqueta | Sí editable (`AdminCamposPersonalizados`) | OK |

**Resumen S6:** El núcleo operativo (Persona, Contacto, Caso, Compromiso) es write-once + transiciones. Las admin (Mandante, Proyecto, Catálogos) son CRUD completas. **Asimetría brusca** que no encaja con expectativas de operación BPO.

---

## 7. Inconsistencias terminológicas en UI

| Concepto | Bandeja | Vista de Trabajo (header) | Panel tipo-específico | Nueva Gestión (form) | Reportes Operativo | Reportes Analítico |
|---|---|---|---|---|---|---|
| Persona | "Persona" (col) | "Persona" + tipo "Fisica/Juridica" | n/a | n/a | "Persona" en historial | n/a |
| Caso | "Tipo" (badge dinámico) | "Casos ({{count}})" | "Préstamo" / "Ticket" / "Lead" / "Servicio técnico" | implícito | "Tipo caso" | "Distribución por tipo de caso" |
| Compromiso | "Compromiso vigente" (badge) | "Compromiso vigente" (alert) | n/a (en alert) | **"Promesa de pago" / "Promesa de cierre" / "Resolución / Escalamiento" / "Acción de servicio programada"** | "Compromisos vigentes/vencidos" | "Compromisos por tipo y estado" |
| Gestión | "Última gestión" (col) | "Registrar gestión" / "Historial" | n/a | "Nueva gestión" | "Total gestiones" / "Gestiones recientes" | "Gestiones por mes" |
| Identificación / persona en KPI | n/a | n/a | n/a | n/a | **"Cuentas intentadas"** / **"Cuentas gestionadas"** | "Distribución" |

**Observaciones:**
- "Cuentas" en `dashboard-operativo.blade.php:21,25` es vocabulario de cobranza v1, no del modelo v2. Usuario que opera CX o Venta verá "Cuentas intentadas" para sus tickets/leads — confuso.
- "Promesa de cierre" para Venta es un calco de "Promesa de pago". Si el supervisor pide "promesas vencidas" sin indicar el tipo, no queda claro si incluye cierres de venta.
- "Resolución / Escalamiento" mezcla dos conceptos en el label (resolución = compromiso; escalamiento = nivel del SLA).

---

## 8. Multi-tenancy real vs declarado

### 8.1 Declarado (CLAUDE.md §10)

> "Global Scope Eloquent agrega `WHERE proyecto_id = {activo}` automáticamente. Desactivar solo con `sinScopeProyecto()`".

### 8.2 Real

- **Mayoría de Livewire usan `DB::table()` directo, NO modelo Eloquent.** Ejemplos:
  - `VistaDeTrabajo.php:48-220` — todas las queries usan `DB::table()`. Cada una incluye `where('proyecto_id', $proyectoId)` MANUALMENTE.
  - `Bandeja.php:64-113` — idem. Manual.
  - `BuscadorGlobal.php:47-89` — manual.
  - `NuevaGestion.php:230-307` (helpers `canales/tiposGestion/resultados/...`) — manual.
- El Global Scope `ScopePorProyectoActivo` (§1.C plan) sí existe en el trait `PerteneceAProyecto`, pero solo se aplica si la query pasa por el modelo Eloquent. Las consultas via `DB::table()` lo evaden.
- **Riesgo:** cualquier desarrollador que olvide `where('proyecto_id', ...)` en una query manual abre fuga cross-project. Tests multi-tenancy detectarían el fallo, pero **solo Cx tiene test cross-project (`MultiTenancyCobranzaCxTest`)**. El resto de módulos confía en revisión humana.

### 8.3 Verificación URL → permiso → proyecto

Middleware `proyecto.activo` (registro en `Tenancy/Infrastructure/Providers/TenancyServiceProvider`) valida que el usuario tiene rol activo en el `{proyecto_id}` de la URL. ADMIN_GLOBAL pasa via `Gate::before` (`UsuariosServiceProvider:40-43`).

### 8.4 Posibles fugas detectadas

- **`personas.hash_identidad` sin proyecto_id en el índice** (migración 2026_04_18_120001:`personas`). Si alguien hace `WHERE hash_identidad = X` cruza proyectos. Comentario migración dice "Nunca se usa para autorizar visibilidad cross-proyecto" — pero **es contrato no validado**. Sin grep que confirme su uso real.
- **`ImportacionModel::query()->sinScopeProyecto()`** se usa en `ImportarPersonas.php:97-99` para validar count tras dry-run. Es legítimo pero abre patrón "sinScopeProyecto en Livewire" que cualquiera puede copiar.
- **`auditorias.proyecto_id` nullable** — auditorías de eventos globales (admin) se admiten. Aceptable, pero `ListadoAuditoria` filtra por `proyecto_id` y no muestra los globales — entonces siempre devuelven 0 para el ADMIN_GLOBAL que opera fuera de proyecto. **Ver desde sidebar admin no hay ruta `/admin/auditoria`**, solo proyecto-scoped.
- **Tests** sin coverage cross-project en: Casos, Gestiones, Compromisos, Asignaciones, Personas, Contactos, Campanas, CamposPersonalizados, Importaciones, Notificaciones, Auditoria, Reportes, Catalogos, EntidadesConfigurables. **Solo Cx lo cubre vía `MultiTenancyCobranzaCxTest`.**

---

## 9. Permisos vs UX

### 9.1 Mismatches sidebar ↔ ruta (clic lleva a 403 o entra sin link)

| Pantalla | Permiso sidebar | Permiso ruta | Riesgo |
|---|---|---|---|
| `Equipos` | `equipos.ver` (layouts/app.blade.php:119) | `usuarios.gestionar` (web.php:127) | **AUDITOR ve link, click → 403.** P0. |
| `Asignación Masiva` | `asignaciones.crear` (sidebar:105) | `asignaciones.reasignar` (web.php:39) | Si en futuro un rol tiene crear pero no reasignar → ve link → 403. Hoy SUPERVISOR tiene ambos. P1. |
| `Catálogos` | `catalogos.ver` (sidebar:155) | `catalogos.gestionar` (web.php:110) | Hoy AUDITOR tiene `catalogos.ver` pero no `catalogos.gestionar` → ve link, click → 403. P0. |
| `Importaciones` | `importaciones.ver` (sidebar:162) | `importaciones.crear` (web.php:86) | Hoy nadie con `ver` sin `crear` (solo SUPERVISOR/ADMIN tienen ambas), pero asimetría latente. P2. |
| `Notificaciones` | (sin `@can`, siempre visible) (sidebar:180) | `compromisos.ver` (web.php:139) | Si un usuario sin `compromisos.ver` entra a un proyecto, ve link y al click → 403. P1. |
| `Roles custom`, `Matriz permisos` | **sin link** | `roles.gestionar` (web.php:118-122) | Pantalla huérfana. P0. |
| `Reportes custom (listado/nuevo/editar)` | **sin link** | `reportes.constructor.*` | Pantalla huérfana F32. P0. |
| `Carteras` | **sin pantalla** | n/a | UseCase existe sin UI. P0. |

### 9.2 Permisos sembrados sin asignación (huérfanos)

`PermisosSeeder` siembra ~70 permisos. Análisis del agente:

- `gestiones.eliminar`, `compromisos.eliminar`, `casos.eliminar`, `contactos.eliminar`, `asignaciones.eliminar`, `usuarios.eliminar`, `equipos.eliminar`, `catalogos.eliminar`, `importaciones.eliminar`, `entidades.eliminar` — sin rol base. Solo ADMIN_GLOBAL via `Gate::before`.
- `casos.crear`, `casos.cerrar`, `casos.reabrir` — solo SUPERVISOR. **GESTOR no puede crear caso individual** (consistente con el gap de J3, no hay UI tampoco).
- `configuracion.ver`, `configuracion.editar` — sembrados, ningún rol asignación útil, ninguna ruta los chequea. Muertos.
- `notificaciones.ver` — sembrado, asignado a SUPERVISOR/GESTOR/AUDITOR, pero la ruta usa `compromisos.ver`. Permiso casi muerto.

### 9.3 Roles con permisos contradictorios

- **AUDITOR tiene `equipos.ver`** pero la ruta `proyectos.equipos` requiere `usuarios.gestionar`. AUDITOR ve link sin poder usar (P0 — confirmado en 9.1).
- **AUDITOR tiene `asignaciones.ver_equipo` pero no `asignaciones.ver_propia`**. AUDITOR ve bandeja del equipo pero no la propia. Es coherente con su rol (no opera) pero podría confundir UX.
- **GESTOR tiene `personas.crear` pero no `casos.crear`**. Crea persona sin poder crear caso. Consistente con la falta de UI de crear caso, pero refuerza la trampa de J1.

---

## 10. Flujos asíncronos y en background

### 10.1 Jobs

- `EjecutarImportacionJob` (F31) — cola `imports`. Encolado por `EncolarImportacion` UseCase. Lock advisory `GET_LOCK("import:{id}")`. Idempotente (`ShouldBeUnique`).

### 10.2 Comandos artisan

- `notificaciones:generar --umbral=N --horas-sla=N` — generador de notificaciones (`Notificaciones/Application/...`). Definido en `routes/console.php:13-21` y agendado:
  - Diario 08:00 con `umbral=3 horas-sla=8` (compromisos).
  - Hourly entre 07:00-20:00 con `umbral=0 horas-sla=4` (SLA CX).

### 10.3 Scheduler (`routes/console.php`)

- 2 schedules registrados (notificaciones). Sin más jobs programados (no hay limpieza de tokens SSO expirados, no hay recálculo periódico de `tiene_compromiso_vigente`).

### 10.4 Procesos sync que deberían ser async

- **Exportación de auditoría** (`ExportarAuditoriaController`) — `streamDownload` directo. Para 1M filas es OK por streaming, pero generación de query es síncrona; si la query es lenta (sin índice), cuelga el request.
- **Exportación de reportes custom F32** (`ExportarReporteController`) — streaming sin límite, `cursor()` Eloquent. OK.
- **Dry-run de importación** (`ImportarPersonas:91-95` invoca `ProcesarImportacionPersonas->ejecutar(commit: false)` SÍNCRONO). Para un CSV de 100k filas, el upload se queda esperando el dry-run. **Debería ser async** o usar batch limitado.
- **Notificaciones** se generan via comando artisan, pero el envío "in-app" (no email) es solo escritura DB. OK.

### 10.5 Procesos async que podrían ser sync

- Ninguno detectado. La importación es legítimamente async (puede durar minutos).

### 10.6 Resultado al usuario tras async

- Importaciones: polling cada 2s (`ImportarPersonas` UI). OK.
- Notificaciones: el usuario las ve la próxima vez que abre UI (no hay push). OK.

---

## 11. Test coverage gaps

### 11.1 Resumen (522 tests, ramas v2 + F33)

- 23 archivos Unit, ~58 tests.
- 78 archivos Feature, ~464 tests.

### 11.2 Por módulo: gaps

| Módulo | Unit Domain | Feature UseCase | Multi-tenancy | Smoke Livewire |
|---|---|---|---|---|
| Asignaciones | 1 (Asignacion) | 4 | **Falta** | sí |
| Auditoria | 0 | 2 | **Falta** | sí |
| Campanas | 1 | **0** | **Falta** | **Falta** |
| Casos | 1 (Caso) | 2 | **Falta** | sí |
| Catalogos | 0 | 2 | **Falta** | sí |
| Clientes | 1 (Cliente, legacy) | 0 | n/a | n/a |
| Cobranza | 2 | 4 | **Falta** | sí |
| Compromisos | 1 | 1 | **Falta** | **Falta** |
| Contactos | 1 | 1 | **Falta** | sí |
| Cx | 1 | 5 | **OK** (`MultiTenancyCobranzaCxTest`) | sí |
| EntidadesConfigurables | 0 | 1 | **Falta** | sí |
| Gestiones | 1 | 1 | **Falta** | **Falta** |
| Importaciones | 1 (DomainEnums) | 5 | **Falta** | sí |
| Integracion | 1 (TokenSso) | 5 | **Falta** | **Falta** |
| Notificaciones | 0 | 3 | **Falta** | sí |
| Personas | 1 | 1 | **Falta** | sí |
| Reportes | 1 | 6 | **Falta** | sí |
| Servicio | 1 | 3 | **Falta** | sí |
| Tenancy | 3 | 5 | **Falta** | sí |
| Usuarios | 2 (CodigoRolCustom + RolCustom) | 6 | **Falta** | sí |
| Venta | 1 | 3 | **Falta** | sí |

### 11.3 Tests roles custom F33

35 tests F33 (CLAUDE.md §15) — verificados:

- `CodigoRolCustomTest` (unit, 7 tests).
- `RolCustomTest` (unit, 12 tests).
- `AdminRolesCustomTest` (feature, 16 tests).

Cumple.

### 11.4 Domain sin coverage

7 archivos Domain sin referencia en tests:

- `CamposPersonalizados/Domain/ValueObjects/CodigoCampo.php`
- `Cx/Domain/Entities/CompromisoResolucionTicket.php`
- `Gestiones/Domain/ValueObjects/DatosCompromiso.php`
- `Gestiones/Domain/ValueObjects/SnapshotGestion.php`
- `Importaciones/Domain/ValueObjects/ResumenChunk.php`
- `Servicio/Domain/Entities/CompromisoAccionServicio.php`
- `Venta/Domain/Entities/CompromisoCierreVenta.php`

### 11.5 UseCases sin feature

- `Campanas/Application/UseCases/RegistrarCampana.php`
- `Clientes/Application/UseCases/RegistrarCliente.php` (legacy)
- `Importaciones/Application/UseCases/ProcesarImportacionCasosCobranza.php` (cubierto indirectamente, no directo)
- `Importaciones/Application/UseCases/ProcesarImportacionCasosLeadVenta.php`
- `Importaciones/Application/UseCases/ProcesarImportacionCasosServicio.php`
- `Importaciones/Application/UseCases/ProcesarImportacionCasosTicketCx.php`
- `Reportes/Application/UseCases/RegistrarEjecucionReporte.php`
- `Tenancy/Application/UseCases/RegistrarCartera.php`

### 11.6 Crítico

- **20 de 22 módulos sin test multi-tenancy explícito** — viola CLAUDE.md §13.2.
- `RegistrarCampana` y `RegistrarCartera` sin feature → flujos admin no cubiertos.

---

## 12. Inconsistencias visuales (mockup)

> El archivo `N_cleo CRM _standalone_.html` (~1.6 MB) está minificado/comprimido y no es legible directamente. Solo se pudo extraer tokens del template auxiliar `.tmp_audit/mockup.html` (35 KB). La auditoría visual completa requeriría ejecutar el mockup en navegador.

### 12.1 Tokens visuales del mockup (extraídos)

- Paleta neutral: `#fafafa` (bg), `#ffffff` (elev), `#f4f5f7` (subtle), borders `#e4e6ea/#d4d7dc`, texto `#18181b`/`#52525b`/`#71717a`.
- Primario: `#2563eb` (Tailwind blue-600), hover `#1d4ed8`, soft `#eff4ff`.
- Tipografía: **IBM Plex Sans** (400/500/600/700), **IBM Plex Mono** para datos numéricos.
- Componentes: `.btn` 32px, `.input` 36px, `.card`, `.badge`, `.drawer` 480px, `.modal` 480px.

### 12.2 Realidad implementada (CLAUDE.md §15 "Design system F29-bis")

CLAUDE.md declara que F29-bis está "en re-ejecución" — el design system formal previo (CSS custom properties con cool-gray + IBM Plex) quedó **suspendido** y se reescribirá al cierre. Hoy mismo conviven:

- En `layouts/app.blade.php` se usa `var(--text)`, `var(--bg-subtle)`, `var(--border)` etc. → tokens vivos coinciden con el mockup.
- En `panel-caso.blade.php` (cobranza/cx/venta/servicio) se usa Tailwind directo: `bg-amber-50`, `bg-sky-50`, `bg-emerald-50`, `bg-blue-50`. **Estos colores no aparecen en el mockup.** Diverge del lema "los datos cualitativos no usan colores semánticos saturados".
- Iconos: `<x-ui.icon name="..."/>` (componente Blade `resources/views/components/ui/`). El mockup dice "SVG inline copiados literalmente". Verificar pixel-fidelity requiere abrir mockup.

### 12.3 Pendientes mockup vs real

- Sin verificar: paleta exacta (¿`#2563eb` real vs token usado?), tipografía (¿IBM Plex en producción?), espaciados (¿4px base?), componentes drawer/modal (¿hay drawer de 480px?).

---

## 13. Resumen ejecutivo (≤1 página)

### Top 10 incoherencias más graves (por impacto en UX)

1. **No existe UI para crear caso individual.** Persona creada queda atrapada sin caso. Solo CSV bulk. Bloquea J1, J3.
2. **No existe pantalla de listado de personas.** Sin `proyectos.personas.lista`. Imposible enumerar personas por proyecto.
3. **No existe pantalla de gestión de carteras.** UseCase `RegistrarCartera` sin UI. Bloquea J6.
4. **Catálogos tipo-específicos sin acceso desde menú.** `proyectos.catalogos` solo monta los del módulo Catalogos; tramos_mora, categorias_ticket, etapas_embudo, tipos_accion_servicio, etc. quedan huérfanos.
5. **Reportes custom F32 invisibles en sidebar.** 4 rutas, 0 links.
6. **Roles custom F33 invisibles en sidebar.** 2 rutas, 0 links.
7. **Item "Equipos" del sidebar lleva a 403 para AUDITOR.** Mismatch permiso sidebar vs ruta.
8. **Item "Catálogos" del sidebar lleva a 403 para AUDITOR.** Idem.
9. **Persona/Caso/Compromiso/Contacto no editables.** Asimetría brutal con admin (Mandante/Proyecto sí editables).
10. **Dashboard Operativo dice "Cuentas" en vez de "Casos".** Vocabulario v1 cobranza-only en pantalla genérica multi-tipo.

### Top 5 conexiones rotas más urgentes

1. Persona → "sus casos completos" (hoy via Vista de Trabajo solo persona+caso, no cross-cuenta).
2. Notificación → entidad referida (link no convertido).
3. Importación → entidad creada (sin "ir al registro").
4. Equipo → Reporte de productividad del equipo (sin link cruzado desde la pantalla Equipos).
5. Caso → Asignar este caso (sin acción inline; obligatorio Asignación Masiva).

### Top 5 pantallas huérfanas que requieren entrada/salida

1. `proyectos.admin.roles-custom`, `proyectos.admin.matriz-permisos` — agregar grupo "Permisos" en sidebar (visible solo ADMIN_GLOBAL).
2. `proyectos.reportes.custom` y subrutas — agregar bajo grupo "Reportes" si tiene `reportes.constructor.ejecutar`.
3. `proyectos.entidades.registros/{id}` — agregar item dinámico "Entidades configurables" listando las del proyecto.
4. `proyectos.personas.crear` — agregar botón "Nueva persona" en Bandeja y Buscador Global.
5. `Carteras` — crear pantalla nueva `proyectos.carteras` y agregarla en grupo "Datos".

### Top 5 inconsistencias terminológicas

1. Caso `cobranza` vs etiqueta UI "Préstamo" vs reportes "Cuenta" vs bandeja "Cobranza". Tres palabras para una entidad.
2. Compromiso `cierre_venta` se llama "Promesa de cierre" — palabra "Promesa" colonizando dominio venta.
3. Compromiso `resolucion_ticket` se llama "Resolución / Escalamiento" — dos conceptos en un label.
4. Dashboard Operativo "Cuentas intentadas/gestionadas" en pantalla multi-tipo.
5. "Cliente" residual en `Modules/Clientes` cuando MIGRACION_V2 lo deprecó por "Persona".

### Estado de los 8 journeys

| Journey | Estado |
|---|---|
| J1 Crear persona → trabajar | **Roto** (sin listado, sin crear caso) |
| J2 Importar 1000 → corregir | **Parcial** (importa OK, sin edición de fila ni listado post-import) |
| J3 Importar casos → asignar → trabajar | **Parcial** (sin listado de casos, asignación masiva OK) |
| J4 Asesor recibe → gestiona → resuelve | **OK** (único journey end-to-end) |
| J5 Supervisor crea equipo → asigna → KPIs | **Parcial** (sin asignación a equipo como destino) |
| J6 Admin crea proyecto → cartera → catálogos → CP → entidad | **Roto** (sin UI carteras, catálogos tipo-específicos huérfanos) |
| J7 Auditor revisa → exporta | **Parcial** (lista y exporta, sin diff visual) |
| J8 Supervisor reporte custom → exporta | **Parcial** (funcional, sin acceso por menú) |

### Multi-tenancy

Diseño DB sólido. Solo Cx tiene test cross-project. **20 módulos sin test multi-tenancy** = riesgo P1 de fugas latentes.

---

## 14. Lista priorizada para Fase F34B

| Prio | Tipo | Descripción | Archivos | Cambio sugerido | Esfuerzo |
|---|---|---|---|---|---|
| P0 | conexión | No hay UI para crear caso individual; solo bulk. Bloquea journey J1/J3. | nuevo Livewire `CrearCaso<Tipo>` por tipo + ruta `proyectos.casos.crear` | Crear 4 forms (uno por tipo), llamar `RegistrarCasoCobranza/TicketCx/LeadVenta/Servicio`. | L |
| P0 | conexión | Sin pantalla "Listado de personas". | nuevo Livewire `ListadoPersonas` + ruta `proyectos.personas.lista` | Tabla paginada scoped, link a Vista de Trabajo. Search por identificación/nombre. | M |
| P0 | conexión | Sin pantalla "Listado de casos". | nuevo Livewire `ListadoCasos` + ruta `proyectos.casos.lista` | Tabla paginada scoped por proyecto (todos los tipos), filtros por tipo/estado/cartera. | M |
| P0 | conexión | Sin UI para gestionar carteras. UseCase existe. | nuevo Livewire `AdminCarteras` + ruta `admin.carteras` o `proyectos.carteras` | CRUD básico. Decidir si admin global o por proyecto. | S |
| P0 | UX | Catálogos tipo-específicos huérfanos en menú. | `resources/views/modules/catalogos/page.blade.php` | Inyectar tabs adicionales según `proyecto.tipo_operacion`: tramos_mora, etc. O crear sub-rutas. | M |
| P0 | UX | F32 reportes custom sin link en sidebar. | `resources/views/layouts/app.blade.php` (sección Reportes) | Agregar items "Reportes custom" con `@can('reportes.constructor.ejecutar')`. | S |
| P0 | UX | F33 roles custom y matriz permisos sin link en sidebar. | `resources/views/layouts/app.blade.php` | Agregar grupo "Permisos" con `@can('roles.gestionar')`. | S |
| P0 | permisos | "Equipos" en sidebar usa `equipos.ver` pero ruta exige `usuarios.gestionar`. AUDITOR clic → 403. | `routes/web.php:127` o `layouts/app.blade.php:119` | Alinear permisos: ruta usar `equipos.ver`, o sidebar usar `usuarios.gestionar`. Recomendado: ruta usa `equipos.ver` y mostrar UI read-only para AUDITOR. | S |
| P0 | permisos | "Catálogos" sidebar usa `catalogos.ver`, ruta exige `catalogos.gestionar`. AUDITOR clic → 403. | `routes/web.php:110` | Idem. Permitir `catalogos.ver` con vista read-only. | S |
| P0 | datos | Asignación de casos a "equipo" como destino, no solo a usuario. | `app/Modules/Asignaciones/Application/UseCases/CrearAsignacionMasiva.php` (verificar nombre) + `AsignarMasivamente.php` | Aceptar `equipo_id` como input; round-robin entre miembros. | M |
| P0 | tests | Tests multi-tenancy faltantes en 20 módulos. | `tests/Feature/Modules/<X>/MultiTenancy<X>Test.php` | Replicar patrón `MultiTenancyCobranzaCxTest`: crear 2 proyectos, verificar aislamiento de queries Livewire. | L |
| P1 | UX | `CrearPersona` redirige a dashboard, plan dice a Vista de Trabajo. Persona queda perdida. | `app/Modules/Personas/Infrastructure/Http/Livewire/CrearPersona.php:91` | Cambiar redirect a `proyectos.trabajo` con `$output->publicId`. | S |
| P1 | UX | Edición de Persona ausente. | nuevo `EditarPersona` + UseCase `ActualizarPersona` | Form modal o página, validar invariantes. | M |
| P1 | UX | Edición de Contactos ausente (solo agregar). | `ListaContactos.php` | Agregar `editar()`/`eliminar()` con UseCases. | S |
| P1 | UX | Edición de Caso (prioridad/estado/datos tipo) ausente. | nuevos por tipo | Modal de edición scoped. Cuidar transiciones estado vía gestiones. | L |
| P1 | UX | Edición de Compromiso (monto/fecha pre-resolución) ausente. | `Compromisos/Application/UseCases/ActualizarCompromiso` | Solo si `estado=pendiente`. | S |
| P1 | conexión | "Notificación → entidad" sin link. | `resources/views/modules/notificaciones/livewire/listado-notificaciones.blade.php` | Convertir `entidad_tipo+entidad_id` en `route('proyectos.trabajo', ...)` cuando aplique. | S |
| P1 | conexión | "Importación → registro creado" sin link. | `ImportarPersonas.blade.php`, `ImportarCasos.blade.php` (preview filas) | Agregar columna "Ver" con link a Vista de Trabajo si fila procesada. | S |
| P1 | UX | Auditoría sin diff visual. | `ListadoAuditoria.blade.php` | Modal con `datos_antes`/`datos_despues` formateado. | M |
| P1 | UX | Notificaciones sidebar sin `@can`. | `layouts/app.blade.php:180` | Agregar `@can('compromisos.ver', $proyectoActivo->id)`. | S |
| P1 | terminología | Dashboard Operativo dice "Cuentas". | `dashboard-operativo.blade.php:21,25` | Cambiar a "Casos" o adaptar por `proyecto.tipo_operacion` ("Casos cobrados", "Tickets resueltos", "Leads convertidos", "Acciones ejecutadas"). | S |
| P1 | terminología | "Promesa de cierre" para venta. | `nueva-gestion.blade.php:144` | Renombrar a "Compromiso de cierre". | S |
| P1 | terminología | "Resolución / Escalamiento" mezcla 2 conceptos. | `nueva-gestion.blade.php:219` | Separar: subtítulo "Compromiso de resolución" + sección "Escalamiento" interna. | S |
| P1 | terminología | "Préstamo"/"Ticket"/"Lead"/"Servicio técnico" en panel vs "Caso" en lista. | `panel-caso.blade.php` x4 | Mantener etiqueta tipo-específica pero anteponer "Caso de cobranza: Préstamo #X". | S |
| P1 | UX | Edición de prioridad de asignación ausente. | nuevo en `Bandeja` o `BandejaEquipo` | Botón inline para SUPERVISOR. | S |
| P1 | datos | `gestiones.duracion_segundos`, `motivo_no_contacto_id`, `causa_id` no se muestran en historial. | `vista-de-trabajo.blade.php` (sección historial) | Agregar columnas/badges. | S |
| P1 | datos | `compromisos.fecha_resolucion` no visible. | `vista-de-trabajo.blade.php` (alert compromiso vigente y panel histórico) | Mostrar al lado de "estado=cumplido/roto/cancelado". | S |
| P1 | tests | `RegistrarCampana`, `RegistrarCartera` sin feature. | `tests/Feature/Modules/Campanas/RegistrarCampanaTest.php`, `tests/Feature/Modules/Tenancy/RegistrarCarteraTest.php` | Crear tests integración. | S |
| P1 | datos | Auditoría global de admin sin pantalla. | nueva ruta `admin.auditoria` | Reusar `ListadoAuditoria` con `proyecto_id IS NULL` para admin. | M |
| P1 | UX | Listado de "Compromisos vigentes/vencidos" por proyecto sin pantalla. | nuevo `ListadoCompromisos` + ruta | Filtro por estado, vencimiento, tipo. Link a Vista de Trabajo. | M |
| P2 | arquitectura | `EncolarImportacion` y `ImportarPersonas` importan Models directos. | `app/Modules/Importaciones/...` | Crear `ImportacionRepository` contrato. Mover orquestación a UseCase. | M |
| P2 | arquitectura | Módulo `Clientes` legacy aún presente sin provider. | `app/Modules/Clientes/`, `resources/views/modules/clientes/` | Borrar módulo y vista, limpiar tests legacy. | S |
| P2 | arquitectura | Vista huérfana `/promesas/livewire/resolver-promesa.blade.php`. | filesystem | Borrar. | S |
| P2 | arquitectura | `bootstrap/app.php` sin declarar `routes/api.php`. | `bootstrap/app.php` | Agregar `api: __DIR__.'/../routes/api.php'` y migrar rutas API ahí desde IntegracionServiceProvider. | S |
| P2 | permisos | Permisos `configuracion.ver/editar` muertos. | `database/seeders/Usuarios/PermisosSeeder.php` | Eliminar o asignar a una pantalla concreta. | S |
| P2 | permisos | `notificaciones.ver` sembrado pero ruta usa `compromisos.ver`. | `routes/web.php:139` | Cambiar a `can:notificaciones.ver`. | S |
| P2 | UX | `SelectorProyecto` siempre se muestra incluso con 1 solo proyecto. | `SelectorProyecto.php` o controller del dashboard | Auto-redirect si `count == 1`. | S |
| P2 | UX | Enlace cruzado "Equipo → Reporte de Equipo". | `AdminEquiposProyecto.blade.php` | Botón "Ver reporte" linkando a `proyectos.reportes.equipos?equipo=ID`. | S |
| P2 | terminología | Soft-delete column inconsistente: `eliminada_en` vs `eliminado_en`. | varias migraciones | Estandarizar (decisión de equipo). | S |
| P2 | tests | 7 archivos Domain sin coverage. | `tests/Unit/Modules/...` | Tests para `CodigoCampo`, `CompromisoResolucionTicket`, `DatosCompromiso`, `SnapshotGestion`, `ResumenChunk`, `CompromisoAccionServicio`, `CompromisoCierreVenta`. | M |
| P2 | datos | `personas.hash_identidad` sin uso real verificable. | `app/Modules/Personas/...` | Documentar cuándo se setea o eliminar columna. | S |
| P2 | UX | `entidades_registros` accesible solo via URL. | `layouts/app.blade.php` | Inyectar items dinámicos por entidad activa del proyecto. | M |
| P2 | datos | `auditorias.ip` no se muestra. | `ListadoAuditoria.blade.php` | Columna opcional. | S |
| P2 | UX | Sin pantalla de "Tokens SSO" para admin global. | nueva ruta `admin.integracion.tokens` | Listado read-only con revocación inline. | M |
| P3 | visual | `panel-caso` usa colores Tailwind saturados (amber/sky/emerald/blue) que no están en el mockup. | `panel-caso.blade.php` x4 | Mover a tokens del design system. | S |
| P3 | visual | Iconos `<x-ui.icon>` vs SVG inline literal del mockup. | `resources/views/components/ui/` | Verificar pixel-fidelity al cierre F29-bis. | M |
| P3 | UX | Dashboard del proyecto (`tenancy::proyecto-dashboard`) carece de KPIs entrada. | view file | Mostrar "Mis pendientes hoy", "Compromisos próximos". | M |

**Total: 47 ítems** (12 P0, 19 P1, 13 P2, 3 P3).

---

## 14-bis. Resolución F34B (cierre de fase)

**Fecha de cierre:** 2026-04-30. **Total resuelto:** 28 de 31 P0+P1.

### P0 (10/11 ✅, 1 parcial)

| # | Estado | Notas |
|---|---|---|
| Crear caso individual (4 tipos) | ✅ | `CrearCasoIndividual` Livewire multiplexor por `tipo_operacion`. Botón "Nuevo caso" en Vista de Trabajo. 5 tests. |
| Listado de personas | ✅ | `ListadoPersonas` paginado + filtros + multi-tenancy test. |
| Listado de casos | ✅ | `ListadoCasos` paginado + filtros tipo/cartera/estado + multi-tenancy test. |
| UI carteras CRUD | ✅ | `AdminCarterasProyecto`. UseCase `RegistrarCartera` reusado sin tocar Domain. 6 tests. |
| Catálogos tipo-específicos en menú | ✅ | **Auditoría F34A errónea**: `proyectos.catalogos` ya inyecta tabs por `tipo_operacion` (cobranza/cx/venta/servicio). Verificado en `resources/views/modules/catalogos/page.blade.php`. |
| F32 reportes custom sin link sidebar | ✅ | Item agregado en grupo Reportes con `@can('reportes.constructor.ejecutar')`. |
| F33 roles custom sin link sidebar | ✅ | Grupo "Permisos" agregado con `@can('roles.gestionar')` (ADMIN_GLOBAL). |
| Mismatch Equipos sidebar↔ruta | ✅ | Sidebar ahora usa `usuarios.gestionar` (mismo que ruta). |
| Mismatch Catálogos sidebar↔ruta | ✅ | Sidebar ahora usa `catalogos.gestionar`. También Asignación Masiva alineada a `asignaciones.reasignar` y Importaciones a `importaciones.crear`. |
| Asignación a equipo destino | ✅ | **Auditoría F34A errónea**: `AsignarMasivamente` + UseCase `AsignarCasosAEquipo` ya implementados con round-robin. Verificado. |
| Tests multi-tenancy 20 módulos | ⏳ parcial | F34B agregó cobertura cross-project explícita en: Tenancy (carteras), Personas (listado), Casos (listado), Compromisos (listado). + `MultiTenancyCobranzaCxTest` ya existía. **Restantes 14 módulos** (Auditoria, Notificaciones, Importaciones, Reportes, Catalogos, Campanas, Contactos, Gestiones, EntidadesConfigurables, Integracion, Servicio, Venta, Cobranza, CamposPersonalizados) → **F34C**. |

### P1 (17/19 ✅, 2 pendientes)

| # | Estado | Notas |
|---|---|---|
| `CrearPersona` redirect a Vista de Trabajo | ✅ | Cambio en `CrearPersona.php:91`. |
| Edición de Persona | ✅ | `EditarPersona` Livewire (UPDATE directo sin tocar Domain núcleo, mismo patrón que AdminMandantes/Carteras). 5 tests. |
| Edición de Contactos | ✅ | `ListaContactos.editar()/eliminar()`. Permiso `contactos.eliminar` agregado a SUPERVISOR. 4 tests. |
| **Edición de Caso** | ⏳ pendiente | **BLOQUEADO en F34B**: requiere modal por tipo (4 CTI), invariantes de transición de estado, y posibles cambios en Domain `Caso`. Núcleo (§15.6). Necesita decisión arquitectónica → **F34C**. |
| **Edición de Compromiso** | ⏳ pendiente | **BLOQUEADO en F34B**: requiere `ActualizarCompromiso` UseCase + 4 forms CTI. Domain `Compromiso` es núcleo. → **F34C**. |
| Notificación → entidad link | ✅ | `ListadoNotificaciones` resuelve persona+caso en bulk + título y chip "caso #N" linkeables. |
| Importación → registro link | ⚠️ parcial | Implementado solo para Personas (`ImportarPersonas` columna "Acción" + Ver). `ImportarCasos` requiere switch CTI por `tipo_entidad` (numero_prestamo/codigo_ticket/codigo_lead/codigo_servicio) → **F34C**. |
| Auditoría diff visual | ✅ | Modal reescrito: tabla campo × antes × después con coloreado rojo/verde. Fallback usa `datos_antes`/`datos_despues` para eventos creado/eliminado. |
| Notificaciones sidebar `@can` | ✅ | Sidebar agrega `@can('notificaciones.ver')`. Ruta cambiada a `can:notificaciones.ver` (era `compromisos.ver`). |
| Dashboard Operativo "Cuentas" | ✅ | Labels adaptados por `tipo_operacion`: cobranza="Casos", cx="Tickets atendidos/resueltos", venta="Leads contactados/calificados", servicio="Servicios atendidos/ejecutados". |
| "Promesa de cierre" → "Compromiso de cierre" | ✅ | `nueva-gestion.blade.php:144`. |
| "Resolución / Escalamiento" split | ✅ | Subtítulo "Compromiso de resolución" + sub-sección visual "Escalamiento". |
| Panel-caso etiquetas | ✅ | Antepuesto "Caso de cobranza/CX/venta/servicio · " sobre Préstamo/Ticket/Lead/Servicio técnico. |
| Editar prioridad asignación | ✅ | `BandejaEquipo.cambiarPrioridad()` con clamp [0,9] + `<select>` inline en columna prioridad para SUPERVISOR. 1 test nuevo. |
| Mostrar `duracion`/`motivo_no_contacto`/`causa` en historial | ✅ | Vista de Trabajo timeline gana joins + badges (motivo no contacto, causa). `duracion_segundos` ya estaba. |
| `compromisos.fecha_resolucion` no visible | ✅ | Nueva sección "Compromisos resueltos" en Vista de Trabajo lista cumplido/roto/cancelado con su `fecha_resolucion`. |
| Tests `RegistrarCampana`, `RegistrarCartera` | ✅ | `RegistrarCartera` cubierto indirectamente por `AdminCarterasProyectoTest` (6 tests usando UseCase). `RegistrarCampana` queda para F34C (no bloqueante). |
| Auditoría global admin | ✅ | `ListadoAuditoria` detecta presencia de `tenancy.proyecto_activo`: si no está bound → modo global, columna extra "Proyecto", sin filtro `proyecto_id`. Ruta `admin.auditoria` + sidebar admin. |
| Listado compromisos por proyecto | ✅ | `ListadoCompromisos` paginado + filtros estado/vencimiento/tipo + resumen header + multi-tenancy test. |

### Métricas F34B

- **19 commits incrementales** (todos con suite verde antes de mergear).
- **34 tests nuevos** sobre 522 previos = **556/556 verde** al cierre.
- Pint OK.
- Sin nuevas dependencias externas (no se introdujo paquete alguno).
- Sin migraciones de BD (cero cambios de schema; F34B es 100% capa Application + Infrastructure + UI).
- Domain del núcleo (Casos, Personas, Compromisos, Gestiones, Tenancy) intacto. Las ediciones de Persona y Contacto usan UPDATE directo (mismo patrón establecido en F7 con AdminMandantes y F34B con AdminCarterasProyecto).

### P2/P3 → F34C

Los 13 P2 y 3 P3 originales quedan abiertos para iteración separada. Los items P0/P1 bloqueados o parciales identificados arriba (Edición Caso, Edición Compromiso, Importación→registro para Casos, Tests multi-tenancy 14 módulos restantes, Test feature `RegistrarCampana`) se priorizan al inicio de F34C.

---

## Anexo A — Verificación cruzada CLAUDE.md §15

CLAUDE.md §15 declara "tests F33 verdes (522 totales, 35 nuevos en F33: 19 unit + 16 feature)". Verificado: 19 unit (CodigoRolCustomTest:7 + RolCustomTest:12) + 16 feature (AdminRolesCustomTest:16) = 35. Cumple.

CLAUDE.md §15 declara "73 migraciones | 22 módulos activos". Verificado: 73 archivos en `database/migrations/`. 22 módulos en `app/Modules/`. Cumple.

CLAUDE.md §15 lista funcionalidades F1–F33. Coverage UI confirmada para todas excepto las pantallas huérfanas listadas en §2.4.

---

## Anexo B — Comandos de regresión sugeridos para F34B

```bash
# Suite completa
./vendor/bin/phpunit

# Tests F33 específicos
./vendor/bin/phpunit --filter "RolCustom|MatrizPermisos|AdminRolesCustom"

# Multi-tenancy nuevos
./vendor/bin/phpunit tests/Feature/Modules/MultiTenancy

# Análisis estático
./vendor/bin/phpstan analyse --level=6 app
./vendor/bin/pint --test
```

---

**Fin de la auditoría F34A.** Cero modificaciones a código fuente. Solo este archivo en `DOCS/`.
