# Production parity and assignment handoff — 2026-09-10

## Result

No completed application code was missing from GitHub or production at the time
of inspection. The visible assignment differences came from the active user's
project role, project configuration, and operational data. Several local
configuration changes and historical data repairs still need an operational
handoff; another deployment of the same source will not apply them.

This report contains code references and configuration findings. Customer
records, database snapshots, credentials, user identifiers, and detailed
operational volumes are excluded from the repository.

## Verified source and deployment

| Check | Result |
| --- | --- |
| Original local branch | `feat/ola-00-recuperar-red-de-regresion`, `99c72da`; clean before this audit. |
| GitHub integration | [PR #17](https://github.com/Saimper/crm1.0/pull/17) merged on September 9, followed by [PR #18](https://github.com/Saimper/crm1.0/pull/18). |
| GitHub `main` and production | Both at `8e0d9ad75b92b106477cb9077a6329f81281a3b3`. Production working tree clean. |
| Unpublished local branches | `git log --branches --not origin/main` returned no commits before creating this documentation change. The original local branch has zero commits ahead and six behind `origin/main`. |
| Deployment workflow | [Run 34406268918](https://github.com/Saimper/crm1.0/actions/runs/34406268918) completed successfully, including the deploy job. The initial PR #17 deployment failure was already fixed by `a445825`. |
| Database migrations | All 108 applied, including autoassignment, unique ownership per project/case, and campaign removal. |
| Assignment schema | Local and production columns and indexes match. |
| Permissions | Active permission codes and the assignment role/permission matrix match local. |
| Build | Manifest present, all referenced files present, no Vite `hot` file; production route/config caches present. |

The assignment use cases, domain, bulk-assignment screen, and navigation in the
original local branch are already contained in production. Restoring the old
campaign branches would reintroduce a requirement deliberately removed by F43.

## Assignment visibility

Two separate conditions explain the observed behavior:

1. **Bulk assignment and reassignment require `asignaciones.reasignar`.**
   The production browser session inspected had the `GESTOR` role in its active
   project. Its permission evaluation correctly denied bulk assignment, and
   opening the bulk-assignment URL returned 403. The same role is denied locally.
   Supervisors and authorized administrators have this permission; a custom
   role must explicitly include it. Do not broaden the standard `GESTOR` role
   to make the menu appear.
2. **Taking an unowned account also requires `permite_autoasignacion`.**
   The inspected project enables it locally and disables it in production.
   `GESTOR` has `asignaciones.autoasignarse`, but that permission alone does not
   override the project flag. The production inbox rendered "Sin asignaciones"
   and did not offer the unowned-account tab. Production had no assignment rows
   in the audited snapshot; local assignment history includes demo activity.

Relevant deployed entry points:

| UI | Route | Prerequisite |
| --- | --- | --- |
| Mi Bandeja / Tomar cuenta | `/proyectos/{proyecto_id}/bandeja` | Own-inbox permission; taking accounts additionally requires the self-assignment permission and project flag. |
| Asignación masiva | `/proyectos/{proyecto_id}/asignaciones/masiva` | Reassignment permission; active team and members for distribution. |
| Reasignación | `/proyectos/{proyecto_id}/asignaciones/reasignar` | Reassignment permission and eligible team assignments. |
| Bandeja del equipo | `/proyectos/{proyecto_id}/bandeja/equipo` | Team-inbox permission. |

The project flag can already be edited in **Administración → Proyectos → Editar
→ Auto-asignación al gestionar**. It defaults to false intentionally, because
some operations require supervisors to distribute all accounts. Enabling it
does not create historical assignments or distribute the entire portfolio.
The deployment only migrates its column; it does not choose this business policy.

Code: `routes/web.php`, `resources/views/layouts/app.blade.php`,
`app/Modules/Asignaciones/Application/UseCases/AutoasignarCaso.php`, and
`database/migrations/2026_09_07_130000_tenancy_alter_proyectos_add_autoasignacion.php`.

## Other confirmed configuration and data differences

These findings concern the inspected operating project and its mandante. They
are database configuration, rather than absent modules or migrations.

| Area | Local reference | Production finding | Reviewer action |
| --- | --- | --- | --- |
| Field groups | Six groups with 34 fields grouped and ordered | No groups; those 34 field order/group settings differ | Review and configure groups and ordering through the project configurator. |
| Note templates | Three templates | None | Review wording and configure the intended templates. |
| Management-type/result combinations | Eleven combinations | None | Review the intended matrix. An empty matrix permits results under the existing compatibility fallback; it does not block all management. |
| Last-payment field | `ultimo_pago` declared `fecha` | Declared `moneda`; existing values remain in text | The date-conversion dry run succeeded. Correct definition and values together using the existing command after backup. |
| Monetary values | Historical local repairs | `saldo_total` and `cuota_de_pago` contain values outside their declared monetary column | Placement dry runs found reparable values. Moving the definition alone does not repair storage. |
| Regional settings | `America/Panama` for the audited mandante | `UTC` | Confirm the operating timezone in mandante administration; it affects day boundaries and reporting. |
| Imported name encoding | Earlier local repair completed | Encoding dry run still found reversible corruption, plus ambiguous values | Apply only reviewed reversible repairs after backup; reconcile ambiguous text with its authoritative source. |
| Operational history | Demo assignments, interactions, commitments, and extra demo users | Real production history differs | Do not copy demo activity or run `EscenarioOperativoCobranzaSeeder` in production. |

Catalog and template tables are already deployed. The current workflow seeds
only roles, permissions, and their base associations. It does not copy local
project settings, custom catalogs, or repaired customer values.

### Existing repair commands

Resolve production field IDs by **project and field code**; never assume a local
numeric ID is the production ID. Recheck the current state and retain a
restorable backup before applying any repair. Inspect each dry run first:

```sh
php artisan campos:convertir-tipo <production_last_payment_field_id> fecha --dry-run
php artisan campos:recolocar-valores <production_balance_field_id> --dry-run
php artisan campos:recolocar-valores <production_installment_field_id> --dry-run
php artisan importaciones:reparar-encoding --proyecto=<production_project_id> --dry-run
```

The project-wide placement dry run returned a failure because `ultimo_pago`
cannot be parsed as currency. The separate date-conversion dry run succeeded.
Resolve that field's type before interpreting a project-wide placement check.
After an approved correction, repeat its dry run and verify the work view and
exports. Ambiguous encoding requires source reconciliation; do not force it.

No repair, assignment, role change, or project-setting change was applied by
this audit. Read-only database probes used read-only transactions; command
probes used `--dry-run`.

## Production operations

| Check | Observed state |
| --- | --- |
| Web services | nginx and PHP 8.2 FPM active; maintenance mode off. |
| Queue consumers | `crm-worker.service` consumes `default`; `crm-worker-imports.service` consumes `imports`. Both run as `www-data`, are active, and restart automatically. |
| Queue backlog | Both inspected queues empty; no failed jobs in the snapshot. |
| Scheduler | Nine registered tasks; `/etc/cron.d/crm-scheduler` invokes `schedule:run` every minute. Thirty cron invocations were observed in the preceding 30 minutes. |
| Application errors | No production ERROR-or-higher log entries found after the latest deployment in the inspected Laravel logs. |
| Download timeout gap | nginx FastCGI read/send timeouts are 120 seconds; the download runbook specifies 600 seconds. No explicit `send_timeout` was present in the loaded configuration. |
| FPM termination | Effective `request_terminate_timeout = 0s`; `pm.max_children = 15`. |

The nginx timeout change from `DOCS/OPERACION_DESCARGAS.md` remains unapplied.
It is an infrastructure handoff: the application workflow does not install or
reload nginx configuration. Review the runbook, validate with `nginx -t`, and
verify a representative long export after the chosen configuration is applied.
This audit did not perform a load test, trigger scheduled jobs, or deliver
external webhooks. A running scheduler is not proof of every task's outcome.

## Validation and next action

Validation ran locally against `crm_test`, whose connection was checked before
the suite. Development and production data were not reseeded.

| Check | Result |
| --- | --- |
| `composer test` | 1,696 passed; 14 deprecated; 5 skipped; zero failures; 5,363 assertions. |
| `./vendor/bin/pint` | Passed; no application source changes. |
| `./vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | No errors with the repository's existing configuration/baseline. |
| `bin/verificar-fugas.sh` | Zero known pending leaks; the pending-leak group is empty. |
| Production browser | Observed the restricted bulk-assignment response and the empty personal inbox using the existing session. |

The audit and handoff are complete on
`chore/production-parity-audit-20260910`, based on current `origin/main`.
Joel can use this documentation change for a review PR. Application code does
not need to be resubmitted from the old branches. The remaining work is to
review the production business configuration, apply the selected data repairs
with a backup, and complete the nginx/download check. A documentation merge
does not perform these operations.

The original UI review remains in `DOCS/LOCAL_REVIEW_2026_09_09.md`. Detailed
scoped evidence is retained locally under the ignored private storage directory
`storage/app/private/production-parity-20260910/`; it is not part of this PR.
Domain coverage and level-8 Domain analysis were not newly measured. Token
usage, remaining-context, and cost counters are not exposed in this session.
