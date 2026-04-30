<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:1e3a8a,100:0ea5e9&height=220&section=header&text=CRM%201.0&fontSize=72&fontColor=ffffff&fontAlignY=38&desc=Plataforma%20BPO%20multi-tenant%20%C2%B7%20Cobranza%20%C2%B7%20CX%20%C2%B7%20Venta%20%C2%B7%20Servicio&descSize=18&descAlignY=60&descAlign=50" alt="CRM 1.0"/>

<br/>

<a href="#"><img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2"/></a>
<a href="#"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12"/></a>
<a href="#"><img src="https://img.shields.io/badge/MySQL-8-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8"/></a>
<a href="#"><img src="https://img.shields.io/badge/Livewire-3-FB70A9?style=for-the-badge&logo=livewire&logoColor=white" alt="Livewire 3"/></a>
<a href="#"><img src="https://img.shields.io/badge/Tailwind-3-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind 3"/></a>

<br/>

<img src="https://img.shields.io/badge/Tests-450%20passing-22c55e?style=flat-square&logo=phpunit&logoColor=white" alt="Tests"/>
<img src="https://img.shields.io/badge/Assertions-993-22c55e?style=flat-square" alt="Assertions"/>
<img src="https://img.shields.io/badge/M%C3%B3dulos-22-1e293b?style=flat-square" alt="Módulos"/>
<img src="https://img.shields.io/badge/Migraciones-68-1e293b?style=flat-square" alt="Migraciones"/>
<img src="https://img.shields.io/badge/PHPStan-level%206%2B-1e293b?style=flat-square" alt="PHPStan"/>
<img src="https://img.shields.io/badge/license-MIT-1e293b?style=flat-square" alt="MIT"/>

</div>

---

## Visión

CRM operativo para un BPO que atiende **múltiples mandantes** desde una sola instancia.
Cada mandante contrata uno o más **proyectos**, y cada proyecto es de **un solo tipo de operación**: cobranza, atención al cliente, venta o servicio técnico.

> El núcleo es fijo. La variabilidad entre tipos vive en *Class Table Inheritance*; la variabilidad entre mandantes, en campos personalizados tipados.

<table>
<tr>
<td width="25%" align="center">
<img src="https://cdn.simpleicons.org/wallet/0ea5e9" width="40" height="40"/><br/>
<strong>Cobranza</strong><br/>
<sub>Promesas de pago,<br/>tramos de mora, casos</sub>
</td>
<td width="25%" align="center">
<img src="https://cdn.simpleicons.org/intercom/0ea5e9" width="40" height="40"/><br/>
<strong>CX</strong><br/>
<sub>Tickets, SLA,<br/>escalamiento</sub>
</td>
<td width="25%" align="center">
<img src="https://cdn.simpleicons.org/salesforce/0ea5e9" width="40" height="40"/><br/>
<strong>Venta</strong><br/>
<sub>Leads, embudo,<br/>cierre</sub>
</td>
<td width="25%" align="center">
<img src="https://cdn.simpleicons.org/servicestack/0ea5e9" width="40" height="40"/><br/>
<strong>Servicio</strong><br/>
<sub>Acción técnica,<br/>estados de campo</sub>
</td>
</tr>
</table>

---

## Arquitectura de tenancy

```mermaid
flowchart TD
  M[Mandante]:::tenant --> P1[Proyecto Cobranza]:::proj
  M --> P2[Proyecto CX]:::proj
  M --> P3[Proyecto Venta]:::proj
  M --> P4[Proyecto Servicio]:::proj

  P1 --> C1[Cartera]:::data
  P1 --> Pe1[Persona]:::data
  P1 --> Ca1[Caso]:::data
  Ca1 --> G1[Gestión]:::core
  Ca1 --> Co1[Compromiso]:::core
  P1 --> Cm1[Campaña]:::data
  Cm1 --> A1[Asignación]:::data

  classDef tenant fill:#1e3a8a,stroke:#1e40af,color:#fff,stroke-width:2px
  classDef proj fill:#0ea5e9,stroke:#0284c7,color:#fff
  classDef data fill:#f1f5f9,stroke:#cbd5e1,color:#0f172a
  classDef core fill:#fde68a,stroke:#f59e0b,color:#78350f
```

