# CLAUDE.md

Fuente de verdad del proyecto. Leer completo antes de escribir código. Estas reglas tienen prioridad sobre cualquier convención genérica de Laravel.

---

## 1. Qué es y principios

**Plataforma BPO multi-tipo y multi-tenant.** Cuatro tipos de operación desde el mismo núcleo: `cobranza`, `cx`, `venta`, `servicio`. Cada mandante contrata uno o más proyectos, cada proyecto es de **un solo tipo**.

### Principios no negociables

1. **Aislamiento por proyecto.** Todo dato operativo está scoped al proyecto. Banco A nunca ve datos de Telco B.
2. **Rol por proyecto.** Permisos siempre evaluados en el contexto del proyecto activo. Solo `ADMIN_GLOBAL` es cross-project.
3. **Tipo único por proyecto.** Un proyecto = un tipo. Si un mandante necesita cobranza + CX, son dos proyectos.
4. **Núcleo fijo + CTI.** Entidades centrales fijas. Variabilidad entre tipos → Class Table Inheritance. Variabilidad entre mandantes → campos personalizados tipados (§7).
5. **La gestión es el activo operativo.** Todo el diseño gira en registrar, consultar y reportar gestiones estructuradas.
6. **Una vista, una acción.** El gestor no cambia de pantalla para registrar una gestión. Más de 3 clics = mal diseñado.

### Configurabilidad permitida vs prohibida

**SÍ:** campos personalizados por cartera/tipo-gestión/tipo-compromiso (§7), catálogos propios por proyecto, etiquetas y orden, entidades configurables de datos (§7.7).

**JAMÁS:** módulos dinámicos desde la UI, fórmulas/triggers/rules engine configurables por usuario, layouts editables visualmente, flujos de estado editables por no-desarrollador, texto libre para datos que el negocio filtre o agrupe.

### Stack fijo

- PHP 8.2 + Laravel 12 + MySQL 8 (InnoDB, `utf8mb4_unicode_ci`)
- Livewire 3 + Alpine + Tailwind (Breeze stack Livewire)
- Redis + Laravel Queue (solo para asíncronos reales)
- Laravel Sanctum (tokens API para integración externa)
- Auth: Breeze + roles/permisos propios (sin Spatie)

No se introduce tecnología adicional sin decisión arquitectónica documentada aquí.

---

## 2. Jerarquía y multi-tenancy

```
Mandante
  └─ Proyecto (tipo: cobranza | cx | venta | servicio)
       ├─ Cartera (N)
       ├─ Persona (N)       — aislada al proyecto
       ├─ Caso (N)          — via Persona + Cartera
       │    ├─ Gestion (N)
       │    ├─ Compromiso (0..N)
       │    └─ Asignacion (via Campaña)
       └─ Campaña (N)
```

**Reglas fijas:**
- `proyecto_id` obligatorio en toda tabla operativa, FK real.
- Scope automático vía Eloquent Global Scope + middleware `proyecto.activo`. La URL es la fuente autoritativa: `/proyectos/{id}/...`.
- Persona es por-proyecto: índice único `(proyecto_id, tipo_identificacion_id, identificacion)`.
- Usuarios asignados a N proyectos con rol por proyecto (`usuario_proyecto_rol`). Un mismo usuario puede ser SUPERVISOR en proyecto A y GESTOR en proyecto B.
- `ADMIN_GLOBAL`: rol global, solo administración y reportes consolidados, no opera en bandeja.

---

## 3. Arquitectura

### Módulos (`app/Modules/<Modulo>/`)

**Núcleo:** `Tenancy`, `Personas`, `Contactos`, `Casos`, `Gestiones`, `Compromisos`, `Campañas`, `Asignaciones`, `Usuarios`, `Catalogos`, `CamposPersonalizados`, `EntidadesConfigurables`, `Auditoria`, `Importaciones`, `Reportes`, `Notificaciones`, `Integracion`.

**Especializaciones:** `Cobranza`, `Cx`, `Venta`, `Servicio`.

### CTI para casos y compromisos

