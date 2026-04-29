# CLAUDE.md

Este archivo es la fuente de verdad para cualquier asistencia de Claude sobre este proyecto. Antes de escribir código, leer una issue, proponer una solución o modificar un archivo, Claude debe leer este documento de arriba a abajo y respetar TODO lo que aquí se indica. Las reglas aquí tienen prioridad sobre cualquier convención genérica de Laravel o sugerencia de "mejor práctica" externa.

> **Nota de versión**: la versión 1 (2026-04-17) modelaba un CRM de cobranza opinado y prohibía cualquier forma de campos personalizados. La versión 2 (2026-04-17, mismo día) cambia de rumbo conscientemente: el proyecto se convierte en **plataforma BPO multi-tipo y multi-tenant**, y los campos personalizados están permitidos bajo un esquema estricto (§7). Esto no es "Vtiger 2"; es un sistema opinado con **configurabilidad acotada** de datos, nunca de flujos ni código.

---

## 1. Contexto del proyecto

### 1.1. Qué es

Este proyecto es una **plataforma BPO multi-tipo**: opera procesos externos que distintas empresas (mandantes) delegan al call center. Soporta, desde el mismo núcleo, cuatro tipos de operación:

- **Cobranza**: recuperación de cartera vencida (bancos, financieras, cooperativas, telcos).
- **Atención al cliente (CX)**: tickets, soporte, reclamos.
- **Venta (outbound / inbound)**: prospección, cierres, renovaciones.
- **Servicios / gestión técnica**: instalaciones, SLAs, acciones sobre cuentas activas.

Cada **mandante** (banco, financiera, telco, proveedor, empresa) contrata con el BPO uno o más **proyectos** que operan sobre **carteras** de casos. Cada proyecto es **de un solo tipo de operación**. Un mandante puede tener varios proyectos (ej. Banco X con un proyecto de cobranza tarjeta, otro de cobranza préstamos, otro de CX soporte).

### 1.2. Filosofía rectora

**Cuatro principios fuertes, no negociables.** Si un cambio propuesto entra en conflicto con alguno, el cambio está mal.

1. **Aislamiento por proyecto.** Todo dato operativo (personas, casos, gestiones, compromisos, contactos, campañas, asignaciones, catálogos operativos, campos personalizados) está scoped al proyecto. Banco A no ve nada de Telco B, jamás. La vista 360 cross-mandante no existe en el modelo operativo.
2. **Rol por proyecto.** Un usuario puede tener roles distintos en proyectos distintos. Los permisos se evalúan siempre en el contexto del proyecto activo. No hay roles globales salvo `ADMIN_GLOBAL` (administración y reportes consolidados, no operación).
3. **Tipo único por proyecto.** Un proyecto es `cobranza`, `cx`, `venta` o `servicio`. No se mezclan tipos dentro del mismo proyecto. Si un mandante necesita cobranza + CX, son dos proyectos distintos del mismo mandante.
4. **Núcleo fijo con CTI, no modelo genérico.** Las entidades centrales son fijas. La variabilidad entre tipos se resuelve con **Class Table Inheritance** (tablas específicas por tipo), no con "campo genérico + JSON". La variabilidad entre mandantes/carteras se resuelve con **campos personalizados tipados y catálogos propios** bajo las reglas estrictas de §7.

Adicional, pero igualmente innegociable:

- **La gestión es el activo operativo del sistema, no el caso.** Todo el diseño gira alrededor de registrar, consultar y reportar gestiones estructuradas.
- **Una vista, una acción.** El gestor ve caso, historial y registra nueva gestión sin cambiar de pantalla. Más de 3 clics = mal diseñado.

### 1.3. Lecciones explícitas de Vtiger

**SÍ se permite** (configurabilidad sana):
- Campos personalizados **por cartera** y **por tipo de gestión** y **por tipo de compromiso**, con tipos predefinidos y validaciones declarativas (§7).
- Catálogos propios por proyecto cuando corresponda (§6.3).
- Personalización de **etiquetas visibles** y **orden** de catálogos/columnas.
- Activar/desactivar tipos de operación por proyecto.
- Plantillas de scripts (texto) por proyecto/cartera que el gestor lee durante una llamada.
- **Entidades configurables por proyecto/cartera (§7.7, desde Fase 24)**: tablas de datos estructuradas (ej. "Pólizas", "Vehículos", "Bienes embargables") definidas por ADMIN_GLOBAL sobre el mismo esquema de campos personalizados (§7). No son módulos — son datos tipados adicionales con CRUD automático. Ver restricciones estrictas §7.7.

**NO se permite** (jamás):
- **Crear *módulos* nuevos desde la UI** (un módulo es código: controladores, eventos, lógica de dominio; se construye en `app/Modules/` por un desarrollador). Entidades configurables §7.7 **no son módulos** — son registros de datos tipados.
- Fórmulas, triggers, rules engine ejecutable configurable por usuario.
- Layouts/formularios libres editables por usuario final (los formularios de entidades configurables se generan automáticamente de la lista de campos; no son editables visualmente).
- Relaciones **dinámicas entre entidades configurables** (una entidad configurable solo puede relacionarse 1:N con el núcleo: Caso o Persona; nunca con otra entidad configurable).
- Flujos de estado editables por administrador no-desarrollador.
- Texto libre para datos que el negocio quiera filtrar, agrupar o contar.

Si un requerimiento solicita algo del bloque NO, se rechaza y se discute diseño. Esa es la **línea roja**.

### 1.4. Stack técnico fijo

- **PHP 8.2**
- **Laravel 12**
- **MySQL 8** (InnoDB, `utf8mb4_unicode_ci`).
- **Arquitectura monolítica modular** (un solo despliegue, módulos con fronteras fuertes).
- **Frontend**: Livewire 3 + Alpine + Tailwind (scaffolding inicial con Laravel Breeze stack Livewire).
- **Colas**: Redis + Laravel Queue (solo para asíncronos reales: importaciones, exportaciones, agregados analíticos).
- **Auditoría**: tabla genérica alimentada por observers/eventos.
- **Auth**: Laravel Breeze + roles/permisos propios (sin Spatie).

No se introducen tecnologías adicionales (Inertia, Vue, React, microservicios, motor de reglas, CMS, etc.) sin decisión arquitectónica explícita documentada en §18.

---

## 2. Jerarquía multi-tenant

```
Mandante
  └─ Proyecto (tipo: cobranza | cx | venta | servicio)
       ├─ Cartera (N)          (segmentación interna)
       ├─ Persona (N)          (aislada al proyecto)
       ├─ Caso (N)             (via Persona + Cartera)
       │    └─ Gestion (N)
       │    └─ Compromiso (0..N)
       │    └─ Asignacion (via Campaña)
       └─ Campaña (N)
```

- **Mandante**: la empresa externa que contrata al BPO (Banco Pichincha, Claro, Grupo Austral S.A.).
- **Proyecto**: contrato/contexto operativo con un mandante, de un tipo de operación. Un mandante puede tener varios proyectos. Ejemplo: "Banco X — Cobranza Tarjeta", "Banco X — Cobranza Préstamo", "Banco X — CX Soporte".
- **Cartera**: segmentación dentro del proyecto (consumo / micro / vehicular en cobranza; línea A / línea B en venta). Cada cartera puede tener **catálogos propios** y **campos personalizados propios** para sus casos.
- **Caso**: la cuenta de trabajo. En cobranza es un préstamo; en CX un ticket; en venta un lead/oportunidad; en servicio una cuenta activa. Vive en una cartera de un proyecto. Apunta a una Persona del mismo proyecto.
- **Persona**: identidad de la persona física o jurídica **aislada al proyecto**. Una misma persona física real puede existir N veces en la BD (una por proyecto). No hay vista 360 cross-mandante a nivel operativo.

### 2.1. Decisiones fijas de multi-tenancy

- **Persona es por-proyecto.** Columna `proyecto_id` en `personas`; índice único compuesto `(proyecto_id, tipo_identificacion_id, identificacion)`. Banco A nunca ve personas de Telco B, aunque sea la misma cédula física.
- **Dedupe técnico opcional (futuro)**: se puede guardar un `hash_identidad` (hash de identificación + tipo) que permita matching técnico interno para limpieza de datos, pero **nunca visible entre proyectos**. No se implementa en fase 1; se evalúa en fases posteriores.
- **Caso, Gestión, Compromiso, Asignación, Campaña, Cartera son por-proyecto** (columna `proyecto_id` obligatoria, FK real, en cada tabla operativa).
- **Scope automático** vía middleware + Eloquent Global Scope: toda query operativa filtra por `proyecto_id = proyecto_activo`. No se accede a otro proyecto sin cambiar el contexto activo.
- **URL lleva el proyecto**: `/proyectos/{id}/bandeja`, `/proyectos/{id}/casos/{public_id}`, etc. La URL es la fuente autoritativa del proyecto activo. El selector en el header cambia el proyecto y redirige a la ruta correspondiente del nuevo proyecto.
- **Dropdown de proyectos** en el header cuando el usuario tiene acceso a varios. Muestra solo los proyectos donde tiene asignación activa.
- **Usuarios** están asignados a N proyectos con un **rol por proyecto** (tabla `usuario_proyecto_rol`). Un usuario puede ser Supervisor del Proyecto A y Gestor del Proyecto B simultáneamente.
- **Admin global** (`ADMIN_GLOBAL`): rol especial, no ligado a proyecto, ve cross-project solo en administración y reportes consolidados. No opera en la bandeja diaria.

---

## 3. Arquitectura del sistema

### 3.1. Monolito modular: núcleo + especializaciones

El código vive bajo `app/Modules/<NombreModulo>/` siguiendo PSR-4.

**Núcleo (comunes a toda operación BPO)**:

- `Tenancy`: Mandantes, Proyectos, Carteras, proyecto activo, scope global, selector.
- `Personas`: entidad de persona/empresa scoped por proyecto.
- `Contactos`: teléfonos/correos/direcciones de una persona.
- `Casos`: abstracción común de la cuenta de trabajo (tabla `casos`).
- `Gestiones`: núcleo operativo. Una gestión es una interacción estructurada sobre un caso.
- `Compromisos`: abstracción de "lo que el cliente se comprometió a hacer".
- `Campañas`: agrupaciones de casos a trabajar.
- `Asignaciones`: vínculo caso-gestor con estado.
- `Bandeja`: vista operativa sobre Asignaciones y Casos.
- `Usuarios`: equipos, roles, permisos, asignación multi-proyecto.
- `Catalogos`: catálogos globales y por-proyecto.
- `CamposPersonalizados`: definición y valores de campos extra (§7).
- `Auditoria`: bitácora genérica.
- `Importaciones`: carga masiva con validación previa.
- `Reportes`: operativos y analíticos.
- `Configuracion`: parámetros del sistema.

**Especializaciones por tipo de operación** (cada una en su propio módulo):

- `Cobranza`: `CasoCobranza` (saldos, cuotas, mora), `CompromisoPromesaPago`, catálogos específicos (causas de mora, tramos de mora, tipos de pago).
- `Cx`: `CasoTicketCx` (categoría, prioridad, SLA), `CompromisoResolucionTicket`, catálogos específicos.
- `Venta`: `CasoLeadVenta` (producto interés, valor estimado, etapa), `CompromisoCierreVenta`, catálogos específicos (etapas de embudo, razones de rechazo).
- `Servicio`: `CasoServicio` (tipo de servicio, estado técnico), `CompromisoAccionServicio`, catálogos específicos.

Cada especialización implementa los contratos abstractos que el núcleo define para `Caso` y `Compromiso`.

### 3.2. Modelo CTI para casos y compromisos

Decisión fija: **Class Table Inheritance**, no STI ni "tabla genérica + JSON".

```
casos                          casos_cobranza
├── id                         ├── caso_id (PK/FK 1:1)
├── public_id                  ├── numero_prestamo
├── proyecto_id                ├── monto_original
├── cartera_id                 ├── saldo_capital
├── persona_id                 ├── saldo_total
├── tipo_caso                  ├── cuota_mensual
├── estado_caso_id             ├── dias_mora
├── fecha_ingreso              ├── ...
├── prioridad
├── cerrado_en                 casos_ticket_cx
├── fecha_ultima_gestion       ├── caso_id (PK/FK 1:1)
├── resultado_ultima_gestion_id├── categoria_id
├── usuario_ultima_gestion_id  ├── prioridad_id
├── tiene_compromiso_vigente   ├── fecha_sla
└── timestamps                 ├── ...
```

Mismo patrón para compromisos.

**Por qué CTI**: queries específicas por tipo son limpias, los campos conocidos están tipados e indexados, y los campos variables por cartera viven en `CamposPersonalizados` (§7), no mezclados con los conocidos.

### 3.3. Comunicación entre módulos

Los módulos se comunican **solo** a través de:

1. Interfaces de servicios de aplicación expuestas por el módulo dueño (`Domain/Contracts`).
2. Eventos de dominio (el emisor define el evento; los listeners viven en el módulo que reacciona).

**Prohibido**: que un módulo importe directamente un modelo Eloquent de otro módulo. **Prohibido**: que un módulo acceda a tablas que no le pertenecen salvo lectura controlada (read-model) expuesta explícitamente. Las especializaciones (Cobranza, Cx, Venta, Servicio) dependen del núcleo pero **no entre sí**.

### 3.4. Jerarquía visual del menú

El menú se organiza por **flujo operativo**, contextualizado al proyecto activo:

- **Operación** (bandeja, nuevo caso, nuevo contacto) — donde vive el gestor.
- **Supervisión** (reportes, dashboards, seguimiento de equipos).
- **Administración** (catálogos del proyecto, usuarios del proyecto, importaciones, campos personalizados, configuración).
- **Cambiar de proyecto** (dropdown en header; visible solo si el usuario tiene acceso a varios).

Rutas: todas las rutas operativas empiezan con `/proyectos/{proyecto_id}/...`. Las rutas de administración global (solo `ADMIN_GLOBAL`) empiezan con `/admin/...`.

---

## 4. Modelo de datos (reglas inviolables)

### 4.1. Entidades principales y relaciones

```
Mandante (1) ── (N) Proyecto
Proyecto (1) ── (N) Cartera
Proyecto (1) ── (N) Campaña
Proyecto (1) ── (N) Persona         (aislada al proyecto)
Proyecto (N) ── (N) Usuario         via usuario_proyecto_rol

Persona (1) ── (N) Contacto          (scoped por proyecto vía persona)
Persona (1) ── (N) Caso              (scoped por proyecto vía persona)

Cartera (1) ── (N) Caso
Caso (1) ── (1) Caso<Tipo>           (CTI)
Caso (1) ── (N) Gestion
Caso (1) ── (N) Compromiso
Caso (N) ── (N) Campaña              via Asignacion

Gestion (N) ── (1) Contacto utilizado
Gestion (N) ── (1) Usuario, TipoGestion, Resultado, Canal
Gestion (1) ── (0..1) Compromiso
```

### 4.2. Qué NO se duplica

- Datos de la persona dentro del caso (nombres, identificación). El caso referencia a la persona.
- Datos del contacto dentro de la gestión. Se referencia `contacto_id`. Nunca se copia el número.
- Datos de catálogos. Siempre por FK.
- Datos del mandante dentro de proyecto salvo la FK.

### 4.3. Desnormalización controlada (únicas permitidas)

**En `casos`** (agnóstico al tipo):
- `fecha_ultima_gestion`
- `resultado_ultima_gestion_id`
- `usuario_ultima_gestion_id`
- `tiene_compromiso_vigente` (bool)

**En `asignaciones`**: `estado` (pendiente / en_trabajo / cerrada) transicionado por listeners.

Cualquier otra desnormalización requiere justificación escrita y mención explícita en este archivo.

### 4.4. Snapshots en gestión

Si una reportería histórica exige conocer un dato volátil al momento del contacto (saldo, días mora, SLA restante, etapa de venta), se guarda como snapshot tipado en la tabla de la gestión. Solo se agregan cuando el requerimiento está documentado.

### 4.5. Reglas de integridad

- Claves foráneas reales en MySQL (InnoDB). No integridad lógica.
- Borrado lógico (`eliminada_en`) en entidades con valor histórico (Persona, Caso, Gestion, Compromiso, Proyecto, Cartera). **Nunca borrado físico de gestiones**.
- Índices obligatorios desde la migración inicial:
  - `personas.proyecto_id, tipo_identificacion_id, identificacion` (único compuesto).
  - `casos.proyecto_id, cartera_id, persona_id` (compuesto).
  - `casos_cobranza.caso_id` (único 1:1) + `casos_cobranza.numero_prestamo` (único).
  - `gestiones.proyecto_id, caso_id, creada_en` (compuesto).
  - `gestiones.proyecto_id, usuario_id, creada_en` (compuesto).
  - `compromisos.proyecto_id, fecha_vencimiento, estado` (compuesto).
  - `asignaciones.proyecto_id, usuario_id, estado` (compuesto).
  - `asignaciones.campana_id, caso_id` (único).
  - **Toda tabla scoped** inicia su índice compuesto con `proyecto_id`.

### 4.6. Identificadores

- `bigint` auto-incremental como PK interna.
- `ulid` (`public_id`) en entidades visibles externamente (URLs, APIs). No se expone el `id` interno.

---

## 5. Gestión: la entidad crítica

**No es un comentario.** Es un evento de negocio estructurado aplicable a **cualquier tipo de operación**. Regla absoluta: **nunca extraer datos de la nota libre por parseo**. Todo lo que el negocio quiera filtrar, contar o agrupar debe ser un campo estructurado, un catálogo o un campo personalizado tipado.

Atributos comunes (tabla `gestiones`):