Aislamiento estricto vía `proyecto_id` + Eloquent Global Scope. La URL `/proyectos/{id}/...` es la fuente autoritativa del proyecto activo. El rol del usuario se evalúa **siempre** dentro del proyecto.

---

## Stack técnico

<table>
<thead>
<tr>
<th align="left">Capa</th>
<th align="left">Tecnología</th>
<th>&nbsp;</th>
</tr>
</thead>
<tbody>
<tr>
<td>Lenguaje</td>
<td>PHP 8.2 — <code>declare(strict_types=1)</code>, readonly VOs, enums tipados</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" width="28"/></td>
</tr>
<tr>
<td>Framework</td>
<td>Laravel 12 — Breeze stack Livewire</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original.svg" width="28"/></td>
</tr>
<tr>
<td>Base de datos</td>
<td>MySQL 8 · InnoDB · <code>utf8mb4_unicode_ci</code> · FKs reales</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" width="28"/></td>
</tr>
<tr>
<td>UI reactiva</td>
<td>Livewire 3 + Alpine.js</td>
<td><img src="https://cdn.simpleicons.org/livewire/FB70A9" width="28"/></td>
</tr>
<tr>
<td>Estilos</td>
<td>Tailwind CSS 3 (design system propio · F29-bis)</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/tailwindcss/tailwindcss-original.svg" width="28"/></td>
</tr>
<tr>
<td>Build</td>
<td>Vite</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/vitejs/vitejs-original.svg" width="28"/></td>
</tr>
<tr>
<td>Cola</td>
<td>Redis + Laravel Queue (cola <code>imports</code> dedicada)</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/redis/redis-original.svg" width="28"/></td>
</tr>
<tr>
<td>Auth</td>
<td>Breeze · Sanctum (SSO wrapper, token one-time SHA-256)</td>
<td><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original.svg" width="28"/></td>
</tr>
<tr>
<td>Calidad</td>
<td>Pint · Larastan nivel 6+ (nivel 8 en <code>Domain/</code>) · PHPUnit 11</td>
<td><img src="https://cdn.simpleicons.org/phpunit/3776AB" width="28"/></td>
</tr>
</tbody>
</table>

---

## Mapa de módulos

```mermaid
flowchart LR
  subgraph Nucleo["Núcleo"]
    direction TB
    Te[Tenancy]
    Us[Usuarios]
    Pe[Personas]
    Co[Contactos]
    Cs[Casos]
    Ge[Gestiones]
    Cm[Compromisos]
    Cp[Campañas]
    As[Asignaciones]
    Cat[Catálogos]
  end

  subgraph Conf["Configuración"]
    direction TB
    CP[CamposPersonalizados]
    EC[EntidadesConfigurables]
    Im[Importaciones]
    Au[Auditoría]
    No[Notificaciones]
    Re[Reportes]
    In[Integración]
  end

  subgraph Esp["Especializaciones por tipo"]
    direction TB
    Cob[Cobranza]
    Cx[CX]
    Vt[Venta]
    Sv[Servicio]
  end

  Cs --> Cob
  Cs --> Cx
  Cs --> Vt
  Cs --> Sv
  Cm --> Cob
  Cm --> Cx
  Cm --> Vt
  Cm --> Sv
  Ge -.eventos.-> Cob
  Ge -.eventos.-> Cx
  Ge -.eventos.-> Vt
  Ge -.eventos.-> Sv
  CP -.valida.-> Ge
  CP -.valida.-> Cs
  CP -.valida.-> Cm
  Im -.usa UseCases.-> Cob
  Im -.usa UseCases.-> Cx
  Im -.usa UseCases.-> Vt
  Im -.usa UseCases.-> Sv
```