```
casos (común)              casos_cobranza (1:1)
├── id, public_id          ├── numero_prestamo
├── proyecto_id            ├── saldo_capital, dias_mora ...
├── cartera_id, persona_id
├── tipo_caso              casos_ticket_cx (1:1)
├── estado_caso_id         ├── categoria_id, prioridad_id, fecha_sla ...
├── fecha_ultima_gestion
├── tiene_compromiso_vigente
└── ...
```

Mismo patrón para `compromisos` → `compromisos_promesa_pago`, `compromisos_resolucion_ticket`, etc.

### Comunicación entre módulos

Solo a través de:
1. Interfaces de servicios (`Domain/Contracts`).
2. Eventos de dominio.

**Prohibido:** que un módulo importe modelos Eloquent de otro módulo. Las especializaciones no se dependen entre sí.

### Rutas

- Operativas: `/proyectos/{proyecto_id}/...`
- Admin global: `/admin/...`
- API integración: `/api/auth/...`, `/api/integracion/...`

---

## 4. Modelo de datos

### Relaciones clave

```
Mandante (1)──(N) Proyecto
Proyecto (1)──(N) Cartera, Campaña, Persona, [N:N via upr] Usuario
Persona  (1)──(N) Contacto, Caso
Cartera  (1)──(N) Caso
Caso     (1)──(1) Caso<Tipo> (CTI)
Caso     (1)──(N) Gestion, Compromiso
Caso     (N)──(N) Campaña via Asignacion
Gestion  (N)──(1) Contacto, Usuario, TipoGestion, Resultado, Canal
Gestion  (1)──(0..1) Compromiso
```

### Reglas de integridad

- FKs reales (InnoDB). Borrado lógico `eliminada_en` en entidades históricas. **Nunca borrado físico de gestiones.**
- PKs: `bigint` autoincremental interno + `ulid` como `public_id` (URLs y APIs, nunca exponer `id`).
- Timestamps: `creada_en`, `actualizada_en`, `eliminada_en`.

### Desnormalizaciones permitidas (solo estas)

En `casos`: `fecha_ultima_gestion`, `resultado_ultima_gestion_id`, `usuario_ultima_gestion_id`, `tiene_compromiso_vigente`. En `asignaciones`: `estado`. Cualquier otra requiere justificación escrita aquí.

### Índices obligatorios

Toda tabla scoped inicia su índice compuesto con `proyecto_id`. Ejemplos críticos:
- `personas`: `(proyecto_id, tipo_identificacion_id, identificacion)` único.
- `gestiones`: `(proyecto_id, caso_id, creada_en)`, `(proyecto_id, usuario_id, creada_en)`.
- `compromisos`: `(proyecto_id, fecha_vencimiento, estado)`.
- `asignaciones`: `(proyecto_id, usuario_id, estado)`, `(campana_id, caso_id)` único.

---

## 5. Gestión (entidad crítica)

Evento de negocio estructurado, no un comentario. **Nunca extraer datos de `notas` por parseo.**

Campos clave: `proyecto_id`, `caso_id`, `persona_id`, `contacto_id`, `canal_id`, `tipo_gestion_id`, `resultado_id`, `subresultado_id`, `motivo_no_contacto_id`, `notas` (libre, no fuente de datos), `creada_en` (inmutable), `usuario_id` (inmutable).

Reglas de dominio:
- `resultado.requiere_compromiso` → crear Compromiso en la **misma transacción**.
- `resultado.requiere_causa` → campo causa obligatorio.
- Validaciones de campos personalizados (§7) en la entidad de dominio, no en Form Request.

---

## 6. Compromiso (entidad abstracta)

| Tipo | Tabla CTI | Campos extra |
|------|-----------|-------------|
| `promesa_pago` | `compromisos_promesa_pago` | monto + fecha pago |
| `resolucion_ticket` | `compromisos_resolucion_ticket` | fecha SLA + acción |
| `cierre_venta` | `compromisos_cierre_venta` | monto + fecha estimada |
| `accion_servicio` | `compromisos_accion_servicio` | tipo acción + fecha |

**Estados:** `pendiente → cumplido | roto | cancelado`. Transiciones solo desde `pendiente`. Vigente: `pendiente AND fecha_vencimiento >= hoy`. `tiene_compromiso_vigente` en casos mantenido por listeners.