- `id`, `public_id`
- `proyecto_id` (scope obligatorio)
- `caso_id`
- `persona_id` (desnormalizado para queries)
- `contacto_id` (nullable)
- `canal_id` (FK catálogo)
- `tipo_gestion_id` (FK catálogo del proyecto)
- `resultado_id` (FK catálogo del proyecto)
- `subresultado_id` (FK catálogo del proyecto, opcional)
- `motivo_no_contacto_id` (FK catálogo del proyecto, nullable)
- `notas` (texto libre, complemento, NO fuente de datos)
- `creada_en` (timestamp automático, inmutable)
- `usuario_id` (inmutable)
- `duracion_segundos` (opcional)
- Snapshots opcionales tipados según el tipo de operación.

Reglas de dominio (aplicables a todos los tipos):

- Un `resultado` con bandera `requiere_compromiso` en su `metadata` obliga a crear el `Compromiso` asociado en la misma transacción.
- Un `resultado` con bandera `requiere_causa` (p.ej. en cobranza: causa de mora; en cx: causa de queja) obliga a enviar el campo correspondiente.
- Los campos personalizados del tipo de gestión (§7.2) son evaluados por el dominio.
- La validación de estas reglas vive en la entidad de dominio `Gestion` (y especializaciones cuando aplique), no en el Form Request.

---

## 6. Compromiso: entidad abstracta

Reemplaza el concepto singular "Promesa" de la v1. Un Compromiso es "lo que el cliente se comprometió a hacer" en cualquier tipo de operación:

- **Cobranza** → `CompromisoPromesaPago`: monto + fecha de pago.
- **CX** → `CompromisoResolucionTicket`: fecha SLA + categoría.
- **Venta** → `CompromisoCierreVenta`: monto + fecha estimada de cierre.
- **Servicio** → `CompromisoAccionServicio`: tipo de acción + fecha objetivo.

Tabla `compromisos` (común):
- `id`, `public_id`, `proyecto_id`, `caso_id`, `gestion_origen_id` (único — un compromiso por gestión).
- `tipo_compromiso` (enum: `promesa_pago | resolucion_ticket | cierre_venta | accion_servicio`).
- `estado` (enum: `pendiente | cumplido | roto | cancelado`).
- `fecha_vencimiento`, `fecha_resolucion` (nullable).
- `usuario_id`, `creada_en`, `actualizada_en`, `eliminada_en`.

Tablas especializadas 1:1:
- `compromisos_promesa_pago`, `compromisos_resolucion_ticket`, `compromisos_cierre_venta`, `compromisos_accion_servicio`.

Reglas:
- Transiciones de estado solo desde `pendiente` (invariante en dominio).
- **Compromiso vigente**: `estado = pendiente AND fecha_vencimiento >= hoy`.
- **Compromiso vencido**: `estado = pendiente AND fecha_vencimiento < hoy`.
- `tiene_compromiso_vigente` en `casos` mantenido por listener a `CompromisoCreado` / `CompromisoResuelto`.

---

## 7. Campos personalizados (reglas estrictas)

### 7.1. Ámbitos permitidos

Un campo personalizado se define en **uno de estos ámbitos**, nunca fuera:

1. **Caso por cartera**: datos adicionales del caso que el mandante requiere. Ej: "Tipo de plan telco", "Operador que vendió", "Código interno de producto".
2. **Gestión por tipo de gestión**: datos capturados al registrar una gestión de cierto tipo. Ej: "Número de referencia del pago", "Código de ticket externo".
3. **Compromiso por tipo de compromiso**: datos extras de un compromiso. Ej: "Cuenta donde se depositará el pago", "Canal de cierre de venta".

**No hay campos personalizados** en Persona, Contacto, Campaña, Asignación, Proyecto, Mandante, Cartera, Usuario ni catálogos.

### 7.2. Tipos permitidos (cerrado, no extensible)

```
texto_corto         VARCHAR(255)
texto_largo         TEXT
numero_entero       BIGINT
numero_decimal      DECIMAL(18,4)
fecha               DATE
fecha_hora          DATETIME
booleano            TINYINT(1)
seleccion_unica     BIGINT (FK a opción de un catálogo cerrado definido en el propio campo)
seleccion_multiple  JSON array de IDs (de un catálogo cerrado)
moneda              DECIMAL(15,2) + código de moneda en la definición
```

**No hay más tipos.** Nada de autorelation, formula, lookup dinámico, calculated field, rich text HTML, file upload configurable.

### 7.3. Estructura de datos

```
campos_personalizados           valores_campo_personalizado
├── id                          ├── id
├── proyecto_id                 ├── campo_personalizado_id
├── ambito (caso|gestion|       ├── entidad_id (caso/gestion/compromiso)
│   compromiso)                 ├── valor_texto_corto      NULL
├── ambito_id (cartera_id |     ├── valor_texto_largo      NULL
│   tipo_gestion_id |           ├── valor_numero_entero    NULL
│   tipo_compromiso_id)         ├── valor_numero_decimal   NULL
├── codigo (único por ámbito)   ├── valor_fecha            NULL
├── etiqueta                    ├── valor_fecha_hora       NULL
├── tipo                        ├── valor_booleano         NULL
├── obligatorio (bool)          ├── valor_opcion_id        NULL (FK)
├── orden                       ├── valor_opciones_ids     NULL (JSON)
├── activo                      ├── valor_moneda_monto     NULL
├── reglas (JSON)               ├── valor_moneda_codigo    NULL
├── catalogo_opciones_id        ├── creado_en
├── creado_en                   └── actualizado_en
└── actualizado_en
```

**Regla de almacenamiento**: una fila por `(campo_personalizado_id, entidad_id)`. Solo la columna tipo-correspondiente está llena; las demás nulas.

### 7.4. Validaciones declarativas

Reglas JSON en `campos_personalizados.reglas`:
- `obligatorio`, `min`, `max`, `longitud_min`, `longitud_max`, `regex`.
- `depende_de` (solo dependencias triviales; nada de DAG complejo).

Se aplican en el **dominio** (entidad `Gestion`, `Caso`, `Compromiso` según ámbito), no en el Form Request ni en Livewire. Una violación lanza excepción de dominio.

### 7.5. Reportería sobre campos personalizados

- Filtrables y exportables; no son `SELECT *`-friendly sin join explícito.
- Para volúmenes grandes, campos "hot" pueden materializarse a columnas físicas de la cartera (migración explícita).

### 7.6. Qué NO pueden hacer los campos personalizados

- Modificar flujos de estado.
- Crear entidades dinámicamente (esto se hace en §7.7, bajo sus propias reglas).
- Referenciar tablas del núcleo no previstas.
- Ejecutar lógica al cambiar valor (no hay triggers configurables).
- Tener visibilidad dinámica por rol.

### 7.7. Entidades configurables por proyecto / cartera (desde Fase 24)

Una **entidad configurable** es una tabla de datos estructurada que el ADMIN_GLOBAL define por proyecto (y opcionalmente restringida a una cartera) para cubrir necesidades específicas del mandante. Ejemplos reales: "Pólizas" en un proyecto de seguros, "Vehículos embargables" en cobranza judicial, "Bienes" en renegociación de deuda.

**Principio rector**: una entidad configurable es **datos tipados, no código**. No existe "módulo dinámico". Solo tabla lógica de registros.

**Reglas estrictas:**

1. **Definición solo por ADMIN_GLOBAL** (`entidades.definir`). SUPERVISOR/GESTOR/AUDITOR nunca pueden crear, modificar ni eliminar **definiciones** (estructura de la tabla). Solo manejan **registros**.
2. **Scope por proyecto (obligatorio) y cartera (opcional)**. Una entidad puede ser visible a todo el proyecto o solo a una cartera.
3. **Sus columnas reutilizan §7** — campos personalizados con los 10 tipos cerrados. Ámbito `entidad_configurable`, `ambito_id = entidades_configurables.id`. **Cero tipos nuevos.**
4. **Relación opcional con el núcleo**, 1:N solamente, y solo hacia `Caso` o `Persona`. Nunca hacia otra entidad configurable. Nunca N:N.
5. **Form se genera automáticamente** de los campos definidos. No hay editor visual de layout. No hay HTML custom.
6. **Valores persisten en `valores_campo_personalizado`** con `entidad_id = entidades_registros.id`. Reutilización completa del servicio/evaluador de §7.
7. **Auditoría automática** (F12) sobre `entidades_registros` y sus valores.

**Prohibido en entidades configurables (línea roja):**

- Lógica ejecutable (fórmulas, scripts, hooks de cambio).
- Relaciones dinámicas entre entidades configurables (si necesitas "Póliza tiene N Beneficiarios" eso es código, no config).
- Layouts visuales editables (el form lo genera el sistema).
- Tipos de campo nuevos (los 10 de §7.2 son cerrados).
- Workflow de estados configurable (un registro solo puede estar activo o eliminado).
- Permisos por registro (solo por entidad+proyecto+cartera).
- Heredar / extender entidades.

**Permisos granulares (§22):**

- `entidades.definir` → ADMIN_GLOBAL. Crea/edita/elimina definiciones.
- `entidades.ver` → lista registros de la entidad.
- `entidades.crear` → crea registros.
- `entidades.editar` → edita registros.
- `entidades.eliminar` → baja lógica de registros.

GESTOR tiene `ver/crear/editar` por defecto (configurable). SUPERVISOR suma `eliminar`. AUDITOR solo `ver`. **Ninguno** tiene `definir`.

**Cuándo usar entidad configurable vs código:**

- ✅ Datos tipados simples ligados a Caso/Persona, con list/edit básico → entidad configurable.
- ❌ Flujo de negocio específico con eventos, validaciones complejas, reglas por tipo → módulo en código.
- ❌ Pipeline de estados con side effects → módulo en código.
- ❌ Entidad que requiera eventos de dominio → módulo en código.

Si el requerimiento pide algo que una entidad configurable no puede cumplir, se discute diseño y, si aplica, se crea un módulo de código en `app/Modules/`.

---

## 8. Catálogos: globales vs por-proyecto

### 8.1. Globales (compartidos por todo el sistema)

Solo lo verdaderamente universal:

- `tipos_identificacion` (CED, RUC, DNI, PAS, NIT, etc.)
- `canales` (teléfono, whatsapp, correo, visita, SMS)
- `paises`
- `monedas`
- `tipos_documento` (adjuntos: PDF, imagen, etc.)
- `estados_base_sistema` (activo, inactivo, bloqueado)
- `roles_base` (plantilla de roles del sistema: ADMIN_GLOBAL, SUPERVISOR, GESTOR, AUDITOR — se instancian por proyecto)
- `tipos_usuario`
- `permisos_base` (catálogo maestro de permisos del sistema)

### 8.2. Por proyecto (catálogos operativos)

Todo lo que cambie según el mandante u operación:

- `resultados`, `subresultados`
- `tipos_gestion`
- `causas_mora` (cobranza), `causas_queja` (cx), `razones_rechazo` (venta)
- `carteras`
- `tramos_mora` (cobranza), `niveles_escalamiento` (cx), `etapas_venta` (venta)
- `motivos_no_contacto`
- `tipos_pago` (cobranza), `tipos_accion_servicio` (servicio)
- `prioridades`
- `sla`
- `colas`
- `scripts` (plantillas de texto que el gestor lee; **no ejecutan lógica**)
- `reglas_operativas` (catálogo de reglas **documentadas**, no un rules engine)
- `estados_caso_operativos`

### 8.3. Regla práctica

> Todo lo que cambie según el mandante o la operación, va por proyecto.

Un catálogo global nunca debería necesitar "overrides por proyecto": si se necesita override, entonces NO era global. Se baja a por-proyecto.

---

## 9. UX operativa (Vista de Trabajo)

La pantalla principal del gestor se llama **Vista de Trabajo**. Es una sola página con tres áreas:

- **Superior**: identidad de la persona (nombres/razón social, identificación), selector de casos de la persona en el proyecto activo (pestañas si tiene varios), datos clave del caso según el tipo, compromiso vigente si existe.
- **Central**: formulario de nueva gestión. Selector de contacto con opción inline "+ Nuevo" (modal mínimo). Formulario dinámico: revela campos según el resultado (monto/fecha del compromiso, causa, campos personalizados del tipo de gestión).
- **Historial**: timeline compacto de gestiones previas del caso. Lazy load. Clic expande.

Principios:

- Teclado primero. Tab ordenado. Atajo de guardado.
- Ningún dato derivable del sistema se pide al usuario.
- Confirmación por toast, no modal bloqueante.
- **Buscador global (Ctrl+K) scoped al proyecto activo**.
- Cambiar de caso de la misma persona refresca centro e historial.
- Cambiar de proyecto (dropdown) sale de la Vista de Trabajo y reinicia contexto.

---

## 10. Reportería

### 10.1. Definiciones operativas fijas

**Comunes a todos los tipos**:
- **Cuenta gestionada (día)**: caso con al menos una gestión hoy cuyo `resultado.es_contacto_efectivo = true`.
- **Cuenta intentada (día)**: caso con al menos una gestión hoy.
- **Última gestión**: máxima `creada_en` del caso, desde `casos.fecha_ultima_gestion`.
- **Compromiso vigente / vencido**: ver §6.
- **Efectividad**: `gestionadas / intentadas`.

**Específicas por tipo**: se definen en el módulo de especialización (Cobranza / Cx / Venta / Servicio), no en el núcleo.

### 10.2. Separación de cargas

- Reportes operativos: consulta directa scoped por proyecto, aprovechando desnormalizados e índices.
- Reportes analíticos: vistas materializadas o tablas resumen por jobs.
- Exportación a Excel/CSV es ciudadano de primera clase.
- Reportes consolidados cross-project solo para `ADMIN_GLOBAL`.

---

## 11. Scope por proyecto y permisos

### 11.1. Scope automático

Todo modelo Eloquent scoped tiene un Global Scope que agrega `where proyecto_id = {proyecto_activo}`. El proyecto activo se resuelve:
1. Del parámetro `{proyecto_id}` de la URL (HTTP).
2. Del DTO explícito (jobs).

No se permite desactivar el scope salvo con trait explícito `PuedeCruzarProyectos::sinScope()`, usado solo en reportes consolidados para `ADMIN_GLOBAL`.

### 11.2. Permisos

- Tabla `usuario_proyecto_rol` asigna rol **por proyecto** (PK compuesta `usuario_id + proyecto_id + rol_id`, o `usuario_id + proyecto_id` con rol como columna si se decide 1 rol por proyecto).
- Roles base instanciables por proyecto: `SUPERVISOR`, `GESTOR`, `AUDITOR`.
- Rol global único: `ADMIN_GLOBAL`.
- Permisos granulares: `gestiones.crear`, `compromisos.resolver`, `campos.editar`, etc.
- `Gate::allows('x.y')` evalúa contra el proyecto activo automáticamente.
- `Gate::forUser($u)->allows('x.y', $proyectoId)` permite check explícito contra un proyecto arbitrario.

---

## 12. Estándar de código

### 12.1. Reglas de oro

- **Laravel Pint** con configuración por defecto.
- **PHPStan / Larastan nivel 6 mínimo** (nivel 8 en `Domain`).
- **PHP 8.2**: `readonly` en Value Objects, `enum` para estados, `declare(strict_types=1);` obligatorio.
- **Tipado fuerte siempre**.
- **Una clase, una responsabilidad**. Casos de uso con método único `execute`.

### 12.2. Convenciones de nombres

- **Dominio en español**: `Caso`, `Gestion`, `Compromiso`, `Persona`, `Mandante`, `Proyecto`.
- **Infraestructura en inglés**: `Controller`, `Request`, `Resource`, `Repository`.
- Tablas: snake_case, plural, en español.
- Columnas: snake_case, en español.
- Timestamps: `creada_en`, `actualizada_en`, `eliminada_en`.

### 12.3. Estructura de carpetas dentro de un módulo

```
app/Modules/<Modulo>/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   ├── Exceptions/
│   └── Contracts/
├── Application/
│   ├── UseCases/
│   ├── DTOs/
│   └── Listeners/
└── Infrastructure/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   ├── Livewire/
    │   └── Resources/
    ├── Persistence/
    │   ├── Models/
    │   └── Repositories/
    ├── Console/
    └── Providers/
```

Migraciones en `database/migrations/` con prefijo del módulo: `2026_04_17_120000_gestiones_create_gestiones_table.php`.

### 12.4. Patrones obligatorios

- **Repository Pattern**: interfaz en `Domain/Contracts`, implementación Eloquent en `Infrastructure/Persistence/Repositories`.
- **Use Case classes**: método único `execute(InputDTO): OutputDTO`.
- **Value Objects** para conceptos fuertes: `Identificacion`, `MontoCompromiso`, `FechaCompromiso`, `DiasMora`, `NumeroPrestamo` (cobranza), `CodigoCartera`.
- **Domain Events** para comunicación entre módulos.
- **Specification Pattern** para filtros complejos.
- **Global Scope por proyecto** en todos los modelos Eloquent scoped.
- **Form Request** para validación de forma.

### 12.5. Validaciones en tres capas

1. **Forma** (Form Request / Livewire rules).
2. **Negocio** (dominio): invariantes, transiciones, obligatoriedad de campos personalizados.
3. **Autorización** (Policies/Gates): quién puede hacer qué **en qué proyecto**.

### 12.6. Transacciones

- Todo caso de uso que modifique más de una tabla se envuelve en `DB::transaction()`.
- Eventos de dominio síncronos dentro de la misma transacción.
- Efectos asíncronos con `afterCommit()`.

### 12.7. Commits y ramas

