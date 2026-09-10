# Local UI and operational review — 2026-09-09

## Production parity follow-up — 2026-09-10

Objective: compare the local assignment features and other completed work with
GitHub and the running production application, and prepare an evidence-based
handoff for Joel.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Locate the project and review its existing progress | Completed | `/Users/pc/crm-bpo`; this report, `AGENTS.md`, `CLAUDE.md`, and project memory reviewed. |
| Compare local branches, GitHub, and production | Completed | PRs #17 and #18 are merged. Production and `origin/main` are at `8e0d9ad`; the original local branch is six commits behind, with none ahead. No local branch contains commits absent from `origin/main`. |
| Check assignment visibility and production prerequisites | Completed | Confirmed the browser session's GESTOR role, disabled production autoassignment, matching permissions/schema, working queues and cron, configuration/data differences, and the unapplied nginx timeout configuration. |
| Prepare the reviewer handoff and validate any corrections | Completed | Findings and operational steps are in `DOCS/PRODUCTION_PARITY_2026_09_10.md`. Local suite: 1,696 passed, 14 deprecated, 5 skipped, 5,363 assertions; Pint and PHPStan passed; zero known pending tenant leaks. No application change or production mutation was necessary for this audit. |

Production inspection is read-only. Database contents, credentials, and local
demo configuration are not publication artifacts. Token usage, remaining
context, and cost counters are not exposed in this session and are not estimated.

The audit is complete on `chore/production-parity-audit-20260910`, based on
current `origin/main`. Next: Joel reviews the documented configuration and data
repairs and the remaining nginx adjustment. PR #17 is already merged and
deployed; the historical handoff below is superseded by the September 10 report.

## Follow-up: persisted name encoding

The reported name was stored with reversible double encoding (`U+00C3 U+0081`
instead of `U+00C1`). The local database, connection, result charset, and person
name columns all use `utf8mb4`, with `utf8mb4_unicode_ci` collation. This was
persisted text corruption, not missing database support for accented characters.

After a dry run and a private JSON backup of the 14 affected rows, the existing
`php artisan importaciones:reparar-encoding --proyecto=8` command repaired seven
person records and seven custom field values. A direct read verified the
reported name's corrected UTF-8 bytes. A second dry run found zero remaining
reversible corrections. The backup is retained under `storage/app/private/`
as `encoding-backup-project-8-20260909-160641.json` with mode `0600`.

Twenty person records and 23 custom field values still contain the ambiguous
`ÃÑ` pattern documented in the project memory. These require an authoritative
source because multiple original letters collapsed into the same sequence.
They were preserved rather than guessed. No schema or application code change
was needed: both CSV and XLSX readers already normalize reversible corruption.

The normalizer, CSV/XLSX readers, and repair-command tests completed with zero
failures: 25 passed, 14 with existing PHP 8.5 deprecation notices, 62 assertions.
The command tests verify project isolation, dry-run behavior, preservation of
ambiguous values, and unchanged business update timestamps.

## Starting point

Reviewed `AGENTS.md`, `CLAUDE.md`, and the four CRM memory files. The working tree
was clean on `feat/ola-00-recuperar-red-de-regresion`, at `57535a6`. The six waves
and the isolation remediation were already implemented locally. Some memory
paragraphs still described completed work as pending; the current code and tests
were used to resolve those contradictions.

The standalone HTML mockup is absent. This change retains the existing slate and
steel-blue tokens, typography, SVG icons, Blade components, and Laravel/Livewire
stack. No dependency or database migration was added.

## Corrections

- Login: ordinary users now land on the project selector instead of the forbidden
  administration route. Administrators retain their administrative destination;
  an intended destination is preserved. The root URL enters through `/dashboard`.
- Custom roles: an active custom role assignment now contributes to project
  discovery and project access. A role must belong to that project, remain active,
  and not be archived. Revoked assignments stop granting access.
- Project selector: archived/inactive projects and inactive clients no longer
  affect the single-project redirect or appear as available destinations.
- Search: requires permission to open the work view, excludes cases belonging to
  archived people, and retains project and portfolio restrictions. The button no
  longer advertises searching interactions, which the query does not implement.
- Header and contact navigation: permission checks match the destination routes;
  the notification counter also checks permission on the server.
- Development startup: `composer dev` now starts a separate `imports` listener.
  Previously it only consumed the default queue, leaving imports waiting unless
  a second worker was started manually.

## Visual changes

Shared navigation now includes an explicit project overview, clearer active
project identity, rounded selected items, and active states on case/person edit
screens. The login and application shell share the same brand treatment.