---

## 7. Campos personalizados

### Ámbitos (solo estos tres)
1. **Caso × cartera** — datos extra del caso según el mandante.
2. **Gestión × tipo de gestión** — datos capturados al registrar.
3. **Compromiso × tipo de compromiso** — datos extra del compromiso.

No hay campos personalizados en Persona, Contacto, Campaña, Cartera, Usuario, Proyecto, Mandante.

### Tipos (cerrado, 10, no extensible)
`texto_corto`, `texto_largo`, `numero_entero`, `numero_decimal`, `fecha`, `fecha_hora`, `booleano`, `seleccion_unica`, `seleccion_multiple`, `moneda`.

### Almacenamiento
- `campos_personalizados`: definición (proyecto_id, ambito, ambito_id, codigo único, tipo, obligatorio, reglas JSON).
- `valores_campo_personalizado`: una fila por `(campo_id, entidad_id)`. Solo la columna del tipo correspondiente llena.

### Validaciones
En el dominio (entidad `Gestion`/`Caso`/`Compromiso`), no en Form Request ni Livewire. Reglas JSON declarativas:

**Base:** `obligatorio`, `min`, `max`, `longitud_min/max`, `regex`, `depende_de` (dependencias simples).

**Avanzadas (F30):**
- `fecha_minima` / `fecha_maxima` — solo tipos `fecha`/`fecha_hora`. Tokens: `"hoy"`, `"ahora"` (solo `fecha_hora`), offset `"+Nd"` o `"-Nd"`, ISO literal (`"2026-12-31"`). Resuelve via VO `MarcadorTemporal`.
- `auto_fill` — render inicial (no participa en validación). Tokens: `"now"` (→ `fecha_hora`), `"today"` (→ `fecha`/`fecha_hora`), `"usuario_nombre"` / `"usuario_email"` / `"proyecto_codigo"` (→ `texto_corto`/`texto_largo`). Si tipo no es compatible con el token, se ignora silenciosamente.
- `solo_lectura_tras_guardar` (bool) — el campo se renderiza `disabled` cuando ya existe valor persistido. Útil para timestamps auto-rellenados que no deben mutar.

Ninguna regla nueva ejecuta lógica de usuario, abre dependencias dinámicas ni introduce tipos. Cero migraciones; todo vive en `campos_personalizados.reglas` (JSON).

### Qué NO pueden hacer
Modificar flujos de estado, crear entidades, ejecutar lógica al cambiar valor, visibilidad dinámica por rol.

### 7.7 Entidades configurables (datos tipados, no módulos)

Tablas lógicas de datos estructurados que ADMIN_GLOBAL define por proyecto/cartera (ej. "Pólizas", "Vehículos embargables"). Sus columnas reutilizan los 10 tipos de §7. Relación opcional 1:N con Caso o Persona únicamente, nunca entre entidades configurables.

**Prohibido en entidades configurables:** lógica ejecutable, relaciones entre ellas, layouts editables, tipos nuevos, workflow de estados, permisos por registro.

**`entidades.definir` → ADMIN_GLOBAL exclusivo.** GESTOR/SUPERVISOR/AUDITOR solo operan registros, nunca definiciones.

---

## 8. Catálogos

**Globales:** `tipos_identificacion`, `canales`, `paises`, `monedas`, `roles_base`, `permisos_base`.

**Por proyecto (todo lo que varía por mandante/operación):** `resultados`, `subresultados`, `tipos_gestion`, `causas_gestion`, `motivos_no_contacto`, `estados_caso`, `carteras`, `scripts`, más los específicos por tipo: `tramos_mora`/`tipos_pago` (cobranza), `categorias_ticket`/`prioridades_ticket`/`niveles_sla`/`niveles_escalamiento` (cx), `productos_venta`/`etapas_embudo` (venta), `tipos_accion_servicio`/`estados_tecnicos` (servicio).

> Si un catálogo necesita override por proyecto, no era global. Bajarlo a por-proyecto.

---

## 9. UX — Vista de Trabajo

Pantalla única del gestor: identidad de persona + selector de casos (pestañas) + datos clave del tipo + compromiso vigente → formulario nueva gestión (dinámico según resultado) → historial lazy.