> Los módulos se comunican únicamente a través de **interfaces de servicio** (`Domain/Contracts`) y **eventos de dominio**. Está prohibido importar modelos Eloquent de un módulo desde otro.

---

## Modelo de dominio

```mermaid
erDiagram
  MANDANTE ||--o{ PROYECTO : "contrata"
  PROYECTO ||--o{ CARTERA : "tiene"
  PROYECTO ||--o{ PERSONA : "aísla"
  PROYECTO ||--o{ CAMPANA : "ejecuta"
  PERSONA  ||--o{ CONTACTO : "posee"
  PERSONA  ||--o{ CASO : "origina"
  CARTERA  ||--o{ CASO : "agrupa"
  CASO     ||--|| CASO_COBRANZA : "CTI"
  CASO     ||--|| CASO_CX : "CTI"
  CASO     ||--o{ GESTION : "registra"
  CASO     ||--o{ COMPROMISO : "asume"
  GESTION  }o--|| TIPO_GESTION : "tipifica"
  GESTION  }o--|| RESULTADO : "concluye"
  GESTION  ||--o| COMPROMISO : "puede generar"
  COMPROMISO ||--|| COMPROMISO_PROMESA_PAGO : "CTI"
  COMPROMISO ||--|| COMPROMISO_RESOLUCION_TICKET : "CTI"
  CAMPANA  ||--o{ ASIGNACION : "distribuye"
  CASO     ||--o{ ASIGNACION : "se asigna"

  MANDANTE { bigint id PK }
  PROYECTO { bigint id PK ulid public_id enum tipo }
  PERSONA  { bigint id PK ulid public_id string identificacion }
  CASO     { bigint id PK ulid public_id enum estado }
  GESTION  { bigint id PK datetime creada_en text notas }
  COMPROMISO { bigint id PK enum estado date fecha_vencimiento }
```

---

## Estructura de cada módulo

```
app/Modules/<Modulo>/
├── Domain/
│   ├── Entities/         · agregados puros, sin dependencias de framework
│   ├── ValueObjects/     · Identificacion, MontoCompromiso, DiasMora, ...
│   ├── Events/           · eventos de dominio síncronos
│   ├── Exceptions/
│   └── Contracts/        · interfaces de repositorio y servicios
├── Application/
│   ├── UseCases/         · execute(InputDTO): OutputDTO
│   ├── DTOs/
│   └── Listeners/        · suscriptores de eventos de otros módulos
└── Infrastructure/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   └── Livewire/
    ├── Persistence/
    │   ├── Models/       · Eloquent
    │   └── Repositories/ · implementación de Domain/Contracts
    └── Providers/        · binding y registro
```

---

## Flujo asíncrono de importaciones

Las importaciones masivas no bloquean al supervisor. El UseCase preprocesa el archivo, encola un job, y la UI hace polling Livewire cada 2 segundos.

```mermaid
sequenceDiagram
  autonumber
  participant U as Supervisor
  participant L as Livewire UI
  participant DB as MySQL
  participant Q as Queue (Redis)
  participant W as Worker imports
  U->>L: Sube CSV + elige modo
  L->>DB: importacion (estado=preparada)
  L->>Q: dispatch EjecutarImportacionJob
  L-->>U: Vista con polling 2s
  W->>Q: pop job
  W->>DB: GET_LOCK("import:{id}")
  loop chunks de IMPORTS_BATCH_SIZE
    W->>DB: lee filas pendientes
    W->>DB: aplica modo (merge / skip / overwrite)
    W->>DB: actualiza contadores
  end
  W->>DB: estado = completada
  L-->>U: progreso en vivo
```

Modos disponibles: `merge` · `skip_duplicados` · `overwrite`.

---

## Estado actual