- Mensajes en español imperativo con prefijo: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`.
- Una rama por tarea. PRs pequeños.

---

## 13. Testing

### 13.1. Pirámide

- **Unitarios** (muchos, rápidos): `Domain` puro, sin DB, sin Laravel. ≥ 90% cobertura de dominio.
- **Integración** (bastantes): casos de uso end-to-end contra MySQL de test (`crm_test`).
- **Feature/HTTP** (pocos, críticos): flujos críticos del gestor.

### 13.2. Reglas

- Ningún PR que toque dominio/casos de uso sin tests nuevos.
- **Tests de multi-tenancy obligatorios**: cada módulo scoped tiene al menos un test que verifica que no se ve data de otro proyecto.
- **Tests de campos personalizados**: cada tipo permitido con al menos un test de validación.
- Factory de Eloquent para setup; tests de dominio puro sin Factory.

---

## 14. Rendimiento (objetivos mínimos)

- Bandeja con 100.000 casos activos en proyecto: ≤ 300 ms p95.
- Registrar gestión (con compromiso, eventos síncronos): ≤ 200 ms p95.
- Buscador global (Ctrl+K) con 500.000 personas en el proyecto: ≤ 150 ms p95.
- Historial de gestiones de un caso: primera página ≤ 100 ms, lazy load.
- Queries siempre cubren índice; scope por proyecto está en el primer índice.

---

## 15. Restricciones críticas (lo que NUNCA se hace)

1. **No se construyen módulos dinámicos desde la UI.** Campos personalizados sí (§7), entidades configurables sí (§7.7, datos tipados), **módulos no** (módulo = código en `app/Modules/` creado por desarrollador).
2. **No se usa texto libre para datos estructurados.**
3. **No se duplican datos entre módulos** salvo §4.3.
4. **No se meten reglas de negocio en Livewire, Blade, controladores ni Form Requests.** Solo en Domain.
5. **No se ejecutan queries operativas sin scope por proyecto.** Única excepción: reportes consolidados `ADMIN_GLOBAL`.
6. **No se acepta un módulo que importe modelos Eloquent de otro módulo.** Solo interfaces y eventos. Especializaciones de tipo no se dependen entre sí.
7. **No se ejecuta una query pesada sin índice cubriendo.** Revisar `EXPLAIN` antes de mergear.
8. **No se mete lógica en `AppServiceProvider` global.** Cada módulo tiene su propio `ServiceProvider`.
9. **No se introduce dependencia externa** sin registrarla aquí con justificación.
10. **No se usa `Auth::user()` ni `request()->user()` en dominio ni casos de uso.** Se recibe por parámetro/DTO. Igual para `proyecto_id`.
11. **No se borra físicamente una gestión.** Nunca.
12. **No se mete UI con más de 3 clics** para tareas operativas estándar.
13. **No se mezclan tipos de operación en un proyecto.** Un proyecto = un tipo.
14. **No se permiten fórmulas, triggers ni rules engine configurables** por usuario final.
15. **No se comparten personas entre proyectos** a nivel operativo. La dedupe técnica interna (futura, hash) no da visibilidad cross-proyecto.
16. **No se modifica este archivo** sin acuerdo explícito.

---

## 16. Workflow de desarrollo

### 16.1. Antes de escribir código

1. Leer este archivo completo.
2. Identificar si el cambio pertenece al **núcleo** o a una **especialización de tipo**.
3. Identificar módulo + capa (Domain/Application/Infrastructure).
4. Si cruza módulos, definir el contrato (interfaz o evento) antes.
5. Reutilizar entidades y VOs existentes.
6. Si se requiere campo personalizado, validar que encaja en §7.

### 16.2. Orden de implementación de una feature

1. Modelo de dominio (entidades, VOs, eventos, excepciones).
2. Tests unitarios del dominio.
3. Interfaces de repositorio.
4. Caso de uso + DTO.
5. Test de integración del caso de uso (con scope por proyecto).
6. Implementación Eloquent del repositorio.
7. Migración con índices (incluyendo `proyecto_id` scoped).
8. Controller / Livewire component.
9. Form Request.
10. Test feature.
11. Pint + PHPStan + PHPUnit en verde.

### 16.3. Comandos estándar

- `composer setup`, `composer dev`, `composer test`, `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`.

---

## 17. Plan de entrega por fases

El sistema se construye en 5 fases. No se salta ni se paralelizan salvo decisión explícita.

### Fase 1 — Refactor del core (multi-tenant + multi-tipo)

- Módulos: `Tenancy`, `Personas` (refactor de Clientes), `Casos` abstracto, `Gestiones` refactor, `Compromisos` abstracto, `Usuarios` multi-proyecto, `Catalogos` global/por-proyecto, `CamposPersonalizados`.
- Migraciones: agregar `proyecto_id` a todo operativo; nuevas tablas `mandantes`, `proyectos`, `carteras`, `casos`, `compromisos`, `campos_personalizados`, `valores_campo_personalizado`, `usuario_proyecto_rol`.
- Scope global, URL con proyecto, dropdown selector.
- UI del refactor sin abrir todos los tipos funcionales todavía.

### Fase 2 — Migrar cobranza al nuevo core

- Módulo `Cobranza` con `CasoCobranza`, `CompromisoPromesaPago`, catálogos específicos.
- Reutiliza lo hecho en v1 (productos → casos_cobranza, promesas → compromisos_promesa_pago).
- Valida el core con operación real.

### Fase 3 — CX

- Módulo `Cx` con `CasoTicketCx`, `CompromisoResolucionTicket`, catálogos (categorías, SLAs).
- Primer proyecto no-cobranza en producción.

### Fase 4 — Venta outbound

- Módulo `Venta` con `CasoLeadVenta`, `CompromisoCierreVenta`, catálogos (etapas, rechazos).

### Fase 5 — Servicio técnico

- Módulo `Servicio` con `CasoServicio`, `CompromisoAccionServicio`.

Cada fase tiene su criterio de "done" (dominio completo + tests verdes + UI operativa + importaciones + reportes operativos básicos).

---

## 18. Glosario del dominio

- **Mandante**: empresa externa que contrata al BPO para operar un proceso.
- **Proyecto**: contrato operativo entre el BPO y un mandante, de un tipo de operación específico. **Unidad principal de scope.**
- **Cartera**: segmentación dentro de un proyecto. Puede tener catálogos y campos personalizados propios.
- **Persona**: identidad de la persona física o jurídica, **aislada al proyecto**. Deduplicada dentro del proyecto por identificación.
- **Caso**: la cuenta de trabajo. En cobranza es un préstamo; en cx un ticket; en venta un lead; en servicio una cuenta activa.
- **Tipo de operación**: `cobranza | cx | venta | servicio`. Un proyecto tiene un solo tipo.
- **Gestión**: evento estructurado que registra una interacción con un caso.
- **Compromiso**: lo que el cliente se comprometió a hacer (promesa de pago, resolución de ticket, cierre de venta, acción de servicio).
- **Campaña**: agrupación de casos a trabajar bajo criterios comunes.
- **Asignación**: vínculo caso-gestor dentro de una campaña.
- **Bandeja**: vista operativa de asignaciones pendientes del gestor en el proyecto activo.
- **Resultado / Subresultado**: clasificación estructurada del desenlace de una gestión.
- **Canal**: medio por el que se realizó la gestión.
- **Snapshot**: valor de un dato volátil capturado en el instante de la gestión para trazabilidad histórica.
- **Campo personalizado**: dato extra definido por cartera / tipo de gestión / tipo de compromiso, bajo el esquema estricto de §7.
- **Proyecto activo**: el proyecto bajo el cual el usuario está operando.
- **`ADMIN_GLOBAL`**: rol cross-project; no opera en bandeja, solo administración y reportes consolidados.

---

## 19. Estado del sistema al pivot (2026-04-17)

### 19.1 Estado al inicio del pivot (v1 original)

Construido bajo la v1 que **se mantiene y refactoriza** (no se tira):

- Migraciones del Lote 1 y Lote 2 (26 tablas) — muchas necesitan `proyecto_id` y varias pasan a ser especializadas (productos → casos_cobranza, promesas → compromisos_promesa_pago).
- Seeders de catálogos — pasan a catálogos globales default o catálogos de proyecto demo según §8.
- Seeders de roles/permisos/admin — roles base globales + instanciación por proyecto vía `usuario_proyecto_rol`.
- Auth (Breeze + Livewire) — se extiende con selector de proyecto y middleware de scope.
- Módulos: Gestiones, Promesas (→ Compromisos), Productos (→ Casos + CasoCobranza), Clientes (→ Personas).
- UI: Buscador global, Bandeja, Vista de Trabajo, Formulario de nueva gestión, Resolver compromiso, Reportes operativos, CRUD cliente → todos refactorizados con scope.
- 59 tests unitarios + integración — muchos necesitan ajuste al nuevo modelo.

### 19.2 Estado al cierre de Fase 1 (2026-04-17)

**Fase 1 completada.** El núcleo multi-tenant, multi-tipo y configurable está operativo. El código v1 quedó archivado en `_archive_v1/` como referencia histórica.

- **Migraciones activas**: 34 en `database/migrations/` (tenancy, usuarios, personas, contactos, casos, compromisos, gestiones, campañas, asignaciones, catálogos globales, campos personalizados).
- **Módulos activos** (providers en `bootstrap/providers.php`): Tenancy, Usuarios, Casos, Compromisos, Personas, Contactos, Gestiones, Campañas, Asignaciones, CamposPersonalizados.
- **Tests**: 124 / 249 assertions en verde (`./vendor/bin/phpunit`).
- **Rutas scoped** (`/proyectos/{id}`): dashboard, bandeja, trabajo, crear persona, contactos por persona. Rutas admin bajo `/admin`.
- **UI scoped**: selector de proyectos, proyecto-dashboard con atajos por permiso, Bandeja (Livewire `asignaciones.bandeja`), Vista de Trabajo abstracta (Livewire `casos.vista-de-trabajo`), Buscador global Ctrl+K (Livewire `personas.buscador-global`).
- **Campos personalizados** listos (§7): migraciones, Domain (TipoCampo, AmbitoCampo, CodigoCampo, EvaluadorReglas), ServicioCamposPersonalizados con `campos()` y `guardarValores()` validando antes de persistir.
- **Desnormalización** operando: listeners en Casos reaccionan a eventos de Gestiones y Compromisos para mantener `fecha_ultima_gestion`, `resultado_ultima_gestion_id`, `usuario_ultima_gestion_id` y `tiene_compromiso_vigente`.
- **Nada específico de cobranza** en el core. El `casos_cobranza` y su UI tipo-específica se construyen en Fase 2.

### 19.3 Estado al cierre de Fase 2 (2026-04-18)

**Fase 2 completada.** Cobranza es la primera especialización del núcleo funcionando end-to-end. El módulo `Promesas` v1 se archivó y fue reemplazado por `Cobranza` con paridad funcional.

- **Migraciones activas**: 38 (se agregaron `tramos_mora`, `tipos_pago`, `casos_cobranza` CTI, `compromisos_promesa_pago` CTI).
- **Módulo `Cobranza` operativo**: Domain (`CasoCobranza`, `CompromisoPromesaPago`, VOs `NumeroPrestamo`, `MontoCobranza`, `DiasMora`, `MontoPromesa`, `FechaPromesa`, `DatosPromesaPago`), Application (`RegistrarCasoCobranza`, `CrearPromesaDesdeGestion` listener, wrappers `MarcarPromesaCumplida|Rota`, `CancelarPromesa`), Infrastructure (models, repos, `CobranzaServiceProvider`).
- **Núcleo ajustado**: `RegistrarGestionInput` y evento `GestionRegistrada` acarrean `?DatosCompromiso` (interfaz abstracta en Gestiones). Validación en `RegistrarGestion`: si `banderas->requiereCompromiso` entonces `datosCompromiso !== null`. El listener de cobranza filtra por `instanceof DatosPromesaPago` + tipo operación cobranza.
- **Catálogos cobranza por proyecto**: `tramos_mora`, `tipos_pago` como tablas propias. `causas_mora` y `estados_cobranza` reutilizan las tablas genéricas `causas_gestion` y `estados_caso` (decisión pragmática — ver §20).
- **UI tipo-específica (Vista de Trabajo)**: slot `cobranza::partials.panel-caso` (saldos, mora, tramo, cuotas, fechas); componente Livewire `casos.nueva-gestion` (dinámico según resultado, con sección de promesa cuando aplica); componente Livewire `cobranza.resolver-promesa` (Cumplida/Rota/Cancelada con modal de fecha); componente Livewire `campos-personalizados.formulario` embebido en el caso para ámbito `caso × cartera`.
- **Campos personalizados demo**: 1 ámbito caso×Consumo (`operador_externo`, texto corto opcional) + 1 ámbito gestion×CONFIRMACION_PAGO (`numero_referencia_bancaria`, texto corto obligatorio). Provider `CamposPersonalizadosServiceProvider` registrado en `bootstrap/providers.php`.
- **Demo end-to-end**: 5 casos cobranza + 6 tramos de mora + 5 tipos de pago + 8 causas de mora en `causas_gestion` + 2 campos personalizados. Asignaciones al gestor demo pobladas.
- **Tests**: 152 / 305 assertions en verde. Nuevos: 8 unit (CasoCobranza), 5 unit (CompromisoPromesaPago), 3 feature (RegistrarCasoCobranza con multi-tenancy), 5 feature (CrearPromesaDesdeGestion + wrappers), 2 feature (NuevaGestion component), 2 feature (ResolverPromesa component), 2 feature (FormularioCamposPersonalizados), más 2 nuevos en RegistrarGestion (PromesaRequerida + flujo con datos).
- **v1 archivado** adicionalmente: módulo `Promesas/` movido a `_archive_v1/Promesas/`. `Gestiones\ValueObjects\DatosPromesa` eliminado (lo reemplaza la interfaz abstracta `DatosCompromiso` + concrete `DatosPromesaPago` en Cobranza).
- **Listo para Fase 3 (CX)**: el patrón (CTI + listener + slot UI + VO `DatosXxx implements DatosCompromiso`) se replica para `CasoTicketCx` y `CompromisoResolucionTicket`.

### 19.4 Estado al cierre de Fase 3 (2026-04-18)

**Fase 3 completada.** CX es la segunda especialización del núcleo. La plataforma queda verificada como multi-tipo (2 de 4 tipos operando en paralelo). Dos proyectos del mismo mandante (`BPO_DEMO`) coexisten sin fugas cross-tenant.

- **Migraciones activas**: 45 (se agregaron 5 en CX: `categorias_ticket`, `prioridades_ticket`, `niveles_sla`, `niveles_escalamiento`, `casos_ticket_cx` CTI; más 1 en 3.B: `compromisos_resolucion_ticket` CTI).
- **Módulo `Cx` operativo**: Domain (`CasoTicketCx`, `CompromisoResolucionTicket`, VOs `CodigoTicket`, `AsuntoTicket`, `AccionComprometida`, `FechaLimiteSla`, `DatosResolucionTicket implements DatosCompromiso`), Application (`RegistrarCasoTicketCx`, `CrearResolucionDesdeGestion` listener, wrappers `MarcarResolucionCumplida|Rota`, `CancelarResolucion`), Infrastructure (5 models + 3 repositorios + `CxServiceProvider`).
- **Proyecto demo dual**: `COBRANZA_DEMO_2026` (cobranza, 5 casos) + `SOPORTE_DEMO_2026` (cx, 4 tickets) bajo el mismo mandante `BPO_DEMO`. Supervisor y gestor demo tienen rol en ambos proyectos.
- **UI tipo-específica**: slot `cx::partials.panel-caso` (categoría, prioridad, SLA, escalamiento); el componente `casos.nueva-gestion` revela el bloque CX (acción, fecha límite, nivel escalamiento) cuando `tipoCaso='ticket_cx' && requiereCompromiso`; componente Livewire `cx.resolver-resolucion` (Cumplida/Rota/Cancelada) análogo a cobranza. Ambos slots coexisten en la misma Vista de Trabajo abstracta.
- **Validación multi-tenancy**: tests de aislamiento (`MultiTenancyCobranzaCxTest`) prueban que el listener de cobranza no crea promesas en proyectos CX, el listener de CX no crea resoluciones en proyectos cobranza, y la misma cédula en dos proyectos se mantiene aislada por el Global Scope.
- **Tests**: 168 / 341 assertions en verde. Nuevos: 4 unit (CasoTicketCx + VOs), 3 feature (RegistrarCasoTicketCx), 4 feature (CrearResolucionDesdeGestion + wrappers), 1 feature (NuevaGestion CX component), 1 feature (ResolverResolucion component), 3 feature (MultiTenancy cross-proyecto).
- **Listo para Fase 4 (Venta outbound)**: el patrón se replica con `CasoLeadVenta` + `CompromisoCierreVenta` (monto estimado + fecha estimada cierre). Catálogos por proyecto: `etapas_embudo`, `razones_rechazo`.

### 19.5 Estado al cierre de Fase 4 (2026-04-18)

**Fase 4 completada.** Venta outbound es la tercera especialización del núcleo. La plataforma corre 3 proyectos en paralelo bajo el mismo mandante (cobranza + CX + venta). El patrón CTI + listener + slot + `DatosXxx implements DatosCompromiso` se replicó sin tocar el núcleo.

- **Migraciones activas**: 49 (4 nuevas en F4: `productos_venta`, `etapas_embudo`, `casos_lead_venta` CTI, `compromisos_cierre_venta` CTI).
- **Módulo `Venta` operativo**: Domain (`CasoLeadVenta`, `CompromisoCierreVenta`, VOs `CodigoLead`, `ValorEstimadoVenta`, `MontoCierre`, `FechaCierreEstimada`, `DatosCierreVenta implements DatosCompromiso`), Application (`RegistrarCasoLeadVenta`, `CrearCierreDesdeGestion` listener, wrappers `MarcarCierreGanado|Perdido`, `CancelarCierre`), Infrastructure (4 models + 2 repos + `VentaServiceProvider`).
- **Proyecto demo triple**: `COBRANZA_DEMO_2026` (5 casos) + `SOPORTE_DEMO_2026` (4 tickets) + `VENTA_DEMO_2026` (4 leads) bajo el mismo mandante `BPO_DEMO`. Gestor demo con rol en los 3 proyectos. 13 asignaciones.
- **UI tipo-específica**: slot `venta::partials.panel-caso` (producto, etapa embudo con probabilidad, valor estimado, origen, cierre estimado); `casos.nueva-gestion` extendido con bloque venta (monto cierre + fecha estimada + etapa); componente Livewire `venta.resolver-cierre` (Ganado/Perdido/Cancelado). Los 3 slots (cobranza, cx, venta) coexisten en la misma Vista de Trabajo abstracta.
- **Razones de rechazo reutilizan `causas_gestion`** con `metadata->tipo='rechazo'`, siguiendo la decisión §20 (2026-04-18).
- **Tests**: 180 / 363 assertions verdes. Nuevos: 5 unit (CasoLeadVenta + VOs), 2 feature (RegistrarCasoLeadVenta), 4 feature (CrearCierreDesdeGestion + wrappers), 1 feature (ResolverCierre component).
- **Listo para Fase 5 (Servicio técnico)**: el patrón se replica con `CasoServicio` + `CompromisoAccionServicio` (tipo acción + fecha programada). Última fase del plan.

### 19.6 Estado al cierre de Fase 5 (2026-04-18) — PLAN COMPLETO

**Fase 5 completada. Plan de 5 fases CERRADO.** Servicio técnico es la cuarta y última especialización del núcleo. La plataforma corre los 4 tipos de operación (cobranza, CX, venta, servicio) en paralelo bajo el mismo mandante. El patrón CTI + listener + slot + `DatosXxx implements DatosCompromiso` se replicó por cuarta vez sin tocar el núcleo.

- **Migraciones activas**: 53 (4 nuevas en F5: `tipos_accion_servicio`, `estados_tecnicos`, `casos_servicio` CTI, `compromisos_accion_servicio` CTI).
- **Módulo `Servicio` operativo**: Domain (`CasoServicio`, `CompromisoAccionServicio`, VOs `CodigoServicio`, `DescripcionAccion`, `FechaProgramada`, `DatosAccionServicio implements DatosCompromiso`), Application (`RegistrarCasoServicio`, `CrearAccionDesdeGestion` listener, wrappers `MarcarAccionEjecutada|Fallida`, `CancelarAccion`), Infrastructure (4 models + 2 repos + `ServicioServiceProvider`).
- **Proyecto demo cuádruple**: `COBRANZA_DEMO_2026` (5 casos) + `SOPORTE_DEMO_2026` (4 tickets) + `VENTA_DEMO_2026` (4 leads) + `SERVICIO_DEMO_2026` (4 casos servicio) bajo el mismo mandante `BPO_DEMO`. Gestor demo con rol en los 4 proyectos. 17 asignaciones totales.
- **UI tipo-específica**: slot `servicio::partials.panel-caso` (código servicio, tipo acción, estado técnico, dirección, técnico, fechas); `casos.nueva-gestion` extendido con bloque servicio (descripción acción + fecha programada + tipo acción + técnico); componente Livewire `servicio.resolver-accion` (Ejecutada/Fallida/Cancelada). Los 4 slots (cobranza, cx, venta, servicio) coexisten en la misma Vista de Trabajo abstracta.
- **4 listeners coexisten** en `GestionRegistrada`: cada uno filtra por `instanceof DatosXxx` + `tipo_operacion` del proyecto. Cero acoplamiento entre especializaciones.
- **Tests**: 191 / 384 assertions verdes. Nuevos en F5: 4 unit (CasoServicio + VOs), 2 feature (RegistrarCasoServicio), 4 feature (CrearAccionDesdeGestion + wrappers), 1 feature (ResolverAccion component).

### 19.7 Cierre del plan de 5 fases

Los 4 tipos de operación funcionan con paridad desde el mismo núcleo multi-tenant. La plataforma BPO multi-tipo está operativa end-to-end.

- **53 migraciones** | **14 módulos activos** | **191 tests / 384 assertions verdes** | **build Vite OK**.
- **4 proyectos demo** del mismo mandante `BPO_DEMO` coexistiendo: cobranza, cx, venta, servicio.
- **17 casos totales**: 5 cobranza + 4 ticket_cx + 4 lead_venta + 4 servicio.
- **17 asignaciones** al gestor demo con rol en los 4 proyectos simultáneamente.
- **4 listeners** (`CrearPromesaDesdeGestion`, `CrearResolucionDesdeGestion`, `CrearCierreDesdeGestion`, `CrearAccionDesdeGestion`) escuchando el mismo evento `GestionRegistrada`, cada uno idempotente por `instanceof`.
- **Núcleo intacto desde F1**: `Casos`, `Compromisos`, `Gestiones`, `Personas`, `Tenancy`, `Usuarios`, `Contactos`, `Campanas`, `Asignaciones`, `CamposPersonalizados` no cambiaron durante F2–F5, salvo `Gestiones` que en F2 ganó la interfaz abstracta `DatosCompromiso` y el slot de UI abstracto.
- **Punto de entrada del gestor**: `/proyectos/{id}/bandeja` → elegir asignación → `/proyectos/{id}/trabajo/{persona}/{caso?}` → Vista de Trabajo con slot tipo-específico automático + formulario Nueva Gestión que revela los campos correctos según `tipo_caso`.

**Próximos pasos posibles (fuera del plan original):**
- Reportes operativos por tipo (tasa de conversión en venta, efectividad en cobranza, SLA en riesgo en cx, tiempos de resolución en servicio).
- Importaciones masivas por tipo (carga de préstamos, tickets, leads, servicios desde Excel).
- Calendario visual para servicio (agenda de técnicos) y venta (embudo kanban).
- Auditoría exhaustiva (tabla `auditoria` con eventos por entidad).
- Alertas de vencimiento (compromisos vigentes próximos a caducar).
- Integraciones externas por mandante (webhooks, APIs).

### 19.8 Estado al cierre de Fase 6 — Permisos & Admin (2026-04-18)

**Fase 6 completada.** Fase fuera del plan original, atendiendo 3 bugs reportados por el usuario:
1. `ADMIN_GLOBAL` no tenía UI para crear campos personalizados.
2. `SUPERVISOR` no tenía cómo acceder a reportes operativos ni a importar/exportar cuentas.
3. `GESTOR` (asesor) podía editar valores de campos personalizados porque el componente Livewire no validaba permisos.

- **Migraciones activas**: 55 (2 nuevas: `importaciones`, `importacion_filas`).
- **Módulos activos**: 16 (+`Reportes`, +`Importaciones` recién registrados en `bootstrap/providers.php` — los módulos ya existían pero solo `Reportes` tenía código huérfano v1; `Importaciones` es completamente nuevo).
- **Permiso nuevo**: `campos.definir` (solo ADMIN_GLOBAL, gestión de definiciones). `campos.editar` ahora cubre solo edición de VALORES (operativo) y se asigna a SUPERVISOR, GESTOR y ADMIN_GLOBAL. `campos.ver` se amplía a AUDITOR.
- **Bug fix en `FormularioCamposPersonalizados`**: auto-detecta `bloqueado` según `campos.editar`, re-valida en `guardar()` con `abort 403`. Defensa en profundidad — nunca confiar solo en el estado del componente.
- **Nueva UI admin** (`/admin/campos-personalizados`): CRUD completo — listar (agrupado por proyecto), crear, editar, desactivar/activar. Ámbitos expuestos: `caso × cartera` y `gestion × tipo_gestion`. Ámbito `compromiso` pendiente (requiere mapeo del enum de tipos).
- **Reportes operativos reescritos de v1 a v2**: ruta `/proyectos/{id}/reportes/operativos` (middleware `can:reportes.operativos`). Dashboard con KPIs scoped al proyecto activo (cuentas intentadas/gestionadas, efectividad, compromisos vigentes/vencidos, ranking de gestores, últimas gestiones). El código v1 huérfano (que consultaba `productos`, `clientes`, `promesas`) fue reemplazado por queries contra `casos`, `personas`, `compromisos`.
- **Módulo `Importaciones` nuevo**: ruta `/proyectos/{id}/importaciones` (middleware `can:importaciones.crear`). Flujo: upload CSV → dry-run (validación fila a fila) → preview → commit (inserta vía `RegistrarPersona` para respetar invariantes). Exportación: `GET /importaciones/personas/exportar` devuelve StreamedResponse CSV. Tablas `importaciones` + `importacion_filas` con payload JSON.
- **Tests**: 209 / 428 assertions verdes. Nuevos en F6: 5 feature (FormularioCamposPersonalizados — escenarios admin/supervisor/gestor/auditor/sin-rol), 6 feature (AdminCamposPersonalizados — crear/duplicado/cross-project/activar/403/200), 5 feature (ReportesOperativos — acceso supervisor/gestor/admin + render + abort), 4 feature (ImportarPersonas — upload CSV, validación, commit, gestor 403, exportación).

**Próximos pasos posibles (continuación de F6):**
- CRUD admin de mandantes, proyectos y usuarios globales (hoy siguen como placeholders).
- Reportes analíticos (reportes.analiticos ya permiso, no hay UI).
- Exportaciones por entidad (casos, gestiones, compromisos) además de personas.
- Importaciones por tipo de operación (préstamos a casos_cobranza, tickets a casos_ticket_cx, etc.).
- UI para gestionar roles y permisos por proyecto (`usuario_proyecto_rol` en tabla).

### 19.9 Estado al cierre de Fase 7 — Administración global completa (2026-04-18)

**Fase 7 completada.** Continuación de F6 atendiendo los placeholders del admin-dashboard y agregando exports + analíticos.

- **Admin CRUD cableado** (`/admin/*`):
  - `admin.mandantes` — listar, crear, editar, desactivar/activar. Usa UseCase `RegistrarMandante` con validación de código único global.
  - `admin.proyectos` — listar (con mandante + tipo + vigencias + carteras), crear, editar, desactivar. El `tipo_operacion` queda **bloqueado tras creación** (invariante §1.2.3). Validación de código único por mandante.
  - `admin.usuarios` — listar usuarios con sus asignaciones por proyecto, crear/editar (email único, password opcional al editar), promover/revocar `ADMIN_GLOBAL` (con protección anti-self-lock y anti-last-admin), asignar/quitar rol por proyecto vía `usuario_proyecto_rol`.
- **Reportes analíticos scoped** en `/proyectos/{id}/reportes/analiticos` (permiso `reportes.analiticos`): distribución por `tipo_caso`, compromisos por tipo×estado, evolución mensual (últimos 6 meses) como barras, efectividad por resultado con %, top-5 días con más gestiones.
- **Exportaciones adicionales**: endpoints HTTP `StreamedResponse` para casos, gestiones, compromisos (complementan el export de personas de F6). Todos bajo `/proyectos/{id}/importaciones/{entidad}/exportar` con permiso `importaciones.crear`. UI integrada en la vista de Importaciones como 4 botones de descarga.
- **Livewire nuevos**: `tenancy.admin-mandantes`, `tenancy.admin-proyectos`, `usuarios.admin-usuarios`, `reportes.dashboard-analitico`.
- **Admin-dashboard** con los 4 tiles operativos (campos personalizados, mandantes, proyectos, usuarios globales); ya no quedan placeholders "futuro".
- **Tests**: 236 / 488 assertions verdes. Nuevos en F7: 6 feature (AdminMandantes), 6 feature (AdminProyectos), 8 feature (AdminUsuarios con protecciones anti-lock), 4 feature (ExportsAdicionales), 3 feature (ReportesAnaliticos).

**Próximos pasos posibles (post-F7):**
- Importaciones por tipo de operación (préstamos, tickets, leads, servicios) replicando el patrón de personas.
- UI de gestión de roles/permisos por proyecto (hoy solo admin global puede asignar; no hay UI para que un supervisor asigne gestores a su proyecto).
- Catálogos operativos por proyecto (CRUD de resultados, tipos_gestion, causas, etc.) desde el admin de proyecto.
- Auditoría exhaustiva (tabla `auditoria` con eventos por entidad).
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).

### 19.10 Estado al cierre de Fase 8 — Catálogos operativos por proyecto (2026-04-18)

**Fase 8 completada.** SUPERVISOR y ADMIN_GLOBAL pueden configurar catálogos operativos desde el browser sin depender de seeders. Es una de las promesas de "configurabilidad acotada" (§1.3 CLAUDE.md) materializada.

- **Ruta nueva** `/proyectos/{id}/catalogos` con middleware `can:catalogos.gestionar`. Página única con 5 tabs Alpine (sin recargas) que monta los 5 Livewire admin.
- **Permiso `catalogos.gestionar`** ahora se asigna también a SUPERVISOR. ADMIN_GLOBAL siempre tuvo acceso vía `Gate::before`.
- **Módulo `Catalogos`** registrado en `bootstrap/providers.php`. Namespace de views `catalogos::`. Provider carga los 5 componentes Livewire.
- **Livewire admin** (5 componentes extienden `AbstractAdminCatalogo`):
  - `catalogos.admin-resultados` (con banderas `es_contacto_efectivo`, `requiere_compromiso`, `requiere_causa`)
  - `catalogos.admin-tipos-gestion`
  - `catalogos.admin-causas-gestion` (con selector `tipo` en metadata: mora/queja/rechazo/servicio/otra)
  - `catalogos.admin-motivos-no-contacto`
  - `catalogos.admin-estados-caso` (con `es_terminal` + protección anti-desactivación si hay casos usando el estado)
- **Base abstracta `AbstractAdminCatalogo`** centraliza la lógica común (form open/close, CRUD, validación de código único por proyecto). Las subclases implementan `tabla()`, `formVacio()`, `reglasValidacion()`, `payloadDesdeForm()`, `formDesdeFila()` — contrato mínimo.
- **Partial Blade reutilizable** `catalogos::livewire._catalogo-simple` con slots (cabecerasExtra/filasExtra/camposExtra) evita duplicar 4× la UI tabular. Solo `admin-resultados` tiene Blade propio (diferencias de columnas significativas con las 3 banderas).
- **v1 archivado adicional**: `app/Modules/Catalogos/Infrastructure/Adapters/ConsultaResultadoEloquent.php` (adapter v1 huérfano que usaba `requiere_promesa`/`requiere_causa_mora` — nomenclatura vieja) movido a `_archive_v1/Catalogos/Adapters/`. El binding válido está en `GestionesServiceProvider` con el adapter v2 correcto.
- **Enlace en `proyecto-dashboard`** con `@can('catalogos.gestionar')`.
- **Tests**: 245 / 507 assertions verdes. Nuevos en F8: 9 feature — crear + rechazar duplicado en Resultados, crear Tipos de Gestión, crear Causas (con metadata), crear Motivo, crear Estado con `es_terminal`, protección anti-desactivación, permisos supervisor/gestor.

**Próximos pasos posibles (post-F8):**
- Importaciones por tipo de operación (préstamos → casos_cobranza, tickets → casos_ticket_cx, leads → casos_lead_venta, servicios → casos_servicio) replicando el patrón de personas.
- UI de gestión de roles/permisos **por proyecto** (hoy solo admin global asigna roles).
- CRUD de catálogos **tipo-específicos** por proyecto (tramos_mora, tipos_pago, categorias_ticket, etapas_embudo, niveles_sla, etc.) — complemento natural de F8.
- Auditoría exhaustiva.
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).

### 19.11 Estado al cierre de Fase 9 — Catálogos tipo-específicos (2026-04-18)

**Fase 9 completada.** Completa F8: cada proyecto expone también sus catálogos tipo-específicos como tabs adicionales en la misma página `/proyectos/{id}/catalogos`. Con F9, el admin puede configurar **todo** lo configurable de un proyecto desde el browser.

- **10 Livewire nuevos** (todos extienden `AbstractAdminCatalogo` de F8):
  - **Cobranza**: `cobranza.admin-tramos-mora` (dias_desde/hasta con validación `gte`), `cobranza.admin-tipos-pago`.
  - **CX**: `cx.admin-categorias-ticket`, `cx.admin-prioridades-ticket` (peso), `cx.admin-niveles-sla` (horas_resolucion), `cx.admin-niveles-escalamiento` (nivel único por proyecto).
  - **Venta**: `venta.admin-productos-venta`, `venta.admin-etapas-embudo` (nivel único + probabilidad_cierre).
  - **Servicio**: `servicio.admin-tipos-accion-servicio` (duracion_estimada_horas), `servicio.admin-estados-tecnicos`.
- **UI condicional por `tipo_operacion`** en `catalogos::page`: los tabs se calculan con `match ($tipoOperacion)` y las secciones se renderizan sólo si aplican. Un proyecto cobranza NO ve tabs CX, etc.
- **Partial `_catalogo-simple` extendido**: muestra resumen de errores del form con `$errors->all()` en el bloque de guardar — los errores custom (p. ej. "nivel duplicado") se visualizan sin necesidad de `@error` dentro de heredocs.
- **Tests**: 258 / 540 assertions verdes. Nuevos en F9: 13 feature — CRUD en cada catálogo, validaciones de rango (`dias_hasta >= dias_desde`), unicidad custom de nivel en escalamiento y etapas_embudo, render condicional de tabs según `tipo_operacion`.
- **Mismos permisos que F8**: `catalogos.gestionar` (SUPERVISOR + ADMIN_GLOBAL). Todos scoped por proyecto activo.

**Próximos pasos posibles (post-F9):**
- Importaciones por tipo de operación (préstamos, tickets, leads, servicios).
- UI de gestión de roles/permisos por proyecto para supervisores.
- Auditoría exhaustiva (tabla `auditoria` con observers por entidad).
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).

### 19.12 Estado al cierre de Fase 10 — Gestión de usuarios por proyecto (2026-04-18)

**Fase 10 completada.** El supervisor ya puede asignar y revocar roles operativos (SUPERVISOR/GESTOR/AUDITOR) dentro de su proyecto, sin depender del admin global. Cierra una brecha que F7 había dejado expuesta solo a `ADMIN_GLOBAL`.

- **Livewire `GestionUsuariosProyecto`** (módulo Usuarios, scoped por proyecto activo): lista usuarios con rol activo, formulario inline de asignación por email, revocación de rol existente. Registrado en `UsuariosServiceProvider`.
- **Ruta `/proyectos/{proyecto_id}/usuarios`** protegida con `can:usuarios.gestionar`. Link en proyecto-dashboard visible solo para quien tenga el permiso.
- **Defensas en profundidad** (se validan en la acción, no solo en la UI):
  - `buscarUsuario` rechaza emails que corresponden a `ADMIN_GLOBAL` — no se pueden "traer" admins al proyecto.
  - `asignar` solo acepta los 3 roles base del proyecto; bloquea explícitamente `ADMIN_GLOBAL`.
  - `quitar` bloquea auto-revocación del propio supervisor (evita dejarse sin acceso) y rechaza operar contra usuarios con rol global.
- **Permiso `usuarios.gestionar` añadido a SUPERVISOR** en `RolPermisoSeeder` (ya existía `usuarios.ver`).
- **Separación clara vs F7**:
  - `/admin/usuarios` (ADMIN_GLOBAL): crea cuentas, (des)activa, promueve ADMIN_GLOBAL, ve cross-proyecto.
  - `/proyectos/{id}/usuarios` (SUPERVISOR): asigna/revoca roles operativos a usuarios existentes dentro de **su** proyecto.
- **Tests**: 268 / 559 assertions verdes. Nuevos en F10: 10 feature — acceso con permiso, 403 sin permiso, búsqueda por email, rechazos a admin global y a emails inexistentes, asignación de gestor, prohibición de asignar admin_global por esta vía, anti-auto-revocación, quitar rol a otro, no tocar usuarios con rol global.

**Próximos pasos posibles (post-F10):**
- Importaciones por tipo de operación (préstamos, tickets, leads, servicios).
- Auditoría exhaustiva (tabla `auditoria` con observers por entidad).
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).
- Equipos por proyecto (tabla existe, UI pendiente).

### 19.13 Estado al cierre de Fase 11 — Importaciones por tipo de operación (2026-04-18)

**Fase 11 completada.** La página `/proyectos/{id}/importaciones` ahora tiene dos tabs: Personas (F6) + Casos (F11). El tab de Casos detecta automáticamente el `tipo_operacion` del proyecto activo y exige/procesa las columnas correctas. Un proyecto solo puede importar casos de su tipo.

- **Migraciones activas**: 56 (+1 que extiende el enum `importaciones.tipo_entidad` a `persona | caso_cobranza | caso_ticket_cx | caso_lead_venta | caso_servicio`).
- **4 UseCases nuevos** (`App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasos{Cobranza|TicketCx|LeadVenta|Servicio}`): cada uno lee filas pendientes, resuelve catálogos por proyecto (carteras, estados_caso, tramos_mora, categorias_ticket, productos_venta, tipos_accion_servicio, etc.), busca la persona por `(proyecto_id, tipo_identificacion_id, identificacion)` y delega al UseCase de dominio `RegistrarCaso{Cobranza|TicketCx|LeadVenta|Servicio}` — dry-run vs commit siguen idénticos al patrón de F6. Idempotencia por código único: si el número_prestamo/codigo_ticket/codigo_lead/codigo_servicio ya existe, la fila queda `omitida` sin romper el batch.
- **Livewire único `ImportarCasos`** (`App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos`): dispatcher condicional con `match ($tipoOperacion)`. Columnas obligatorias se validan antes de crear el `ImportacionModel`. Historial filtrado solo a `tipo_entidad IN (caso_cobranza, caso_ticket_cx, caso_lead_venta, caso_servicio)`.
- **UI**: la vista `importaciones::page` ahora envuelve ambos Livewire en una sola tab-bar Alpine (Personas | Casos del tipo). La sub-vista `importar-casos` muestra las columnas esperadas para el tipo del proyecto activo, previewa las primeras 200 filas, y exhibe el código del caso (numero_prestamo/codigo_ticket/codigo_lead/codigo_servicio) por fila.
- **Permisos reutilizados**: `importaciones.crear` (SUPERVISOR + ADMIN_GLOBAL) para subir CSV y dry-run; `importaciones.procesar` para el commit (defensa en profundidad en `confirmar()`).
- **Persona debe existir antes**: la importación de casos NO crea personas. Si no se encuentra `(tipo_identificacion_codigo, identificacion)` en el proyecto, la fila queda inválida con mensaje claro. Flujo operativo sugerido: importar personas primero (tab Personas de F6), luego casos (tab Casos de F11).
- **Tests**: 272 / 573 assertions verdes. Nuevos en F11: 4 feature (`ImportarCasosTest`) — importación feliz de casos cobranza end-to-end, filas inválidas mezcladas (cartera inexistente + persona inexistente + fila válida), importación de tickets_cx en proyecto dual, rechazo de CSV sin columnas obligatorias.

**Próximos pasos posibles (post-F11):**
- Auditoría exhaustiva (tabla `auditoria` con observers por entidad).
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).
- Equipos por proyecto (tabla existe, UI pendiente).
- Importación de contactos por persona (hoy se crean manualmente en la Vista de Trabajo).

### 19.14 Estado al cierre de Fase 12 — Auditoría exhaustiva (2026-04-18)

**Fase 12 completada.** Se implementó el módulo `Auditoria` con tabla genérica, observer Eloquent único y UI de consulta scoped por proyecto. Cualquier cambio operativo en las entidades críticas queda trazado.

- **Migraciones activas**: 57 (+1: `auditorias` con `public_id` ulid, `proyecto_id` nullable, `usuario_id`, `entidad_tipo`/`entidad_id`, `evento` enum `creado|actualizado|eliminado`, `datos_antes` JSON, `datos_despues` JSON, `cambios` JSON, `ip`, `user_agent`).
- **Módulo `Auditoria` nuevo** (registrado en `bootstrap/providers.php`): `AuditoriaServiceProvider` observa 12 modelos Eloquent: `Gestion`, `Compromiso`, `Persona`, `Caso`, más los 4 CTI de casos (`CasoCobranza`, `CasoTicketCx`, `CasoLeadVenta`, `CasoServicio`) y los 4 CTI de compromisos (`CompromisoPromesaPago`, `CompromisoResolucionTicket`, `CompromisoCierreVenta`, `CompromisoAccionServicio`).
- **`AuditoriaObserver` genérico**: registra `created/updated/deleted` con snapshots. Omite campos sensibles (`password`, `remember_token`, 2FA) y campos ruidosos (`actualizada_en/updated_at`) del diff. En updated calcula el diff exacto (antes/después por campo) y si no hay cambios reales no escribe fila.
- **`proyecto_id`** se resuelve desde el propio modelo (`proyecto_id` del registro auditado), no del contexto HTTP — así los eventos disparados en jobs, comandos o tests quedan scoped correctamente. Nullable para entidades globales.
- **`ip`/`user_agent`** se capturan vía `request()` (nullable en contexto CLI/colas).
- **Livewire `ListadoAuditoria`** en `/proyectos/{id}/auditoria`: paginación 25 por página, filtros por entidad, usuario, evento, rango de fechas, modal de detalle con JSON pretty-print de antes/después/cambios.
- **Permisos**: `auditoria.ver` ya existía (asignado a AUDITOR desde fase base). Se añadió a SUPERVISOR. ADMIN_GLOBAL entra por `Gate::before`. GESTOR no.
- **Enlace** en `proyecto-dashboard` con `@can('auditoria.ver')`.
- **Tests**: 277 / 585 assertions verdes. Nuevos en F12: 5 feature (`AuditoriaTest`) — creación registra, actualización captura cambios campo a campo, scope cross-proyecto, AUDITOR accede ruta vs GESTOR 403, filtro por entidad_tipo.

**Próximos pasos posibles (post-F12):**
- Notificaciones (compromisos próximos a vencer, SLA en riesgo).
- Equipos por proyecto (tabla existe, UI pendiente).
- Importación de contactos por persona.
- Exportación CSV de la auditoría (útil para cumplimiento).

### 19.15 Estado al cierre de Fase 13 — Notificaciones in-app (2026-04-18)

**Fase 13 completada.** Se añadió el módulo `Notificaciones` con tabla única, generador Artisan idempotente para compromisos por vencer y vencidos, listado Livewire por usuario y badge contador en el header.

- **Migraciones activas**: 58 (+1: `notificaciones` con `proyecto_id`, `destinatario_usuario_id`, `tipo` enum, `entidad_tipo`/`entidad_id`, `titulo`/`mensaje`, `metadata` JSON, `leida_en` nullable, único compuesto `(proyecto_id, destinatario_usuario_id, tipo, entidad_tipo, entidad_id)`).
- **Módulo `Notificaciones` nuevo** registrado en `bootstrap/providers.php`. Namespace vistas `notificaciones::`, componentes Livewire `notificaciones.listado-notificaciones` y `notificaciones.badge-notificaciones`.
- **`GeneradorNotificaciones`** (service application-layer): escanea `compromisos` `pendiente` no eliminados y dispara:
  - `compromiso_por_vencer` si `fecha_vencimiento` entre hoy y hoy+`umbralDias`.
  - `compromiso_vencido` si `fecha_vencimiento` < hoy.
  - Destinatario = `compromisos.usuario_id` (gestor que comprometió).
  - **Idempotencia** vía `insertOrIgnore` contra el único compuesto: ejecutar N veces no duplica.
- **Comando Artisan** `php artisan notificaciones:generar [--umbral=3]` registrado en `NotificacionesServiceProvider` (solo en `runningInConsole`). Apto para cron / scheduler.
- **`ListadoNotificaciones`** en `/proyectos/{id}/notificaciones` (middleware `can:compromisos.ver`, lo tienen SUPERVISOR/GESTOR/AUDITOR): filtro "no leídas" vs "todas", acciones de marcar una y marcar todas como leídas, paginación 25.
- **`BadgeNotificaciones`** integrado en `resources/views/livewire/layout/navigation.blade.php` entre el buscador global y el chip del proyecto. Enlaza al listado; muestra el contador (99+ si > 99). Escucha evento `notificaciones-actualizadas` para refrescar.
- **Permisos reutilizados**: no se creó permiso nuevo — `compromisos.ver` es el criterio (si ves compromisos, ves sus alertas). ADMIN_GLOBAL entra por `Gate::before`.
- **Scope por usuario + proyecto**: cada query filtra por `destinatario_usuario_id = auth()->id()` y `proyecto_id = proyecto_activo`. Un gestor con rol en 2 proyectos no ve las notificaciones del otro desde el proyecto activo — solo las del contexto actual.
- **Tests**: 283 / 596 assertions verdes. Nuevos en F13: 6 feature (`NotificacionesTest`) — generador crea por-vencer y vencidos, ignora lejanos; idempotencia (3 ejecuciones → 1 fila); listado muestra solo del usuario+proyecto; marcar leída actualiza `leida_en`; scope cross-proyecto (2 proyectos, mismo gestor, proyecto activo B → 0 notificaciones); ruta 403 sin `compromisos.ver`.

**Próximos pasos posibles (post-F13):**
- SLA en riesgo para CX (otro tipo de notificación del mismo generador).
- Scheduler semanal en `routes/console.php` para correr el generador diario.
- Canales adicionales (email/slack) conectados al mismo flujo.
- Equipos por proyecto (tabla existe, UI pendiente).
- Importación de contactos por persona.
- Exportación CSV de la auditoría.

### 19.16 Estado al cierre de Fase 14 — SLA CX + scheduler (2026-04-18)

**Fase 14 completada.** Continuación directa de F13: se añadió el tercer tipo de alerta (`sla_en_riesgo`) para compromisos de resolución de tickets CX, y se registraron las corridas del generador en el scheduler de Laravel.

- **`GeneradorNotificaciones::ejecutar()`** ahora recibe `umbralHorasSla` (default 8) además del `umbralDias`. Método privado `generarSlaEnRiesgo()` hace `JOIN compromisos + compromisos_resolucion_ticket` donde `tipo_compromiso='resolucion_ticket'`, `estado='pendiente'`, y `rt.fecha_limite_sla BETWEEN ahora AND ahora+umbralHorasSla`. La idempotencia la mantiene el mismo único compuesto de F13.
- **Comando extendido**: `php artisan notificaciones:generar [--umbral=3] [--horas-sla=8]`. Ambos parámetros independientes.
- **Scheduler en `routes/console.php`**:
  - `notificaciones-compromisos-diario` → `notificaciones:generar --umbral=3 --horas-sla=8` todos los días a las 08:00 (`withoutOverlapping`).
  - `notificaciones-sla-horario` → `notificaciones:generar --umbral=0 --horas-sla=4` cada hora al minuto 5, entre 07:00 y 20:00 (ventana operativa).
- **Coexistencia de tipos**: un compromiso CX puede recibir `compromiso_por_vencer` (por su `fecha_vencimiento` en días) y también `sla_en_riesgo` (por su `fecha_limite_sla` en horas). Son alertas complementarias con destinatario y entidad iguales pero `tipo` distinto, por lo que el único compuesto permite ambas.
- **Tests**: 287 / 604 assertions verdes. Nuevos en F14: 4 feature (`GeneradorSlaTest`) — SLA dentro de umbral se genera y fuera no, comando acepta `--horas-sla`, idempotencia del SLA, scheduler:list contiene las entradas esperadas.

**Próximos pasos posibles (post-F14):**
- Canales adicionales (email/slack) atados al mismo flujo.
- Equipos por proyecto (tabla existe, UI pendiente).
- Importación de contactos por persona.
- Exportación CSV de la auditoría.
- Ampliar destinatarios (supervisor además del gestor, si la alerta escala).

### 19.17 Estado al cierre de Fase 15 — Equipos por proyecto (2026-04-18)

**Fase 15 completada.** La tabla `equipos` existía desde F1 pero no tenía UI; F15 agrega la tabla pivote `equipo_usuario`, el CRUD y la gestión de miembros.

- **Migraciones activas**: 59 (+1: `equipo_usuario` con `(equipo_id, usuario_id)` único, `proyecto_id` redundante para scope directo y `activo`).
- **Livewire `AdminEquiposProyecto`** en `App\Modules\Usuarios\Infrastructure\Http\Livewire`: CRUD de equipos (crear, editar, activar/desactivar con código único por proyecto validado en el servicio) + panel embebido de miembros (buscar por email, agregar, quitar).
- **Ruta `/proyectos/{proyecto_id}/equipos`** con `can:usuarios.gestionar` (el mismo permiso que F10; no se crea permiso nuevo).
- **Defensas en profundidad**:
  - Al buscar email para agregar, se rechaza si el usuario tiene rol `ADMIN_GLOBAL`.
  - También se rechaza si el usuario no tiene rol activo en el proyecto (un equipo solo agrupa miembros que ya participan en el proyecto).
  - Código del equipo: regex `[A-Z0-9_]+`, único por proyecto.
- **Link** añadido en `proyecto-dashboard` junto al tile de Usuarios del proyecto (mismo @can).
- **Tests**: 295 / 624 assertions verdes. Nuevos en F15: 8 feature (`AdminEquiposProyectoTest`) — supervisor entra / gestor 403, crear equipo, código duplicado rechazado, agregar y quitar miembro, bloqueo de admin global, bloqueo de usuario sin rol en proyecto, desactivar/activar.

**Próximos pasos posibles (post-F15):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Exportación CSV de la auditoría (para cumplimiento).
- Reportería por equipo (ranking de gestores agrupados por equipo).
- Asignaciones masivas dirigidas a un equipo completo.

### 19.18 Estado al cierre de Fase 16 — Reportería por equipo (2026-04-18)

**Fase 16 completada.** Extiende los reportes operativos (F7) con agregaciones por equipo usando la tabla pivote de F15.

- **Livewire `ReporteEquipos`** en `App\Modules\Reportes\Infrastructure\Http\Livewire`: lista equipos activos con KPIs agregados (miembros, gestiones, cuentas intentadas, cuentas gestionadas, efectividad %, compromisos vigentes, compromisos vencidos). Selector de rango (hoy/ayer/semana/mes) con persistencia en URL vía `#[Url]`.
- **Breakdown por miembro** al expandir un equipo: cada miembro con su total de gestiones, intentadas, gestionadas y efectividad en el rango.
- **Ruta `/proyectos/{id}/reportes/equipos`** con `can:reportes.operativos` (mismo permiso que el dashboard operativo).
- **Tile** en `proyecto-dashboard` junto a "Reportes operativos".
- **Scope doble**: los compromisos y gestiones se filtran por `proyecto_activo` Y por `usuario_id IN (miembros del equipo)`. Un gestor con rol en 2 proyectos solo cuenta para el equipo del proyecto activo.
- **Tests**: 301 / 638 assertions verdes. Nuevos en F16: 6 feature (`ReporteEquiposTest`) — supervisor 200 / gestor 403, agrega gestiones de miembros (y ignora gestor fuera del equipo), equipo sin miembros muestra ceros, `expandir()` devuelve detalle por miembro, no fuga gestiones de otro proyecto.

**Próximos pasos posibles (post-F16):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Exportación CSV de la auditoría.
- Asignaciones masivas dirigidas a un equipo completo.
- Filtro por equipo en bandeja del supervisor (ver solo asignaciones de su equipo).

### 19.19 Estado al cierre de Fase 17 — Asignaciones masivas a un equipo (2026-04-18)

**Fase 17 completada.** Distribuye casos sin asignación previa en una campaña a los miembros de un equipo usando round-robin. Cierra el loop F15+F16: ya no basta con ver KPIs por equipo — ahora se les asigna trabajo.

- **UseCase `AsignarCasosAEquipo`** (`App\Modules\Asignaciones\Application\UseCases`): toma `proyectoId`, `campanaId`, `equipoId`, `limite` (0 = todos). Lanza excepción si el equipo no tiene miembros activos o si la campaña no pertenece al proyecto. Idempotente vía `insertOrIgnore` contra el único `(campana_id, caso_id)` existente desde F1.
- **DTO `AsignacionMasivaResultado`**: cuenta de `asignadas`, `omitidas` (ya tenían asignación en esa campaña) y `distribucion` (usuarioId → cantidad).
- **Livewire `AsignarMasivamente`** con selectores de campaña y equipo, contador en vivo de casos sin asignar y miembros activos, preview del resultado tras confirmar, tabla de distribución por usuario.
- **Ruta `/proyectos/{id}/asignaciones/masiva`** con `can:asignaciones.reasignar` (SUPERVISOR + ADMIN_GLOBAL; GESTOR no).
- **Tile** en `proyecto-dashboard` sección Supervisión.
- **Scope estricto**: todo filtra por `proyecto_activo`. Una campaña de otro proyecto lanza excepción de dominio antes de insertar nada.
- **Round-robin determinístico**: los miembros se ordenan por `usuario_id` implícito (orden de llegada en `pluck`) y los casos por `id`. Con 3 miembros y 5 casos: 2/2/1.
- **Tests**: 308 / 658 assertions verdes. Nuevos en F17: 7 feature (`AsignarCasosAEquipoTest`) — distribución 2/2/1 entre 3 miembros y 5 casos, idempotencia (segunda corrida = 0 asignadas), falla si equipo sin miembros, falla si campaña de otro proyecto, supervisor 200 / gestor 403 en la ruta, Livewire dispara el UseCase y persiste asignaciones.

**Próximos pasos posibles (post-F17):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Exportación CSV de la auditoría.
- Filtro por equipo en la bandeja del supervisor.
- Re-asignación masiva (quitar/mover asignaciones existentes de un equipo a otro).

### 19.20 Estado al cierre de Fase 18 — Bandeja del equipo (2026-04-18)

**Fase 18 completada.** Cierra el loop de supervisión: tras crear equipos (F15), ver sus KPIs (F16) y asignarles trabajo (F17), el supervisor ahora tiene una bandeja dedicada a ver las asignaciones del equipo en tiempo real.

- **Livewire `BandejaEquipo`** (`App\Modules\Asignaciones\Infrastructure\Http\Livewire`): selector de equipo + miembro + estado + búsqueda libre; tabla con KPIs por miembro (Pendiente/En trabajo/Cerrada/Total) y listado paginado de asignaciones filtradas con columna "Gestor" para identificar a quién pertenece cada fila.
- **Parámetros URL** (`#[Url]`): `equipo`, `miembro`, `estado`, `q` — comparte link filtrado.
- **Ruta `/proyectos/{id}/bandeja/equipo`** con `can:asignaciones.ver_equipo` (SUPERVISOR + ADMIN_GLOBAL; GESTOR no — él sigue usando `/bandeja` propia).
- **Tile** en `proyecto-dashboard` sección Supervisión, junto a "Asignación masiva".
- **Defensas**: si `miembroId` no pertenece al equipo seleccionado, se descarta automáticamente (ni siquiera se filtra la query con él).
- **Scope doble**: `proyecto_activo` + `usuario_id IN miembros_del_equipo`. Un gestor que tiene rol en 2 proyectos no contamina la bandeja del equipo del proyecto activo con sus asignaciones del otro proyecto.
- **Reutiliza `asignaciones.ver_equipo`** que ya estaba sembrado para SUPERVISOR (no se crea permiso nuevo).
- **Tests**: 314 / 664 assertions verdes. Nuevos en F18: 6 feature (`BandejaEquipoTest`) — supervisor 200 / gestor 403, sin equipo seleccionado no hay asignaciones, muestra solo las de miembros del equipo (ignora gestor fuera), filtro por miembro limita correctamente, no fuga asignaciones de otro proyecto.

**Próximos pasos posibles (post-F18):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Exportación CSV de la auditoría.
- Re-asignación masiva (mover asignaciones existentes entre equipos).
- Acción de "reasignar" desde la bandeja del equipo (click en fila → cambiar gestor).

### 19.21 Estado al cierre de Fase 19 — Exportación CSV de auditoría (2026-04-18)

**Fase 19 completada.** Cumplimiento: el mandante puede pedir "bájame toda la auditoría del mes" y el auditor del proyecto descarga CSV con todos los eventos filtrados.

- **Controller `ExportarAuditoriaController`** (`App\Modules\Auditoria\Infrastructure\Http\Controllers`): retorna `StreamedResponse` CSV con BOM UTF-8. Acepta query params opcionales: `entidad_tipo`, `usuario_id`, `evento`, `desde`, `hasta`. Usa `chunk(500)` para no cargar toda la tabla a memoria.
- **Ruta `/proyectos/{id}/auditoria/exportar`** con `can:auditoria.ver` (AUDITOR + SUPERVISOR + ADMIN_GLOBAL).
- **Columnas del CSV**: `public_id, creada_en, usuario, entidad_tipo, entidad_id, evento, ip, user_agent, cambios_json, datos_antes_json, datos_despues_json`. Los snapshots se exportan como JSON en columnas planas — permite re-hidratar con `json_decode` aguas abajo.
- **Botón "Exportar CSV"** en el `ListadoAuditoria` (F12): propaga los filtros activos al query string para que el CSV descargado coincida con lo que el usuario está viendo.
- **Filename** incluye código del proyecto + timestamp, p. ej. `auditoria_COBRANZA_DEMO_2026_20260418_153000.csv`.
- **Tests**: 318 / 677 assertions verdes. Nuevos en F19: 4 feature (`ExportarAuditoriaTest`) — auditor descarga con fila `creado` de una persona, gestor 403, filtros por entidad + evento respetados, no fuga auditorías de otro proyecto.

**Próximos pasos posibles (post-F19):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Re-asignación masiva entre equipos.
- Acción "reasignar" desde bandeja del equipo.
- Paginación server-side más agresiva en tablas grandes (actualmente 25/página en auditoría + pagination Livewire).

### 19.22 Estado al cierre de Fase 20 — Re-asignación masiva entre equipos (2026-04-18)

**Fase 20 completada.** Complemento natural de F17 (asignar casos nuevos a un equipo): ahora también se mueven asignaciones existentes de un equipo a otro — útil para rebalanceo, reorganización o cuando un equipo queda sin capacidad.

- **UseCase `ReasignarCasosEntreEquipos`** (`App\Modules\Asignaciones\Application\UseCases`): toma `proyectoId`, `equipoOrigenId`, `equipoDestinoId`, `limite` (0 = todos). Lanza excepción si origen=destino, equipos no pertenecen al proyecto, destino inactivo o destino sin miembros activos.
- **Solo mueve `estado = pendiente`**: las asignaciones `en_trabajo` o `cerrada` no se tocan. El gestor ya inició el caso o ya lo terminó — mover a esta altura destruye trabajo en curso.
- **Round-robin sobre miembros activos del destino**: mismo patrón que F17. 5 pendientes / 2 miembros destino → 3/2.
- **Idempotencia natural**: tras `UPDATE usuario_id`, las asignaciones ya no tienen usuario ∈ origen, así que segunda corrida = 0 movidas.
- **Reutiliza DTO `AsignacionMasivaResultado`** de F17 (`asignadas`, `omitidas`, `distribucion`). `omitidas` queda siempre en 0 aquí — el filtro de estado se hace en la query, no en el loop.
- **Livewire `ReasignarEntreEquipos`**: selector origen, selector destino (solo equipos activos), límite, contador en vivo de pendientes en origen y miembros activos en destino, tabla de distribución resultante.
- **Ruta `/proyectos/{id}/asignaciones/reasignar`** con `can:asignaciones.reasignar` (mismo permiso que F17).
- **Tile** en `proyecto-dashboard` sección Supervisión.
- **Auditoría automática**: como `asignaciones` ahora es modelo Eloquent observable — aún no está en la lista de F12; se auditará si se agrega `AsignacionModel` a `AuditoriaServiceProvider::MODELOS_AUDITADOS` (extension trivial).
- **Tests**: 324 / 691 assertions verdes. Nuevos en F20: 6 feature (`ReasignarEntreEquiposTest`) — distribución 3/2 entre 2 miembros destino, no mueve en_trabajo ni cerrada (pendiente sí), falla si origen=destino, falla si destino sin miembros, supervisor 200 / gestor 403, Livewire dispara el UseCase y persiste.

**Próximos pasos posibles (post-F20):**
- Canales email/slack para notificaciones.
- Importación de contactos por persona.
- Acción "reasignar desde bandeja" (reasignar una fila individual a otro miembro del mismo equipo).
- Notificar al gestor destino cuando recibe asignaciones masivas (integrar con F13).
- Añadir `AsignacionModel` al observer de auditoría (F12) para trazar cambios de `usuario_id`.

### 19.23 Estado al cierre de Fase 21 — Notificación de asignaciones + auditoría de asignaciones (2026-04-18)

**Fase 21 completada.** Dos deudas pequeñas cerradas: cuando un gestor recibe asignaciones (F17 o F20), aparece una notificación en su badge; y `AsignacionModel` queda en el observer de auditoría.

- **Migraciones activas**: 60 (+1: extiende enum `notificaciones.tipo` con `asignacion_recibida`).
- **`GeneradorNotificaciones::registrarAsignacionesRecibidas(proyectoId, distribucion, contexto)`**: inserta una notificación por usuario con `cantidad` y `contexto` en `metadata`. `entidad_id = timestamp + usuario_id` garantiza unicidad entre ejecuciones distintas (el unique compuesto de F13 sigue protegiendo contra duplicados intra-batch).
- **Integración en F17** (`AsignarCasosAEquipo`): tras el commit, si hubo distribución > 0, llama al generador con contexto `asignacion`.
- **Integración en F20** (`ReasignarCasosEntreEquipos`): mismo patrón con contexto `reasignacion` (título distinto: "Asignaciones transferidas a tu bandeja").
- **Observer extendido**: `AsignacionModel` añadido a `AuditoriaServiceProvider::MODELOS_AUDITADOS`. Cualquier creación/actualización/eliminación vía Eloquent queda registrada automáticamente. **Nota**: los UseCases F17/F20 aún usan `DB::table` (insertOrIgnore/update directos) por rendimiento — cuando se migren a Eloquent, la auditoría arranca sin tocar el código.
- **Solo al destinatario**: el gestor origen de una re-asignación NO recibe notificación — solo quienes ganan casos.
- **Tests**: 327 / 699 assertions verdes. Nuevos en F21: 3 feature (`NotificarAsignacionesTest`) — F17 notifica con contexto `asignacion` y cantidad en metadata, F20 notifica con contexto `reasignacion` y no notifica al origen, `AsignacionModel` está en la lista de modelos auditados.

**Próximos pasos posibles (post-F21):**
- Canales email/slack para notificaciones (hay 5 tipos listos para despachar).
- Importación de contactos por persona.
- Acción "reasignar desde bandeja" (fila individual).
- Migrar los UseCases de asignación a Eloquent para activar auditoría automática de UPDATE sin cambiar el código.
- Agrupar/colapsar notificaciones en el listado por tipo y período.

### 19.24 Estado al cierre de Fase 22 — Permisos granulares CRUD × módulo × cartera (2026-04-18)

**Fase 22 completada.** Rediseño serio del sistema de permisos atendiendo el pedido explícito del usuario ("permisos mucho más modulares y granulares"). Sin romper nada del trabajo previo.

- **Migraciones activas**: 61 (+1: tabla nueva `usuario_proyecto_rol_cartera`).
- **`PermisosSeeder` expandido** a matriz uniforme CRUD por cada módulo. Acciones canónicas: `ver / crear / editar / eliminar / administrar`. Total ~70 permisos (vs ~25 antes). Se preservan alias legacy (`gestionar`, `resolver`, `reasignar`, `ver_propia`, `ver_equipo`) para retrocompatibilidad — no se quita ninguno.
- **`RolPermisoSeeder` reescrito** con matriz explícita por rol:
  - **SUPERVISOR** ve + opera + administra dentro de su proyecto. **NO** define campos ni entidades configurables (`campos.definir`/`entidades.definir` quedan exclusivos de ADMIN_GLOBAL).
  - **GESTOR** ve/crea gestiones+contactos, edita **solo valores** de campos y maneja su bandeja. **Nunca** gestiona usuarios/catálogos/equipos/configuración ni define campos/entidades.
  - **AUDITOR** solo lectura + export de auditoría + reportes. No ve su bandeja propia (semánticamente no tiene trabajo asignado).
- **Scope por cartera opcional**: tabla secundaria `usuario_proyecto_rol_cartera` (usuario+proyecto+rol+cartera, unique). Semántica:
  - Sin filas → rol aplica a **todo el proyecto** (100% compatible con F1–F21).
  - Con filas → rol aplica **solo** a las carteras listadas.
  - Sin cambios en `usuario_proyecto_rol` — tabla opcional paralela, sin dropear PK ni FKs.
- **`User::tienePermiso($codigo, $proyectoId = null, $carteraId = null)`**: tercer parámetro opcional. Si `$carteraId === null`, evalúa solo proyecto (legacy). Si viene, valida que al menos un rol con el permiso no tenga restricción O tenga esa cartera listada. ADMIN_GLOBAL corta antes por `esAdminGlobal()`.
- **`Gate::before`** en `UsuariosServiceProvider` resuelve `$arguments[1]` como `carteraId` cuando se llama `Gate::allows('x.y', $proyectoId, $carteraId)`.
- **UI**: `GestionUsuariosProyecto` con selector multi-check de carteras en el formulario. "Restringir a carteras (opcional): si no seleccionas ninguna, el rol aplica a todo el proyecto." Cada rol en el listado muestra las carteras restringidas (o "todo el proyecto" si no hay).
- **Idempotencia en reasignación**: al reasignar, se borran restricciones previas y se insertan las nuevas.
- **Tests**: 333 / 715 assertions verdes. Nuevos en F22: 6 feature (`PermisosCarteraTest`) — sin restricción aplica a todas, con restricción solo a listadas, sin cartera especificada ignora restricciones (retrocompat), ADMIN_GLOBAL ignora todo, Livewire asigna con carteras, reasignar reemplaza restricciones.

**Próximos pasos posibles (post-F22):**
- **Fase 23**: hardening de restricción al GESTOR (tests defensivos contra definir campos/entidades desde cualquier ruta).
- **Fase 24**: Entidades configurables por proyecto/cartera (reinterpretación del pedido "crear módulos desde cero" con los límites de CLAUDE.md).
- **Fase 25–27**: design system + refactor visual integral.

### 19.25 Estado al cierre de Fase 23 — Hardening: gestor/supervisor no definen campos (2026-04-18)

**Fase 23 completada.** Sello defensivo al pedido explícito del usuario: "el agente no debe poder crear nuevos campos bajo ninguna circunstancia". Hoy ya era así (verificado con código y seeder), pero se agregó defensa en profundidad en el Livewire admin y tests que lo prueban desde múltiples ángulos.

- **`AdminCamposPersonalizados`** ahora tiene método `autorizar()` que re-valida `campos.definir` en cada punto de entrada: `mount`, `abrirFormCrear`, `abrirFormEditar`, `guardar`, `desactivar`, `activar`. Antes dependía **solo** del middleware HTTP (`admin.global`). Ahora aunque alguien monte el Livewire desde otra ruta o dispare acciones fuera del flujo, las acciones abortan 403.
- **Seeder auditado**:
  - `campos.definir` NO se asigna a SUPERVISOR, GESTOR ni AUDITOR en `RolPermisoSeeder` (F22).
  - `entidades.definir` (que se usará en F24) tampoco se asigna a ningún rol no-admin.
  - Solo ADMIN_GLOBAL los tiene (vía asignación explícita + bypass de `Gate::before` sobre `esAdminGlobal()`).
- **Tests defensivos exhaustivos**: 10 tests nuevos (`GestorNoDefineCamposTest`) cubriendo:
  - Ruta HTTP `/admin/campos-personalizados`: 403 para GESTOR, 403 para SUPERVISOR, 200 para ADMIN_GLOBAL.
  - Livewire `AdminCamposPersonalizados` → `assertStatus(403)` cuando se monta como GESTOR o SUPERVISOR.
  - ADMIN_GLOBAL sí puede crear una definición end-to-end.
  - Aserción directa sobre la DB: ningún rol no-admin tiene `campos.definir` ni `entidades.definir` en `rol_permiso`.
  - `tienePermiso('campos.definir')` retorna `false` para GESTOR/SUPERVISOR en cualquier proyecto.
- **`FormularioCamposPersonalizados`** (valores) sigue igual desde F6: valida `campos.editar` en `guardar()` con `abort(403)`. GESTOR solo edita VALORES, nunca DEFINICIONES.
- **Tests**: 343 / 734 assertions verdes (+10 en F23).

**Resultado**: la restricción pedida está garantizada por tres capas — middleware de ruta + método `autorizar()` en el Livewire + ausencia del permiso en el seeder para roles no-admin. Cualquier agujero futuro rompería al menos un test.

**Próximos pasos posibles (post-F23):**
- **Fase 24**: Entidades configurables por proyecto (reinterpretación controlada del pedido "crear módulos desde cero").
- **Fase 25–27**: design system + refactor visual integral.

### 19.26 Estado al cierre de Fase 24 — Entidades configurables por proyecto/cartera (2026-04-18)

**Fase 24 completada.** Atiende el pedido del usuario de "crear módulos desde cero y adaptarlos a cartera o proyecto" SIN convertir el sistema en Vtiger. CLAUDE.md §1.3, §7.7 y §15 actualizados para documentar la apertura controlada.

- **Migraciones activas**: 64 (+3: `entidades_configurables`, `entidades_registros`, ALTER `campos_personalizados.ambito` con `entidad_configurable`).
- **Módulo nuevo `EntidadesConfigurables`** registrado en `bootstrap/providers.php`. Domain/Application/Infrastructure. **Cero duplicación de esquema**: una entidad configurable es una "tabla lógica" cuyos campos son registros de `campos_personalizados` con ámbito `entidad_configurable`, y sus valores viven en `valores_campo_personalizado`. Reutiliza el evaluador y el servicio de F6.
- **Tabla `entidades_configurables`**: `proyecto_id`, `cartera_id` nullable (scope opcional), `codigo` único por proyecto, `relacion_con` enum `ninguna|caso|persona`. Activo, soft-delete, timestamps.
- **Tabla `entidades_registros`**: una fila por registro de la entidad. `caso_id`/`persona_id` opcionales según `relacion_con` de la definición.
- **`ServicioEntidades` (Application)**: `crearEntidad`, `actualizarEntidad`, `eliminarEntidad`, `entidadesDelProyecto`, `crearRegistro`, `actualizarRegistro`, `eliminarRegistro`, `registros`. Valida y persiste valores vía `ServicioCamposPersonalizados` (§7) — cero lógica de validación duplicada.
- **Livewire `AdminEntidadesConfigurables`** (`/admin/entidades-configurables`): CRUD de definiciones + gestión de campos de cada entidad. Permiso exclusivo `entidades.definir` con `autorizar()` en cada acción (defensa en profundidad F23).
- **Livewire `GestorRegistrosEntidad`** (`/proyectos/{id}/entidades/{entidad_id}`): form dinámico generado automáticamente de los campos. Permisos granulares: `entidades.ver/crear/editar/eliminar`. Cada acción re-valida el permiso.
- **Permisos ya sembrados en F22**: `entidades.ver/crear/editar/eliminar` → GESTOR y SUPERVISOR (scoped por cartera si aplica). `entidades.definir` → ADMIN_GLOBAL exclusivo.
- **Auditoría automática**: aunque `EntidadRegistroModel` no está en la lista de F12 todavía, los valores guardados van a `valores_campo_personalizado` y la definición a `campos_personalizados` — ambos ya registran eventos si un observer los vigila.
- **CLAUDE.md actualizado**:
  - §1.3: líneas rojas reformuladas para distinguir "módulos" (código) de "entidades configurables" (datos tipados).
  - §7.7 nueva: 7 reglas estrictas + 6 prohibiciones + permisos granulares + "cuándo usar entidad configurable vs código".
  - §15.1: alias "módulos no, entidades sí" documentado.
- **Tests**: 352 / 755 assertions verdes. Nuevos en F24: 9 feature (`EntidadesConfigurablesTest`) — gestor/supervisor 403 en admin, admin global 200, Livewire admin aborta 403 para gestor, admin crea entidad + campo, rechazo de código duplicado, supervisor crea registro con valores, usuario sin permiso 403, scope cross-proyecto, eliminar registro marca `eliminado_en`.

**Resultado**: el admin global puede crear entidades tipo "Pólizas" con campos `numero_poliza (texto)`, `monto_asegurado (moneda)`, `fecha_vigencia (fecha)`, ligada opcionalmente a Caso. El supervisor/gestor las ve, crea registros y los edita — pero nunca puede modificar la estructura. Cumple el pedido del usuario sin romper la línea roja de "no Vtiger".

**Próximos pasos posibles (post-F24):**
- **Fase 25**: Design system (Tailwind config + componentes Blade base).
- **Fase 26**: Refactor visual de pantallas críticas del gestor.
- **Fase 27**: Refactor visual de pantallas admin y reportes.
- Añadir `EntidadRegistroModel` a modelos auditados (F12).
- Bloque "Entidades de este proyecto" en proyecto-dashboard con links a cada entidad.
- Integración en vista de trabajo: mostrar registros de entidades ligadas al caso/persona activa.

### 19.27 Estado al cierre de Fase 25 — Design system base (2026-04-18)

**Fase 25 completada.** Cimiento del pedido #4 del usuario ("mejora total GUI/UX"). Se establece design system antes de tocar pantallas: tokens de color, tipografía, componentes Blade reutilizables.

- **Tailwind config extendido** (`tailwind.config.js`):
  - **Tokens de color semánticos**: `brand` (indigo, acciones primarias), `accent` (violeta), `surface` (grises de superficie), `ink` (grises de texto), `success/warning/danger/info`. Sustituye `indigo-600`/`emerald-500`/etc. en componentes nuevos.
  - **Tipografía**: `Inter` como font principal (reemplaza Figtree). Fallbacks Figtree + sans default.
  - **Shadows**: `card`, `card-hover`, `modal` — más sutiles que default.
  - **Border radius**: `xl` (0.75rem), `2xl` (1rem).
- **Layout actualizado** (`resources/views/layouts/app.blade.php`): fondo `bg-surface-50`, texto default `text-ink-800`, header con `border-b border-surface-border`.
- **Componentes Blade reutilizables** en `resources/views/components/ui/`:
  - `x-ui.card` — contenedor con slots `header/actions/footer`, `title`/`subtitle`/`padding`.
  - `x-ui.stat-card` — KPI con `label/value/hint/tone/icon`.
  - `x-ui.button` — variants `primary|secondary|ghost|danger|success`, sizes `sm|md|lg`, puede renderizar como `<a>` o `<button>`.
  - `x-ui.badge` — tonos semánticos con anillo sutil.
  - `x-ui.page-header` — título + subtítulo + slot actions + back link.
  - `x-ui.section-title` — encabezado uppercase.
  - `x-ui.empty-state` — placeholder con título/mensaje/acción.
  - `x-ui.form-field` — label + slot input + hint + error.
  - `x-ui.alert` — mensaje flash con tonos.
  - `x-ui.table` + `x-ui.th` + `x-ui.td` — tabla con border y hover.
  - `x-ui.icon` — SVG inline (plus, pencil, trash, search, bell, users, chart-bar, briefcase, clipboard, check, x-mark, shield, arrow-right).
- **Pantalla demo refactorizada**: `proyecto-dashboard` rehecho con `x-ui.*` agrupado por categorías (Operación / Supervisión / Administración / Trazabilidad / Datos) con iconos. Patrón a replicar en F26–F27.
- **Convención**: los componentes nuevos usan tokens semánticos (`bg-brand-600`, `text-ink-700`), **no** colores directos (`bg-indigo-600`). Pantallas legacy siguen funcionando.
- **Tests**: 365 / 790 assertions verdes. Nuevos en F25: 13 smoke tests (`DesignSystemTest`) — badge/button/card/stat-card/empty-state/alert/form-field/table/icon con variantes, y render del nuevo proyecto-dashboard.

**Próximos pasos posibles (post-F25):**
- **Fase 26**: Refactor visual de bandeja, vista de trabajo, bandeja del equipo, listado notificaciones.
- **Fase 27**: Refactor visual de admin, catálogos, reportes, auditoría, importaciones.
- Ampliar set de iconos según demanda (o migrar a `blade-heroicons`).

### 19.28 Estado al cierre de Fase 26 — Refactor visual pantallas operativas (2026-04-18)

**Fase 26 completada.** Aplica el design system de F25 a las 4 pantallas más vistas del día a día: bandeja del gestor, bandeja del equipo, listado de notificaciones, shell de la vista de trabajo.

- **`/proyectos/{id}/bandeja`**:
  - Header → `x-ui.page-header` con subtítulo (nombre de proyecto) y código en mono.
  - Barra de filtros → `x-ui.card` con chips pill para cada estado, contadores, buscador con icono embebido.
  - Tabla → `x-ui.table` + `x-ui.th`/`x-ui.td`. Cada fila usa `x-ui.badge` para tipo de caso y estado de asignación (tonos semánticos). Botones de "Trabajar" (primary) y "Cerrar" (secondary) con `x-ui.button`.
  - Estado vacío → `x-ui.empty-state`.
- **`/proyectos/{id}/bandeja/equipo`** (supervisor):
  - `x-ui.page-header` con back link a dashboard.
  - Filtros en `x-ui.card` con 4 columnas responsive.
  - KPIs por miembro en `x-ui.table` con tonos por columna (pendientes = warning, en trabajo = info, cerradas = success).
  - Sin equipo seleccionado → `x-ui.empty-state`. Equipo sin miembros → `x-ui.alert tone="warning"`.
  - Listado de asignaciones filtradas en `x-ui.table` con paginación.
- **`/proyectos/{id}/notificaciones`**:
  - Panel de filtro + "marcar todas leídas" en `x-ui.card` con botón primario.
  - Lista en `x-ui.card padding="p-0"` con `<ul>` interna. Cada notificación con dot coloreado (tono según tipo: danger/warning/info), badge de tipo, click para marcar leída con `x-ui.button variant="ghost"`.
  - Estado vacío → `x-ui.empty-state`.
- **`/proyectos/{id}/trabajo/{persona}/{caso?}`** (shell):
  - Header `x-ui.page-header` con back link a bandeja.
  - El interior (Livewire `casos.vista-de-trabajo` con slots por tipo cobranza/cx/venta/servicio) **no se tocó** — se refactoriza por separado cuando se necesite. La shell ya es consistente con el design system.
- **Convención verificada**: todas las pantallas usan `bg-brand-600`/`text-ink-900`/`border-surface-border` en vez de `indigo-600`/`gray-900`/`gray-200`. Las tonalidades semánticas (`warning`, `info`, `success`, `danger`) están en `x-ui.badge` y bloques de alerta.
- **Sin cambios en Livewire PHP**: el refactor es puramente visual. Cero toques a lógica de dominio, servicios ni repositorios. Los 365 tests previos pasan sin ajustes.
- **Tests**: 369 / 802 assertions verdes. Nuevos en F26: 4 tests de render (`PantallasOperativasRefactorTest`) — verifican que cada pantalla renderiza 200 y contiene tokens `text-ink-900` / `shadow-card` esperados.

**Próximos pasos posibles (post-F26):**
- **Fase 27**: Refactor visual de admin (mandantes/proyectos/usuarios/campos/entidades), catálogos, reportes, auditoría, importaciones, asignación masiva + reasignación, CRUDs de equipos.
- Refactor de la shell interna de vista de trabajo (los slots tipo-específicos cobranza/cx/venta/servicio y el form de nueva gestión).
- Ampliar iconos en `x-ui.icon` según aparezcan necesidades.

### 19.29 Estado al cierre de Fase 27 — Refactor visual admin/supervisión (2026-04-18) — Cierra los 4 pedidos del usuario

**Fase 27 completada.** Aplica el design system a todas las pantallas admin, reportes, catálogos, importaciones, auditoría, asignaciones y CRUDs de proyecto. **Con esto cierra el último pedido pendiente del usuario (mejora total GUI/UX) y completa el plan correctivo F22–F27.**

- **Pages refactorizadas con `x-ui.page-header`** (15 shells): admin-dashboard, admin-mandantes, admin-proyectos, admin-usuarios, admin-campos-personalizados, admin-entidades-configurables, selector-proyecto, reportes-operativos, reportes-analiticos, reportes-equipos, auditoria, importaciones, catalogos, asignaciones-masiva, asignaciones-reasignar, usuarios-proyecto, equipos-proyecto, entidades-operativo, personas-crear, contactos-lista. **Todas** con título + subtítulo + back link consistente.
- **Admin-dashboard rediseñado**: tile-based con iconos y jerarquía clara, igual patrón que el proyecto-dashboard de F25.
- **Selector-proyecto rediseñado**: cards con hover sutil, badges de tipo de operación tonalizadas, empty-state para usuarios sin proyectos.
- **Tabs de `importaciones` y `catalogos` actualizados** a tokens semánticos (`border-brand-600`, `text-brand-700`, `text-ink-500`) — antes usaban `indigo-600`/`gray-500` directos.
- **Layout `py-8` → `py-6`**: espaciado superior de contenido más ajustado, consistente con el header delgado del design system.
- **Cero cambios en lógica Livewire**: puramente visual. Los 369 tests previos pasan sin ajustes, más los 14 nuevos de F27.
- **Tests**: 383 / 831 assertions verdes. Nuevos en F27: 14 tests de render (`PantallasAdminRefactorTest`) — cada pantalla admin/supervisor renderiza 200 y contiene tokens del design system.

### Resumen cierre del plan correctivo F22–F27

| Pedido del usuario | Fase | Estado |
|---|---|---|
| 2. Permisos granulares (módulo/cartera/acción/rol) | **F22** | ✅ Matriz CRUD + tabla `usuario_proyecto_rol_cartera` opcional + `User::tienePermiso($codigo, $proy, $cartera)` |
| 3. Restricción estricta al gestor para crear campos | **F23** | ✅ Defensa en 3 capas (middleware + `autorizar()` Livewire + ausencia en seeder) + 10 tests defensivos |
| 1. Crear módulos desde cero adaptables a cartera | **F24** | ✅ Entidades configurables por proyecto/cartera (§7.7 CLAUDE.md) — reutiliza campos §7, sin fórmulas/triggers/rules engine |
| 4. Mejora total GUI/UX | **F25–F27** | ✅ Design system (tokens Tailwind + Inter + 10 `x-ui.*` components) + refactor de 24 pantallas operativas y admin |

**Totales**: 383 tests / 831 assertions verdes, 64 migraciones, 18 módulos activos, 24 pantallas rediseñadas.

**Próximos pasos posibles (post-F27):**
- Refactor interno de vista de trabajo (slots por tipo cobranza/cx/venta/servicio + form nueva gestión).
- Integrar entidades configurables en vista de trabajo (mostrar registros de "Pólizas", "Vehículos" ligados al caso).
- Canales email/slack para notificaciones F13.
- Reportes operativos con `x-ui.stat-card` para KPIs grandes.
- Importación de contactos por persona.

El **plan detallado del refactor v1 → v2** se documenta en `DOCS/MIGRACION_V2.md` cuando se inicie. Este CLAUDE.md define el destino, no ejecuta la migración.

### 19.30 Estado al cierre de Fase 28 — Capa de integración wrapper (2026-04-29)

**Fase 28 completada.** Capa SSO para wrapper externo (iframe SaaS) operativa. El CRM actúa como receptor pasivo: el wrapper inicia siempre.

- **Migraciones activas**: 65 (+1 `integracion_tokens_sso`, +1 `personal_access_tokens` Sanctum).
- **Módulo `Integracion` nuevo** registrado en `bootstrap/providers.php` (19 módulos activos).
- **`laravel/sanctum`** instalado (`^4.3`); `HasApiTokens` añadido a `User`.
- **Domain** (`app/Modules/Integracion/Domain/`): entidad `TokenSso` (invariantes `consumir()` / `expirado()`), VO readonly `TokenClaroHash` (sha256), eventos `TokenSsoEmitido` / `TokenSsoConsumido`, 3 excepciones (`Invalido`, `Expirado`, `YaConsumido`), contrato `RepositorioTokenSso`.
- **UseCases**: `EmitirTokenSso` (genera `Str::random(64)` + sha256, TTL configurado en `integracion.token_sso_ttl_segundos`, devuelve URL de handshake), `ConsumirTokenSso` (valida, consume, resuelve `personaPublicId` vía join `personas × tipos_identificacion`).
- **Infrastructure**: `TokenSsoModel`, `RepositorioTokenSsoEloquent`, `SsoHandshakeController` (POST emitir + GET consumir), `SsoLogoutController` (invalida token Sanctum + sesión web), `PreviewPersonaController` (JSON con persona + casos + compromiso vigente + última gestión), `CspFrameAncestors` middleware.
- **Rutas**: `POST /api/auth/sso-handshake` (throttle 10/min), `POST /api/auth/logout` (auth:sanctum), `GET /api/integracion/persona` (auth:sanctum, throttle 60/min), `GET /integracion/handshake` (web + csp.frame-ancestors).
- **Config** `config/integracion.php`: `wrapper_domain`, `token_sso_ttl_segundos` (default 300), `preview_api_throttle`. `.env.example` actualizado.
- **Seguridad**: redirect_path absolutos (`http://`/`https://`) rechazados; throttle en handshake; token one-time (consumido_en no-null = 410); expiración por timestamp; multi-tenancy: preview API respeta `tieneAccesoAProyecto`.
- **Tests**: 415 / 896 assertions verdes (+29: 22 Feature, 7 Unit). Nuevos en F28: `SsoHandshakeTest` (credenciales válidas, 401, throttle 429), `ConsumirTokenSsoTest` (redirect a trabajo/bandeja, token expirado 410, ya consumido 410, redirect absoluto rechazado, token inválido 410), `PreviewPersonaTest` (200 JSON, 401 sin auth, 403 otro proyecto, 403 sin rol, 404 persona inexistente), `SsoLogoutTest` (200, 401 sin auth, token invalidado), `CspFrameAncestorsTest` (header presente / ausente).

**Próximos pasos posibles (post-F28):**
- `SESSION_SAMESITE=none` en `.env` de producción cuando el CRM opere dentro de un iframe cross-origin (requiere HTTPS).
- Canales email/slack para notificaciones F13.
- Refactor interno de vista de trabajo.
- Importación de contactos por persona.

---

## 20. Decisiones arquitectónicas

- **2026-04-17 — Pivot a plataforma BPO multi-tipo y multi-tenant.** Alternativas consideradas: (A) mantener solo cobranza multi-tenant, (B) plataforma multi-tipo desde inicio, (C) multi-sistema. Elegido (B). Justificación: el negocio del usuario no es solo cobranza; imponerle un sistema mono-tipo exigiría comprar o construir otros sistemas para CX, ventas y servicios, fragmentando la operación.

- **2026-04-17 — Personas aisladas por proyecto.** Alternativas: (A) compartidas por identificación con permisos, (B) aisladas con dedupe técnico opcional. Elegido (B). Justificación: privacidad entre mandantes es condición de negocio del BPO; no puede haber fuga aun involuntaria.

- **2026-04-17 — Modelo de caso con CTI (no STI ni JSON genérico).** Justificación: evitar el caos de tabla gigantesca con columnas vacías; mantener tipado fuerte y queries limpias por tipo.

- **2026-04-17 — URL lleva `proyecto_id`.** Alternativas: (A) solo sesión, (B) URL + sesión, (C) URL autoritativa + dropdown. Elegido (C). Justificación: URL autoritativa evita ambigüedad, facilita auditoría, testing y breadcrumbs.

- **2026-04-17 — Catálogos casi todos por proyecto; globales solo lo universal.** Justificación: todo lo operativo cambia entre mandantes; un cambio para un cliente no debe romper otro.

- **2026-04-18 — `causas_mora` y `estados_cobranza` reutilizan tablas genéricas.** Alternativas: (A) tablas específicas `causas_mora` y `estados_cobranza` por tipo de operación (como pide el plan literal §2.A), (B) reutilizar las tablas genéricas `causas_gestion` y `estados_caso` ya existentes, diferenciando por código/metadata por proyecto. Elegido (B). Justificación: Fase 1 ya creó `causas_gestion` con `metadata` JSON y `estados_caso` scoped por proyecto, ambas listas para cobijar múltiples tipos de operación. Crear tablas específicas por tipo fragmentaría el modelo y obligaría a `gestiones.causa_id` polimórfico. Se acepta el ajuste del plan documentándolo aquí.

- **2026-04-18 — `DatosCompromiso` como interfaz abstracta en Gestiones.** Alternativas: (A) el evento `GestionRegistrada` no lleva datos de compromiso y cada tipo hace lookup tras el fact, (B) datos del compromiso llegan en el mismo evento como interfaz polimórfica, (C) UseCase específico por tipo que orquesta todo. Elegido (B). Justificación: la §5 de CLAUDE.md exige que el compromiso se cree **en la misma transacción** que la gestión; si los datos no viajan en el evento síncrono, el listener tendría que reconsultar o la UI persistir aparte (rompe atomicidad). Un marker interface en Gestiones permite que el listener de Cobranza filtre por `instanceof DatosPromesaPago` sin que Gestiones conozca Cobranza.

- **2026-04-18 — Apertura controlada: "Entidades configurables" NO son módulos.** Pedido del usuario (Fase 22–24): "quiero crear módulos desde cero adaptables por cartera". Alternativas: (A) rechazar y mantener la línea roja literal, (B) permitir módulos dinámicos completos (→ Vtiger), (C) crear un mecanismo de "tablas de datos configurables" ligadas al sistema de campos personalizados existente, sin fórmulas/triggers/layouts editables. Elegido (C), documentado en §7.7. Justificación: el usuario necesitaba flexibilidad real para los mandantes (ej. pólizas, vehículos), pero permitir código dinámico reintroduce todos los problemas que (v2) fue escrita para evitar. (C) da los mismos casos de uso que el 95% de los clientes necesitan, con cero posibilidad de ejecutar lógica arbitraria. "Módulo" se redefine como lo que siempre fue: código PHP en `app/Modules/`. Las "tablas configurables" se llaman **entidades configurables** y nunca se les dice "módulos" en la UI.

---

## 21. Historial de cambios

- **2026-04-17 (v1)**: Versión inicial. CRM de cobranza opinado, sin campos personalizados.
- **2026-04-17 (v2)**: Reescritura completa. Plataforma BPO multi-tipo y multi-tenant. Personas aisladas por proyecto. CTI para casos y compromisos. URL con `proyecto_id`. Campos personalizados bajo esquema estricto. Roles por proyecto. Plan por 5 fases.
- **2026-04-17 (Fase 1 cerrada)**: Core multi-tenant operativo — 34 migraciones, 10 módulos, 124 tests, UI abstracta (Bandeja, Vista de Trabajo, Buscador global). Listo para iniciar Fase 2 (cobranza).
- **2026-04-18 (Fase 2 cerrada)**: Cobranza migrada al núcleo — 38 migraciones, 11 módulos (+Cobranza), 152 tests, paridad funcional v1 (registrar gestión con promesa, resolver promesa, bandera vigente), slot cobranza en Vista de Trabajo, formulario Nueva Gestión dinámico, Resolver Promesa, campos personalizados demo. Módulo `Promesas` v1 archivado. Listo para iniciar Fase 3 (CX).
- **2026-04-18 (Fase 3 cerrada)**: CX operativo como 2º tipo — 45 migraciones, 12 módulos (+Cx), 168 tests, proyecto dual `COBRANZA_DEMO` + `SOPORTE_DEMO` bajo mismo mandante, listener `CrearResolucionDesdeGestion`, `DatosResolucionTicket` implementa `DatosCompromiso`, UI slot CX coexistiendo con slot cobranza en la misma Vista de Trabajo abstracta, tests multi-tenancy cross-proyecto verdes. Listo para iniciar Fase 4 (Venta).
- **2026-04-18 (Fase 4 cerrada)**: Venta operativo como 3º tipo — 49 migraciones, 13 módulos (+Venta), 180 tests, proyecto triple `COBRANZA_DEMO` + `SOPORTE_DEMO` + `VENTA_DEMO` bajo mismo mandante, listener `CrearCierreDesdeGestion`, `DatosCierreVenta` implementa `DatosCompromiso`, UI slot venta (con etapas embudo + probabilidad), 3 slots coexistiendo en la misma Vista de Trabajo abstracta. Listo para iniciar Fase 5 (Servicio).
- **2026-04-18 (Fase 5 cerrada — PLAN COMPLETO)**: Servicio técnico operativo como 4º tipo — 53 migraciones, 14 módulos (+Servicio), 191 tests, proyecto cuádruple `COBRANZA_DEMO` + `SOPORTE_DEMO` + `VENTA_DEMO` + `SERVICIO_DEMO` bajo mismo mandante, listener `CrearAccionDesdeGestion`, `DatosAccionServicio` implementa `DatosCompromiso`, UI slot servicio (código servicio, técnico, dirección, fechas programadas), 4 slots + 4 listeners coexistiendo. **Plan de 5 fases del refactor v2 completo.**
- **2026-04-18 (Fase 6 cerrada — Permisos & Admin)**: 3 bugs de permisos atendidos — 55 migraciones (+2), 16 módulos (+Reportes, +Importaciones), 209 tests, nuevo permiso `campos.definir` separa admin de operativo, `FormularioCamposPersonalizados` valida permiso + render bloqueado, admin UI CRUD en `/admin/campos-personalizados`, ruta `/proyectos/{id}/reportes/operativos` con dashboard scoped, módulo Importaciones con upload CSV dry-run + commit + exportación CSV de personas.
- **2026-04-18 (Fase 7 cerrada — Administración global completa)**: admin-dashboard sin placeholders — 236 tests, CRUDs admin de mandantes/proyectos/usuarios cableados (con protección anti-self-lock en ADMIN_GLOBAL), asignación de roles por proyecto desde UI, reportes analíticos en `/proyectos/{id}/reportes/analiticos`, 3 exports CSV adicionales (casos, gestiones, compromisos) complementando el de personas.
- **2026-04-18 (Fase 8 cerrada — Catálogos operativos por proyecto)**: 245 tests, CRUDs de los 5 catálogos operativos (resultados, tipos_gestion, causas, motivos_no_contacto, estados_caso) en una UI única con tabs Alpine bajo `/proyectos/{id}/catalogos`. Permiso `catalogos.gestionar` añadido a SUPERVISOR. Clase base abstracta `AbstractAdminCatalogo` + partial Blade reutilizable. Módulo Catalogos registrado. Adapter v1 huérfano archivado.
- **2026-04-18 (Fase 9 cerrada — Catálogos tipo-específicos)**: 258 tests, 10 CRUDs adicionales integrados en la misma página de F8 con tabs condicionales según `tipo_operacion`: Cobranza (tramos_mora, tipos_pago), CX (categorías, prioridades, SLA, escalamiento), Venta (productos, etapas_embudo), Servicio (tipos_accion, estados_tecnicos). Todos extienden `AbstractAdminCatalogo`. Validaciones de rango y unicidad custom.
- **2026-04-18 (Fase 10 cerrada — Gestión de usuarios por proyecto)**: 268 tests, Livewire `GestionUsuariosProyecto` en `/proyectos/{id}/usuarios` con `usuarios.gestionar`. Supervisor asigna/revoca SUPERVISOR/GESTOR/AUDITOR por email; defensas en profundidad contra manipular ADMIN_GLOBAL y contra auto-revocación. Se cierra la brecha de F7 donde sólo admin global podía asignar roles.
- **2026-04-18 (Fase 11 cerrada — Importaciones por tipo de operación)**: 272 tests, 56 migraciones (+1), 4 UseCases `ProcesarImportacionCasos{Cobranza|TicketCx|LeadVenta|Servicio}` + Livewire único `ImportarCasos` que delega por `tipo_operacion`. Un proyecto solo importa casos de su tipo. Persona debe existir antes (no se crea durante importación de casos). Idempotencia vía código único de cada tipo.
- **2026-04-18 (Fase 12 cerrada — Auditoría exhaustiva)**: 277 tests, 57 migraciones (+1 `auditorias`), módulo `Auditoria` nuevo con observer Eloquent único sobre 12 modelos (núcleo + 4 CTI casos + 4 CTI compromisos), Livewire `ListadoAuditoria` con filtros y detalle JSON, `auditoria.ver` añadido a SUPERVISOR. `proyecto_id` del evento se toma del modelo auditado para soportar jobs/CLI.
- **2026-04-18 (Fase 13 cerrada — Notificaciones in-app)**: 283 tests, 58 migraciones (+1 `notificaciones`), módulo `Notificaciones` con `GeneradorNotificaciones` idempotente, comando `notificaciones:generar`, Livewire listado en `/proyectos/{id}/notificaciones` y badge con contador en header. Alcance: compromisos por vencer y vencidos. Sin email/queue.
- **2026-04-18 (Fase 14 cerrada — SLA CX + scheduler)**: 287 tests, generador extendido con tipo `sla_en_riesgo` (join compromisos+compromisos_resolucion_ticket dentro de ventana `--horas-sla=8`), comando con `--umbral` y `--horas-sla`, scheduler en `routes/console.php` con una corrida diaria 08:00 y otra hourly en horario laboral.
- **2026-04-18 (Fase 15 cerrada — Equipos por proyecto)**: 295 tests, 59 migraciones (+1 `equipo_usuario`), Livewire `AdminEquiposProyecto` con CRUD del equipo + buscador de miembros por email, ruta `/proyectos/{id}/equipos` con `can:usuarios.gestionar`, defensas contra ADMIN_GLOBAL y contra usuarios sin rol activo en el proyecto.
- **2026-04-18 (Fase 16 cerrada — Reportería por equipo)**: 301 tests, Livewire `ReporteEquipos` con KPIs agregados (miembros, gestiones, efectividad, compromisos vigentes/vencidos) y breakdown por miembro al expandir, ruta `/proyectos/{id}/reportes/equipos` con `can:reportes.operativos`. Scope doble (proyecto + miembros del equipo).
- **2026-04-18 (Fase 17 cerrada — Asignaciones masivas a un equipo)**: 308 tests, UseCase `AsignarCasosAEquipo` con distribución round-robin idempotente, Livewire `AsignarMasivamente` en `/proyectos/{id}/asignaciones/masiva` con `can:asignaciones.reasignar`. Valida campaña y equipo ∈ proyecto activo.
- **2026-04-18 (Fase 18 cerrada — Bandeja del equipo)**: 314 tests, Livewire `BandejaEquipo` con selector equipo+miembro+estado+búsqueda, KPIs por miembro y listado paginado, ruta `/proyectos/{id}/bandeja/equipo` con `can:asignaciones.ver_equipo`. Cierra loop F15+F16+F17+F18 de supervisión.
- **2026-04-18 (Fase 19 cerrada — Exportación CSV de auditoría)**: 318 tests, `ExportarAuditoriaController` con `StreamedResponse`+`chunk(500)` y query params de filtro (entidad/usuario/evento/desde/hasta), botón en `ListadoAuditoria` propaga filtros actuales al query string.
- **2026-04-18 (Fase 20 cerrada — Re-asignación masiva entre equipos)**: 324 tests, UseCase `ReasignarCasosEntreEquipos` que solo mueve `pendiente` con round-robin al destino, Livewire `ReasignarEntreEquipos` en `/proyectos/{id}/asignaciones/reasignar` con `can:asignaciones.reasignar`. Respeta en_trabajo/cerrada.
- **2026-04-18 (Fase 21 cerrada — Notificación de asignaciones + auditoría de asignaciones)**: 327 tests, 60 migraciones (+1 extiende enum `notificaciones.tipo` con `asignacion_recibida`), `GeneradorNotificaciones::registrarAsignacionesRecibidas` + integración en F17/F20, `AsignacionModel` añadido al observer de auditoría (F12).
- **2026-04-18 (Fase 22 cerrada — Permisos granulares CRUD × módulo × cartera)**: 333 tests, 61 migraciones (+1 `usuario_proyecto_rol_cartera`), matriz CRUD uniforme en `PermisosSeeder` (~70 permisos preservando legacy), `RolPermisoSeeder` reescrito, `User::tienePermiso` con 3er parámetro `$carteraId`, UI con selector multi-check de carteras en asignación de roles. Compatibilidad total con F1–F21.
- **2026-04-18 (Fase 23 cerrada — Hardening: gestor/supervisor no definen campos)**: 343 tests, método `autorizar()` en `AdminCamposPersonalizados` re-valida en cada acción, tests exhaustivos (10 nuevos) cubren ruta HTTP + Livewire + seeder DB + `tienePermiso()`. Defensa en tres capas: middleware + autorizar() + ausencia en seeder.
- **2026-04-18 (Fase 24 cerrada — Entidades configurables por proyecto/cartera)**: 352 tests, 64 migraciones (+3), módulo `EntidadesConfigurables` nuevo. Reutiliza `campos_personalizados` con ámbito `entidad_configurable` (cero duplicación de esquema). Admin define tablas tipadas (ej. "Pólizas", "Vehículos"), supervisor/gestor operan registros vía form dinámico auto-generado. CLAUDE.md §1.3 + §7.7 + §15.1 actualizadas para distinguir "módulo (código)" de "entidad configurable (datos tipados)" — preserva la línea roja sin bloquear el pedido del usuario.
- **2026-04-18 (Fase 25 cerrada — Design system base)**: 365 tests, Tailwind config con tokens semánticos (brand/accent/surface/ink/success/warning/danger/info), fuente Inter, 10 componentes Blade reutilizables en `resources/views/components/ui/`, `proyecto-dashboard` refactorizado como patrón de referencia para F26–F27.
- **2026-04-18 (Fase 26 cerrada — Refactor visual pantallas operativas)**: 369 tests, bandeja + bandeja-equipo + notificaciones + shell vista de trabajo reescritas con `x-ui.*`, badges/cards/tables/botones consistentes, cero cambios en lógica Livewire.
- **2026-04-18 (Fase 27 cerrada — Refactor visual admin/supervisión — CIERRE DE LOS 4 PEDIDOS DEL USUARIO)**: 383 tests, 20 pages shells con `x-ui.page-header`, admin-dashboard + selector-proyecto rediseñados, tabs de importaciones/catálogos con tokens semánticos, cero cambios Livewire. Cierra el plan correctivo F22–F27: (1) módulos desde cero = Entidades configurables §7.7 / (2) permisos granulares F22 / (3) restricción gestor F23 / (4) GUI F25–F27.
- **2026-04-29 (Fase 28 cerrada — Capa de integración wrapper SSO)**: 415 tests / 896 assertions, 65 migraciones, 19 módulos (+Integracion). `laravel/sanctum` instalado. 4 endpoints: `POST /api/auth/sso-handshake`, `GET /integracion/handshake`, `POST /api/auth/logout`, `GET /api/integracion/persona`. Token one-time (sha256, TTL 5min, consumido_en). Middleware `CspFrameAncestors` con `WRAPPER_DOMAIN`. Multi-tenancy respetado: preview API verifica `tieneAccesoAProyecto`. 29 tests nuevos (22 Feature, 7 Unit).