**Reglas:** teclado primero, tab ordenado, confirmación por toast (no modal bloqueante), buscador global `Ctrl+K` scoped al proyecto, no pedir datos derivables del sistema.

---

## 10. Scope y permisos

- Global Scope Eloquent agrega `WHERE proyecto_id = {activo}` automáticamente.
- Desactivar solo con `sinScopeProyecto()` en reportes consolidados de ADMIN_GLOBAL.
- Roles base por proyecto: `SUPERVISOR`, `GESTOR`, `AUDITOR`.
- Permisos granulares CRUD: `gestiones.crear`, `campos.editar`, `entidades.definir`, etc. (~70 en total).
- Scope por cartera opcional: `usuario_proyecto_rol_cartera`. Sin filas = rol aplica a todo el proyecto.
- `User::tienePermiso($codigo, $proyectoId, $carteraId)` es la API de verificación.

---

## 11. Estándar de código

- **Pint** (config por defecto) antes de cada commit.
- **PHPStan/Larastan nivel 6 mínimo** (nivel 8 en `Domain/`).
- `declare(strict_types=1)` obligatorio. `readonly` en VOs. `enum` para estados.
- Dominio en **español** (`Caso`, `Gestion`, `Compromiso`). Infraestructura en **inglés** (`Controller`, `Repository`).
- Tablas y columnas: snake_case español. Timestamps: `creada_en`, `actualizada_en`, `eliminada_en`.

### Estructura de módulo

```
app/Modules/<Modulo>/
├── Domain/         Entities/, ValueObjects/, Events/, Exceptions/, Contracts/
├── Application/    UseCases/, DTOs/, Listeners/
└── Infrastructure/ Http/{Controllers,Requests,Livewire}, Persistence/{Models,Repositories}, Providers/
```

Migraciones en `database/migrations/` con prefijo del módulo.

### Patrones obligatorios

- **Repository**: interfaz en `Domain/Contracts`, Eloquent en `Infrastructure/Persistence/Repositories`.
- **Use Case**: clase con `execute(InputDTO): OutputDTO`.
- **Value Objects** para conceptos fuertes: `Identificacion`, `MontoCompromiso`, `DiasMora`, etc.
- **Domain Events** para comunicación entre módulos.
- **DB::transaction()** en todo use case que modifique más de una tabla.
- Eventos síncronos dentro de la transacción. Efectos asíncronos con `afterCommit()`.

### Validaciones en tres capas

1. **Forma**: Form Request / Livewire rules.
2. **Negocio**: dominio (invariantes, transiciones, campos personalizados).
3. **Autorización**: Policies/Gates contra el proyecto activo.

---

## 12. Testing

- **Unit** (muchos, sin DB): Domain puro. ≥ 90% cobertura de dominio.
- **Feature** (críticos): flujos end-to-end contra MySQL de test.
- **Multi-tenancy obligatorio**: cada módulo scoped tiene al menos un test que verifica que no se fuga data entre proyectos.
- No PR que toque Domain/UseCases sin tests nuevos.

---

## 13. Restricciones críticas (NUNCA)

1. No módulos dinámicos desde la UI. Entidades configurables (§7.7) sí, módulos (código) no.
2. No texto libre para datos que el negocio filtre, cuente o agrupe.
3. No duplicar datos entre módulos (solo desnormalizaciones de §4).
4. No lógica de negocio en Livewire, Blade, Controllers ni Form Requests. Solo en Domain.
5. No queries operativas sin scope por proyecto (excepción: ADMIN_GLOBAL consolidados).
6. No importar modelos Eloquent de otro módulo. Solo interfaces y eventos.
7. No query sin índice cubriendo. Revisar `EXPLAIN` antes de mergear.
8. No lógica en `AppServiceProvider`. Cada módulo tiene su propio provider.
9. No dependencia externa sin registrarla aquí con justificación.
10. No `Auth::user()` ni `request()->user()` en Domain ni UseCases. Pasar por parámetro/DTO.
11. No borrado físico de gestiones. Nunca.
12. No más de 3 clics para tareas operativas estándar.
13. No mezclar tipos de operación en un proyecto.
14. No fórmulas, triggers ni rules engine configurables por usuario final.
15. No compartir personas entre proyectos a nivel operativo.
16. No modificar este archivo sin acuerdo explícito.