<table>
<tr>
<td align="center" width="20%">
<img src="https://img.shields.io/badge/68-1e3a8a?style=for-the-badge" alt="68"/><br/>
<sub>Migraciones</sub>
</td>
<td align="center" width="20%">
<img src="https://img.shields.io/badge/22-1e3a8a?style=for-the-badge" alt="22"/><br/>
<sub>Módulos activos</sub>
</td>
<td align="center" width="20%">
<img src="https://img.shields.io/badge/450-22c55e?style=for-the-badge" alt="450"/><br/>
<sub>Tests</sub>
</td>
<td align="center" width="20%">
<img src="https://img.shields.io/badge/993-22c55e?style=for-the-badge" alt="993"/><br/>
<sub>Assertions</sub>
</td>
<td align="center" width="20%">
<img src="https://img.shields.io/badge/4-0ea5e9?style=for-the-badge" alt="4"/><br/>
<sub>Proyectos demo</sub>
</td>
</tr>
</table>

Funcionalidades operativas completas — F1 a F31:

| Fase | Área |
|------|------|
| F1 | Multi-tenant: scope automático, URL autoritativa, selector de proyecto |
| F2–F5 | Cobranza · CX · Venta · Servicio (CTI completo por tipo) |
| F6–F10 | Permisos, admin global, catálogos, gestión de usuarios por proyecto |
| F11 | Importaciones por tipo de operación |
| F12–F14 | Auditoría exhaustiva, notificaciones in-app |
| F15–F18 | Equipos, reportería, asignaciones masivas, bandeja del equipo |
| F20–F23 | Reasignación entre equipos, permisos granulares CRUD por cartera, hardening |
| F24 | Entidades configurables por proyecto/cartera |
| F25–F27 | Design system inicial y refactor visual |
| F28 | Capa de integración SSO wrapper (Sanctum, token one-time) |
| F29-bis | Refactor visual literal del mockup standalone |
| F30 | Validaciones avanzadas y auto-fill en campos personalizados |
| F31 | Importaciones asíncronas con 3 modos y polling en vivo |

---

## Puesta en marcha

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed

composer dev
```

`composer dev` levanta server, queue listener, vite y pail concurrentemente.

Para procesar importaciones en background:

```bash
php artisan queue:work --queue=imports
```

---

## Calidad

```bash
composer test                 # Suite completa
./vendor/bin/pint             # Formateo automático
./vendor/bin/phpstan analyse  # Análisis estático
```

Reglas internas:

- Cobertura mínima del 90 % en `Domain/`.
- Cada módulo scoped tiene al menos un test que verifica que **no se fuga data entre proyectos**.
- Pint + PHPStan + PHPUnit en verde antes de mergear.

---

## Principios no negociables

| | Regla |
|---|---|
| <img src="https://cdn.simpleicons.org/cloudflare/0ea5e9" width="18"/> | Aislamiento por proyecto — `proyecto_id` obligatorio en toda tabla operativa |
| <img src="https://cdn.simpleicons.org/keycdn/0ea5e9" width="18"/> | Rol por proyecto — solo `ADMIN_GLOBAL` es cross-project |
| <img src="https://cdn.simpleicons.org/databricks/0ea5e9" width="18"/> | Tipo único por proyecto — sin mezclar operaciones |
| <img src="https://cdn.simpleicons.org/codefactor/0ea5e9" width="18"/> | Núcleo fijo — variabilidad por CTI o campos personalizados, nunca módulos dinámicos |
| <img src="https://cdn.simpleicons.org/contentful/0ea5e9" width="18"/> | La gestión es el activo — datos estructurados, jamás texto libre parseado |
| <img src="https://cdn.simpleicons.org/fastlane/0ea5e9" width="18"/> | Una vista, una acción — máximo 3 clics por tarea operativa |

---

## Licencia

Distribuido bajo licencia **MIT**. Ver [LICENSE](LICENSE) para el texto completo.

<div align="center">

<sub>Construido en Sincelejo, Colombia · <a href="https://github.com/Saimper">@Saimper</a></sub>

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0ea5e9,100:1e3a8a&height=80&section=footer" alt="footer"/>

</div>