The compact layout keeps search available, adds a sidebar backdrop and close
button, handles Escape and navigation, restores focus, and traps focus while the
sidebar or search dialog is open. Hidden mobile navigation is removed from the
keyboard path. Header names truncate; page actions wrap; tabs scroll locally;
drawers and notifications fit smaller viewports. Reduced motion is respected.

The work-view columns adapt sooner and management/commitment fields use the width
of their container rather than the window width. Primary management controls have
associated labels. Shared table footers use the pagination component styling.
Inline progress fills now have block layout, making their percentage widths
visible. Dashboard tiles and period controls reuse the existing design system.
Inline style attributes decreased from 763 to 733; the baseline was tightened.

## Validation

The new navigation test opens every project sidebar destination for GESTOR,
SUPERVISOR, and AUDITOR across cobranza, CX, venta, and servicio. Additional
regressions cover authentication destinations, custom-role access/revocation,
isolation, search permissions, and archived records. Two previously skipped
authentication tests were restored. The archived-project selector fixture now
keeps two active projects so it exercises the list instead of redirecting.

Browser checks used the existing local demo account: login to its project,
dashboard period changes, inbox, global search, work-view navigation, form layout,
and compact navigation under browser zoom. No management record, commitment,
assignment, or customer data was changed during browser checks.

Local `migrate:status` reports the migrations applied. `schedule:list` lists nine
scheduled tasks. Registering a schedule is distinct from running its worker.

| Check | Result |
| --- | --- |
| `composer test` | 1,670 passed, 14 deprecated, 5 skipped; no failures; 5,289 assertions |
| `./vendor/bin/pint` and final `--test` | Passed |
| `./vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | No errors with the existing configuration and baseline |
| `bin/verificar-fugas.sh` | Zero known pending leaks; isolation regressions run in the full suite |
| `npm run build` | Passed; local production assets regenerated |
| `php artisan view:cache` | Passed |
| `composer validate --no-check-publish` | Valid |
| `git diff --check` | Passed |

MySQL tests and PHPStan required approved execution outside the sandbox because
the sandbox denied the local database connection and PHPStan's local socket.
The test database was `crm_test`; development data was not reseeded or migrated.

## Remaining operational checks

- Restart `composer dev` to use the added imports listener. This review did not
  start workers that could consume existing customer jobs.
- Local scheduled processing requires `php artisan schedule:work`; production
  requires its existing cron/worker configuration. This review inspected task
  registration, not a production execution cycle or external webhook delivery.
- Existing skipped tests and PHP 8.5 deprecations remain visible in suite output.
  Domain coverage and the level-8 Domain analysis requirement were not
  measured by this UI review; the repository's configured PHPStan gate is level 6
  with its existing baseline.
- This report records local validation. Publication is tracked in PR #17;
  production migration and deployment remain with the reviewer.

## Production handoff

PR #17 includes the earlier work from closed, unmerged PRs #11–#16, the subsequent
operational and isolation changes, and this UI review. It contains 18 new
migrations relative to `main`; the UI follow-up itself adds none.

Merging into `main` triggers the existing production workflow after CI passes.
Review the complete migration set before merging, particularly the assignment
deduplication, removal of `asignaciones.campana_id`, and removal of `campanas`.
These data changes cannot be undone by simply reverting application code.
Verify the workflow's database backup prerequisites and retain a restorable
backup before deployment. The workflow leaves maintenance enabled if migrations
or permission seeders fail and prints recovery instructions.

Confirm that production consumes both the default queue and the dedicated
`imports` queue, and that its scheduler is running. The `composer dev` change
only starts the additional listener locally; production process management must
provide it separately. Review `DOCS/OPERACION_DESCARGAS.md` for download and
proxy configuration checks. After deployment, check login and project access
for each relevant role, search and work-view navigation, an authorized import,
exports, queue processing, and scheduled processing.

Local database repairs and their private backup are not distributed through
Git. For affected production data, identify the correct production project ID,
take a backup, and run `php artisan importaciones:reparar-encoding
--proyecto=<id> --dry-run`. After reviewing the proposed corrections, run the
same command without `--dry-run` and repeat the dry run to verify completion.
Do not assume the local project ID is the production ID. Reconcile remaining
ambiguous characters against the authoritative source file.

For local testing on another machine, copy `.env.testing.example` to
`.env.testing`, configure a separate `crm_test` database, and verify the target
database before running Artisan with `--env=testing`. The real environment
files, credentials, database contents, and private backups stay outside Git.