---

## 14. Workflow de desarrollo

**Antes de escribir código:** identificar módulo + capa, definir contrato si cruza módulos, reutilizar entidades/VOs existentes.

**Orden de implementación:**
1. Dominio (entidades, VOs, eventos, excepciones)
2. Tests unit del dominio
3. Interfaces de repositorio
4. Use Case + DTOs
5. Test de integración (con scope por proyecto)
6. Repo Eloquent
7. Migración con índices (`proyecto_id` primero)
8. Controller / Livewire
9. Form Request
10. Test feature
11. Pint + PHPStan + PHPUnit en verde

**Comandos:** `composer test`, `./vendor/bin/pint`, `./vendor/bin/phpunit`.

---

## 15. Estado actual (2026-04-30)

**70 migraciones | 22 módulos activos | tests F32 verdes (37 nuevos: 13 unit + 24 feature)**

Módulos activos: Tenancy, Usuarios, Casos, Compromisos, Personas, Contactos, Gestiones, Campañas, Asignaciones, CamposPersonalizados, Cobranza, Cx, Venta, Servicio, Reportes, Importaciones, Catalogos, Auditoria, Notificaciones, EntidadesConfigurables, Integracion, Clientes.

**4 proyectos demo** bajo mandante `BPO_DEMO`: COBRANZA_DEMO_2026, SOPORTE_DEMO_2026, VENTA_DEMO_2026, SERVICIO_DEMO_2026.

### Funcionalidades operativas completas

| Área | Estado |
|------|--------|
| Multi-tenant (scope, URL, selector) | ✅ F1 |
| Cobranza (CTI, promesas, catálogos) | ✅ F2 |
| CX (tickets, SLA, escalamiento) | ✅ F3 |
| Venta (leads, embudo, cierre) | ✅ F4 |
| Servicio técnico (acción, técnico) | ✅ F5 |
| Permisos & Admin (campos.definir, importaciones, reportes) | ✅ F6 |
| Admin global (mandantes, proyectos, usuarios) | ✅ F7 |
| Catálogos operativos por proyecto | ✅ F8 |
| Catálogos tipo-específicos por proyecto | ✅ F9 |
| Gestión de usuarios por proyecto (supervisor) | ✅ F10 |
| Importaciones por tipo de operación | ✅ F11 |
| Auditoría exhaustiva (observer + UI + export CSV) | ✅ F12 |
| Notificaciones in-app (compromisos, SLA, asignaciones) | ✅ F13–F14 |
| Equipos por proyecto | ✅ F15 |
| Reportería por equipo | ✅ F16 |
| Asignaciones masivas + reasignación entre equipos | ✅ F17–F18–F20 |
| Bandeja del equipo (supervisor) | ✅ F18 |
| Permisos granulares CRUD × módulo × cartera | ✅ F22 |
| Hardening: gestor/supervisor no definen campos (3 capas) | ✅ F23 |
| Entidades configurables por proyecto/cartera | ✅ F24 |
| Design system inicial (tokens Tailwind + Inter + x-ui.*) | ✅ F25 |
| Refactor visual pantallas operativas y admin | ✅ F26–F27 |
| Capa integración wrapper SSO (Sanctum, token one-time) | ✅ F28 |
| Refactor visual a HTML standalone (intento previo, superado) | ⚠️ F29 (revertido en parte) |
| Refactor literal admin (mandantes, proyectos, usuarios, campos personalizados, entidades configurables, dashboard) — `AdminTablePattern`: page-header + search-row + table-compact-clickable + drawer | ✅ F29-bis |
| Validaciones avanzadas y auto-fill en campos personalizados (`fecha_minima`/`fecha_maxima`, `auto_fill`, `solo_lectura_tras_guardar`) — VO `MarcadorTemporal`, enum `AutoFill`, `ContextoUsuarioProyecto`, `ServicioCamposPersonalizados::valoresAutoRelleno()`. Cero migraciones, todo vive en JSON `reglas` existente. | ✅ F30 |
| Importaciones async + 3 modos (`merge`/`skip_duplicados`/`overwrite`) — `EjecutarImportacionJob` en cola `imports`, lock advisory `GET_LOCK("import:{id}")`, chunks `IMPORTS_BATCH_SIZE` (default 1000), polling Livewire 2s. Enums `ModoImportacion`/`EstadoImportacion`/`EstadoFila`, VO `ResumenChunk`, eventos `ImportacionEncolada`/`Iniciada`/`Terminada`/`Fallada`. Rename estados (`borrador→pendiente`, `validada→preparada`) y contadores (`procesadas/validas/invalidas/omitidas/duplicadas`). | ✅ F31 |
| Constructor de reportes custom + export streaming sin límite — DSL declarativo `DefinicionReporte` (entidad raíz + columnas + filtros + agrupaciones + orden), persistido en `reportes_definiciones`. Whitelist server-side `CatalogoCamposReporte` por entidad. Ejecutor `EjecutarReporte` traduce DSL a Eloquent Query Builder con joins predeclarados y bindings parametrizados (cero SQL injection). Streaming CSV nativo (BOM + fputcsv) y XLSX vía OpenSpout `^4.28`. Permisos nuevos `reportes.constructor.{gestionar,ejecutar,exportar}`. Auditoría `reportes_ejecuciones`. Livewire `ConstructorReporte` (preview live LIMIT 50) + `ListadoReportesCustom`. | ✅ F32 |

