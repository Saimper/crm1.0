# Agent Instructions: CRM 1.0

## Critical Context
- **Multi-tenancy:** Every operational table MUST include `proyecto_id`. Eloquent Global Scopes are active.
- **Domain Isolation:** Modules in `app/Modules/` MUST NOT import Eloquent models from other modules. Use `Domain/Contracts` (interfaces) or Domain Events.
- **CTI Pattern:** Cases and Commitments use Class Table Inheritance (e.g., `casos` + `casos_cobranza`). Never use polymorphic relations for core operational data.
- **Immutability:** Never physically delete `Gestiones`. Use soft deletes for historical entities only.
- **Project-scoped roles:** Only `ADMIN_GLOBAL` is cross-project. All other roles are evaluated within a project context.

## Architecture
- **Structure:** `Domain` (Entities, VOs, Contracts), `Application` (UseCases, DTOs, Listeners), `Infrastructure` (Models, Controllers, Livewire).
- **Service Communication:** Use `UseCases` for business logic. Avoid logic in Controllers or Livewire components.
- **Authoritative Source:** The URL `/proyectos/{proyecto_id}/...` is the source of truth for the active project.
- **22 Modules:** Tenancy, Usuarios, Personas, Contactos, Casos, Gestiones, Compromisos, Campanas, Asignaciones, Catalogos, Clientes, Cobranza, Cx, Venta, Servicio, CamposPersonalizados, EntidadesConfigurables, Importaciones, Auditoria, Notificaciones, Reportes, Integracion.
- **DB:** MySQL 8, `utf8mb4_unicode_ci`, real FK constraints.

## Developer Workflow
- **Setup:** `composer setup` runs install, env, key, migrate, npm install, and build in one command.
- **Dev server:** `composer dev` starts server, queue listener, pail logs, and Vite concurrently. Requires Redis.
- **Verification order:** `composer test && ./vendor/bin/pint && ./vendor/bin/phpstan analyse`
- **Single test:** `php artisan test tests/Feature/Modules/Cobranza/PaymentPromiseTest.php` (or `--filter=methodName`)
- **Test DB:** `crm_test` (configured in `phpunit.xml`). Must exist before running tests.
- **Imports:** Massive imports use a dedicated `imports` queue (Redis-backed). Run `php artisan queue:work --queue=imports` to process. UI polls Livewire every 2s.
- **Import modes:** `merge`, `skip_duplicados`, `overwrite`. Jobs use `GET_LOCK("import:{id}")` for safety.
- **Styling:** Adhere to the design system in `N_cleo CRM _standalone_.html`. Use Tailwind arbitrary values matching the mockup precisely.

## Constraints
- **Larastan:** Level 6+ required (Level 8 in `Domain/`).
- **Coverage:** Minimum 90% in `Domain/`. Each scoped module must have a test verifying no data leakage between projects.
- **Commits:** Strict Conventional Commits in English.
- **Language:** Code/Docs in English; UI/Error messages in Spanish.