### Módulo Integracion (F28)

- `POST /api/auth/sso-handshake` (throttle 10/min) → emite token one-time (SHA-256, TTL `SSO_TOKEN_TTL` seg).
- `GET /integracion/handshake?token=...` → consume token, autentica usuario, redirige a Vista de Trabajo o bandeja.
- `POST /api/auth/logout` (auth:sanctum) → invalida token + sesión.
- `GET /api/integracion/persona` (auth:sanctum) → JSON preview: persona + casos + compromiso vigente + última gestión.
- Middleware `CspFrameAncestors`: agrega `frame-ancestors 'self' <WRAPPER_DOMAIN>` cuando env seteado.
- `SESSION_SAMESITE=none` necesario en producción si el CRM opera dentro de iframe cross-origin (requiere HTTPS).

### Módulo Importaciones — async F31

- **Modos** (`importaciones.modo`):
  - `merge`: si existe el registro (mismo unique compuesto), rellena solo columnas nulas/vacías.
  - `skip_duplicados`: si existe, marca fila `duplicada` con razón "ya existe en proyecto" y continúa el batch. **No** inserta filas literales — el unique compuesto §4.5 lo prohíbe; el comportamiento real es "no detenerse por duplicado".
  - `overwrite`: si existe, actualiza todas las columnas mutables con valores no-null del CSV (vacío del CSV no pisa a null).
- **Estados importación**: `pendiente → preparada → procesando → completada | fallida | cancelada`. Solo `preparada` puede encolarse.
- **Estados fila**: `pendiente → procesada | duplicada | invalida | omitida`.
- **Contadores en `importaciones`**: `total_filas`, `procesadas`, `validas`, `invalidas`, `omitidas`, `duplicadas`. Job incrementa atómicamente tras cada chunk vía `DB::raw('procesadas + N')`.
- **Concurrencia**: lock advisory `GET_LOCK("import:{id}", 0)` por `importacion_id`. Dos imports distintos del mismo proyecto corren en paralelo; el lock impide solo doble procesamiento del mismo batch.
- **Idempotencia**: Job es `ShouldBeUnique` con `uniqueId = importacion_id`. Las filas se procesan filtrando por `estado = pendiente` y se actualizan a estado terminal en la misma iteración; re-ejecutar el Job no duplica inserts.
- **Cancelación**: `CancelarImportacion` marca `estado = cancelada`. Job revisa estado entre chunks y abandona en próxima iteración.
- **Worker**: `php artisan queue:work --queue=imports`. Múltiples workers OK — el lock advisory los serializa por importación.
- **Configuración** (`config/imports.php`): `IMPORTS_BATCH_SIZE`, `IMPORTS_QUEUE`, `IMPORTS_MAX_FILAS`, `IMPORTS_JOB_TIMEOUT`.
- **UPDATE en merge/overwrite**: realizado vía `DB::table()` directo sobre tablas CTI (`personas`, `casos_*`). Respeta §3 (no se importan modelos Eloquent de otros módulos) y §13.4 (lógica de orquestación en Application/UseCase, no en Livewire/Blade). Inserts nuevos siempre delegan al UseCase de origen (`RegistrarPersona`, `RegistrarCasoCobranza`, etc.) preservando invariantes.
- **Restricción §13.16 vigente**: este archivo se modificó como parte del cierre de F31, con acuerdo previo.

### Módulo Reportes — constructor custom F32

- **Entrada por proyecto**: `reportes_definiciones` guarda DSL declarativo (entidad raíz, columnas, filtros, agrupaciones, orden). Único `(proyecto_id, codigo)`. Soft delete `eliminada_en`.
- **Auditoría de ejecuciones**: `reportes_ejecuciones` registra `definicion_id`, `usuario_id`, `formato` (`vista`/`csv`/`xlsx`), `total_filas`, `duracion_ms`.
- **Whitelist server-side** (`Domain/Constructor/Catalogo/CatalogoCamposReporte`): cada entidad raíz declara su lista cerrada de `CampoDisponible` (clave canónica + etiqueta + tipo + SQL ya calificado + joinKey predeclarada). El usuario nunca aporta SQL: solo selecciona claves; el ejecutor traduce. Joins predeclarados como descriptores estructurados `{tabla, alias, col_a, col_b}` — nada de raw SQL en la API pública.
- **Tipos cerrados**: `EntidadRaiz` (`casos`/`gestiones`/`compromisos`/`personas`), `OperadorFiltro` (11 ops), `Agregacion` (5 ops), `TipoCampoReporte` (8 tipos). Validación `aceptaOperador()` chequea compatibilidad tipo↔operador.
- **Reglas Domain** (`DefinicionReporte::validar(CatalogoCamposReporte)`): código/nombre no vacíos, ≥1 columna, todo campo en catálogo, operador compatible con tipo, agregación → ≥1 agrupación, columnas no agregadas deben estar en agrupaciones. Lanza `CampoNoPermitidoEnReporte` / `OperadorIncompatibleConTipo` / `AgregacionRequiereAgrupacion` / `DefinicionReporteInvalida`.
- **Ejecutor** (`Application/UseCases/EjecutarReporte`): aplica `where {tabla}.proyecto_id = X` SIEMPRE primero (§5 §10), luego `whereNull(eliminada_en)` para entidades históricas, luego filtros DSL como bindings parametrizados. Devuelve generator lazy (`cursor()` Eloquent), `O(1)` memoria por fila. Headers como `[{clave,etiqueta}]` aliasados `col_0..col_N`.
- **Campos personalizados §7** (`ServicioCamposPersonalizadosReporte`): inyectados al catálogo por proyecto+ámbito (`caso`/`gestion`/`compromiso`). Solo tipos directamente reportables: textos, números, fechas, booleano. Selección única/múltiple/moneda quedan fuera por requerir joins extra contra `opciones_campo_personalizado`. Joins generados dinámicamente como `LEFT JOIN valores_campo_personalizado vcp_{id} ON vcp_{id}.entidad_id = {tabla}.id AND vcp_{id}.campo_personalizado_id = {id}`.
- **Streaming sin límite** (precedente F19):
  - **CSV** (`StreamerReporteCsv`): `StreamedResponse` + `fopen('php://output', 'w')` + BOM `\xEF\xBB\xBF` + `fputcsv` por fila. Header `X-Accel-Buffering: no` para nginx.
  - **XLSX** (`StreamerReporteXlsx`): OpenSpout `^4.28` `Writer::openToFile('php://output')` + `Row::fromValues()` por fila. Sin límite de 64K filas (era el problema del XLS legacy reportado por cliente). Memoria O(1).
- **Permisos** (granulares por proyecto):
  - `reportes.constructor.gestionar` → SUPERVISOR + ADMIN_GLOBAL: crear/editar/eliminar definiciones.
  - `reportes.constructor.ejecutar` → SUPERVISOR + AUDITOR + ADMIN_GLOBAL: previsualizar y abrir constructor.
  - `reportes.constructor.exportar` → SUPERVISOR + ADMIN_GLOBAL: descargar CSV/XLSX. AUDITOR puede ejecutar lectura pero no exportar.
  - GESTOR no tiene ninguno por defecto (overridable vía F22 si supervisor lo otorga por cartera).
- **Rutas**:
  - `GET /proyectos/{id}/reportes/custom` (listado, `can:reportes.constructor.ejecutar`).
  - `GET /proyectos/{id}/reportes/custom/nuevo` y `.../{def}/editar` (`can:reportes.constructor.gestionar`).
  - `GET /proyectos/{id}/reportes/custom/{def}/exportar?formato=csv|xlsx` (`can:reportes.constructor.exportar`).
- **UI**: Livewire `ConstructorReporte` ofrece selector entidad → catálogo lateral filtrable → +columna/+filtro/+agrupación/+orden → preview live (LIMIT 50) → guardar. `ListadoReportesCustom` lista definiciones del proyecto con acciones inline.
- **Dependencia nueva**: `openspout/openspout ^4.28.5` — librería liviana MIT-licensed, streaming nativo XLSX/ODS/CSV. Justificación: alternativa única para escribir XLSX sin cargar todo el archivo en memoria (PhpSpreadsheet no soporta streaming real). Sin Horizon, sin colas extra.
- **Restricción §13.16 vigente**: este archivo se modificó como parte del cierre de F32, con acuerdo previo.

### Design system — F29-bis (en re-ejecución)

**Fuente de verdad visual única:** `Núcleo CRM (standalone).html` en la raíz del proyecto. Paleta, tipografía, espaciado, radios, sombras, iconos, estructura de markup y nombres/órdenes de clases se copian **literal** desde ese archivo. Si el mockup y este archivo discrepan, manda el mockup.

**Reglas de implementación durante F29-bis (sustituyen al diseño F29 anterior):**

- Permitido y preferente: **Tailwind arbitrary values** (`bg-[#xxxxxx]`, `p-[18px]`, `rounded-[10px]`) y tokens semánticos en `tailwind.config.js` derivados 1:1 del mockup.
- Permitido: clases semánticas en `app.css` solo si reproducen literalmente lo que el mockup expresa; no como abstracción del autor.
- Prohibido durante F29-bis: sustituir colores, espaciados, iconos, fuentes o pesos por "equivalentes" Tailwind/heroicons; reorganizar el markup; descartar clases del mockup por considerarlas redundantes.
- Iconos: SVG inline copiados literalmente desde el mockup. Sin librerías externas (`blade-heroicons`, `blade-lucide`, etc.).
- Tipografía, paleta, layout grid, anatomía de cada componente y tamaños: se determinan en la **auditoría literal del mockup** (Etapa 1 del prompt F29-bis) y se documentan aquí al cierre de la fase.

El bloque "Design system F29" anterior (clases semánticas sobre CSS custom properties, IBM Plex + cool-gray como inferencia) queda **suspendido y no normativo** mientras F29-bis está en curso. Al cierre de F29-bis se reescribe esta sección con los valores efectivamente aplicados.

### Decisiones arquitectónicas vigentes

- **CTI para casos y compromisos** — no STI ni JSON genérico. Tipos limpios e indexados.
- **Personas aisladas por proyecto** — privacidad entre mandantes es condición de negocio.
- **URL como fuente autoritativa del proyecto activo** — evita ambigüedad, facilita auditoría.
- **`DatosCompromiso` como interfaz abstracta en Gestiones** — permite que listeners de cada tipo filtren por `instanceof DatosXxx` sin acoplar el núcleo.
- **`causas_mora`/`estados_cobranza` reutilizan tablas genéricas** (`causas_gestion`, `estados_caso`) — evita `gestiones.causa_id` polimórfico.
- **Entidades configurables ≠ módulos** — datos tipados reutilizando §7, sin fórmulas/triggers/layouts. Módulo = código en `app/Modules/`.
- **Multi-tenancy 1 instancia = 1 BPO** — no hay tabla `tenants` ni `tenant_id`. Aislamiento es por `proyecto_id`.
