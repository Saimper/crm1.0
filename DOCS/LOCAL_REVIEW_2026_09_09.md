# Local UI and operational review — 2026-09-09

## Pull request handoff — 2026-09-10

Objective: publish the completed customer workspace/history/settings changes and
automatic-import-return correction for Joel (`@Saimper`) to review and deploy.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Confirm base and scope | Completed | Upstream PR 19 is merged. Upstream main has the same tree as the previously tested base; branch fast-forwarded without changing application code. |
| Prepare review and deployment notes | Completed | Full scope and three migrations documented in CUSTOMER_WORKSPACE_2026_09_10.md. Existing verification: 1,848 passed / 6,246 assertions; Pint, PHPStan and builds passed. |
| Publish branch and hand off to Joel | Completed | Upstream PR #20 opened against main from the existing fork. Joel (@Saimper) is mentioned in the review/deployment instructions; GitHub denied formal reviewer assignment for the fork author's account. |

PR: https://github.com/Saimper/crm1.0/pull/20. Application commit: `574fb84`.
The PR is ready for Joel's review; it has not been merged or deployed. Local
validation remains the completed suite recorded above; GitHub CI is reported
separately on the PR.

Private backups, recovery scripts and business data remain excluded from Git.
Production deployment remains with Joel after review. Usage/context/cost counters
are unavailable.

## Automatic account return — 2026-09-10

Objective: treat a newly imported debt from an archived/inactive portfolio as
returning operational work. Reincorporate it automatically into the selected
active portfolio, preserving identity, ownership and historical evidence.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Identify the omitted import and root cause | Completed | Imports 30 and 31 to active portfolio 222 both omitted the same 55 rows (matching payload hashes). The previous checkbox defaulted to false and non-update modes skipped archived matches. |
| Implement automatic reincorporation | Completed | Removed the extra UI choice; enqueue persists processing authorization. All six modes support return with project/identity/portfolio permission checks; incoming data updates returning accounts, while MERGE retains fill-empty behavior. |
| Recover the affected rows | Completed | Recovery import 32 processed the exact 55 omitted rows from 31: 55 updated, zero inserted/invalid/omitted/duplicate rows. Portfolio 222 now has 233 active accounts. Original attempts 30/31 remain unchanged. |
| Verify and record handoff | Completed | Full suite: 1,848 passed / 6,246 assertions. Pint, PHPStan (application level 6 and changed Domain level 8), asset build, Blade compilation and diff check passed. Recovery evidence verifies unchanged identity, assignments, activity and commitments plus 55 matching financial snapshots. |

Before recovery, a fresh private local database dump was completed at
`storage/app/private/workspace-review-20260910/before-automatic-return-31.sql`
(270,795,236 bytes). The recovery copied only the 55 matching omitted payloads
into a new audited import, using the original uploader, mappings, INSERT mode and
input format through the normal enqueue/worker flow. A repeated dry-run detects
completed import 32 and dispatches nothing.

The 55 returning accounts retain their case/person identities, 25 assignments,
110 management records and 11 commitments/payment promises. The original
financial details are preserved in 55 portfolio movement snapshots. Global and
project case/person/activity counts did not change; operational accounts rose
from 178 to 233. Affected original import rows and metadata remain byte-equivalent
under the recorded evidence hashes.

Related import checks also fixed destination permission enforcement before
queueing and filtered the portfolio selector by the specific import permission.
MERGE now preserves filled custom fields, including zero/false and repeated rows
within a batch, and retains existing CX subjects and sale estimates. Existing
native numeric-zero fill behavior is retained.

Final verification retains 14 existing deprecation notices and 5 skipped tests,
with no test failures. The updated early-rejection guards separately passed
22 tests / 109 assertions. PHPStan required `--memory-limit=512M` after the
default 128 MiB worker limit was exhausted; the completed analysis has no errors.
Numerical Domain coverage remains unavailable without Xdebug/PCOV.

Recovery scripts, pinned BEFORE evidence, successful AFTER verification and
final check logs are retained in
`storage/app/private/workspace-review-20260910/`, using the
`crm-return-accounts-` and `crm-automatic-return-` prefixes. The retained verifier
can read its sibling snapshot with `--after=32`; it was also checked after copying.
The task is complete locally. Next: review import 32 or the 233 active accounts
in the portal; no additional upload is needed to recover these 55 rows.

This follows the user's explicit correction to the previous opt-in behavior.
Production publication is outside this task. Usage/context/cost counters are
unavailable.

## Archived activity follow-up — 2026-09-10

Objective: verify the accepted individual ownership/collaboration and supervisor
Historical custody behavior, remove archived portfolio activity from operational
commitments and other related surfaces, and rename the unified entry to Clientes.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Confirm ownership and Historical custody | Completed | One responsible advisor; collaboration preserves owner and actual author. Local GESTOR collaboration/Historical grants remain off; supervisor custody is on. Corrected mixed-role widening of Historical portfolio access. |
| Correct archived activity visibility | Completed | Commitments/list/summary/CSV, management CSV, four dashboards, custom reports, notification reminders and arrears bands corrected. Integration preview, incoming screen-pop and direct work links exclude archived-only accounts/persons. |
| Rename the unified entry | Completed | Navigation, page heading, search and explanatory references now use Clientes. |
| Verify behavior and record handoff | Completed | Final full suite: 1,816 tests / 5,934 assertions; Pint and PHPStan passed. Asset build, Blade compilation and isolation ratchet passed. Browser verified Clientes menu and zero operational commitments. |

Read-only local verification: project 8 retains 8,429 historical accounts,
481 commitments and 4,359 management records; the corresponding operational
counts are all zero. No permissions, assignments, commitments, activity or
historical records were changed. Detailed behavior is recorded in
`DOCS/CUSTOMER_WORKSPACE_2026_09_10.md`.

Final `composer test` retained 14 existing deprecation notices and 5 skipped
tests, with no failures. Targeted access/integration checks passed 19 tests /
62 assertions; report/notification/commitment/export/cron checks passed 123 /
426. The final full suite includes all four new screen-pop regressions, direct
archived-work-view rejection/reactivation, and the corrected query-builder
mock. PHPStan passed across the application; this follow-up does not modify
Domain code. No numerical coverage measurement is available without Xdebug/PCOV.
The isolation ratchet reports zero known leaks and an empty dedicated group.
`git diff --check` passed. The local commitment query was inspected with EXPLAIN;
portfolio/project and parent joins use existing indexes, while MySQL chose a
scan for the small local commitment table (481 rows).

Logs are retained privately under
`storage/app/private/workspace-review-20260910/` with the
`crm-archived-activity-` prefix. No new migrations or dependency changes were
needed. This follow-up is complete; next is the user's local review. Any desired
advisor collaboration or Historical grants can be enabled through project
Roles y permisos. They were not changed automatically.

No production publication or historical data deletion is part of this follow-up.
Usage/context/cost counters are unavailable.

## Customer workspace implementation — 2026-09-10

Objective: implement the user's accepted unified person/debt workspace, Historical
area, regional settings, editable base roles, optional advisor teams, and retirement
of the empty Banco Azteca entity. Branch:
`feat/customer-workspace-history-settings`.

| Milestone | Status | Scope |
| --- | --- | --- |
| Record decisions and define ownership/history behavior | Completed | One debt and one responsible advisor; project-grantable collaboration. Supervisor custody of Historical preserves the original advisor. Optional alternatives were presented; the recommended behavior is implemented. |
| Build unified workspace and Historical reports | Completed | Active/archived separation, all debts and pending promises, portfolio restrictions, CSV of accounts/activity/commitments, transfer snapshots. |
| Fix import conflicts and preserve portfolio provenance | Completed | Match preflight, explicit archived-account reincorporation, no duplicate debt in another active portfolio, row rollback, project/actor checks and cancellation protection. |
| Implement regional configuration and precision | Completed | Mandante defaults/project overrides, local dates and reporting buckets, exact 2/3 decimal input/storage and currency preservation. |
| Implement role templates and permissions editor | Completed | Saved templates survive seeders, project inherit/allow/deny decisions, Historical/collaboration grants, searchable comparison. |
| Integrate navigation, optional teams, and retire empty entity | Completed | Single Clients/accounts entry; optional direct advisor/team batches. Entity 1 archived locally with audit after verifying no active records/fields. Linked definitions remain available from valid person/account context. |
| Verify tests, static analysis, build, and local UI | Completed | Full suite: 1,794 passed / 5,751 assertions. Final targeted regressions, Pint, PHPStan, build and Blade compilation passed. Local migrations/permission seeders applied after database backup. Chrome verified empty active workspace, 8,429 historical accounts, permission comparison, regional preview and import match/date preview. |

The import preview fixture was removed after verification; no account was imported,
reassigned or moved locally. Existing failed attempts 27/28 remain historical
records. Their read-only preflight reports 72 and 55 archived matches respectively.
Local active account count remains 0, historical count 8,429, and movement count 0.
Regional preview changes were not saved. Local backup:
`storage/app/private/workspace-review-20260910/before-workspace.sql` (private).

Final verification: `composer test` passed 1,794 tests / 5,751 assertions, with
14 existing deprecation notices and 5 skipped tests. A final linked-entity
regression passed 7 tests / 20 assertions, and the subsequent numeric-string
validation refinement passed 77 Domain tests / 277 assertions. Earlier targeted
operational/entity/history checks passed 64 / 710, regional/reporting/archive
checks passed 134 / 386, and import format/row/cancellation checks passed 26 / 83.
Pint and PHPStan passed after the final code changes, including level 8 for all
seven changed Domain directories. The asset build, Blade compilation and
`git diff --check` passed. The isolation ratchet retains a zero baseline and
zero current known leaks; its dedicated test group is empty. No numerical Domain
coverage can be measured without Xdebug or PCOV in this PHP installation.

The implementation and local verification are complete, with no known blocking
failures. Details and deployment prerequisites are recorded in
`DOCS/CUSTOMER_WORKSPACE_2026_09_10.md`; final check logs are stored privately under
`storage/app/private/workspace-review-20260910/`. Chrome is left open on the
project's Historical page. Next: user review in the local portal, followed by
the intended project grants/settings and a deliberate real import when needed.

No production publication is part of this implementation. Usage, remaining
context, and cost counters are unavailable.

## Functional design follow-up — 2026-09-10

Objective: investigate the user's local portfolio/import, people/accounts,
regional settings, roles/matrix, teams, and empty configurable-entity concerns;
recommend what to retain or remove and present choices before implementation.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Diagnose local behavior | Completed | Read-only `crm` queries, code review, and browser inspection. Portfolio `222` is active; imports 27/28 collide with accounts in archived `SEM36`. |
| Evaluate design and dependencies | Completed | Detailed findings and recommendations in `DOCS/FUNCTIONAL_REVIEW_2026_09_10.md`. |
| Verify existing targeted behavior | Completed | 39 tests passed, 122 assertions in `crm_test`; no operational data changed. |
| Present option questions | Completed | Eight questions sent and the user subsequently accepted the recommendations. |
| Implement selected behavior | Completed | Implemented and verified in the customer workspace milestone above; the original audit itself was read-only. |

Confirmed gaps include person counts/search/CSV retaining archived-portfolio
accounts, import conflicts aborting the import, incomplete regional rendering
and configuration, globally seeded base roles, and an empty entity adding
unhelpful navigation. The accepted scope has now been implemented as recorded
above. Usage, remaining-context, and cost counters are unavailable.

## GitHub PR handoff — 2026-09-10

Objective: publish the completed local fixes for Joel to review and merge.
The user authorized this publication after the local implementation stage.

| Milestone | Status | Evidence / next action |
| --- | --- | --- |
| Verify branch and local checks | Completed | Clean working tree; fetched `origin/main` has no commits absent from this branch. Existing full-suite and final regression logs were verified. |
| Publish the review branch and open the PR | Completed | [PR #19](https://github.com/Saimper/crm1.0/pull/19), from `djneftali:fix/local-administration-catalogs-imports` into `Saimper:main`. |
| Document the deployment handoff | Completed | PR includes both migrations, validation evidence, compatibility behavior, and manual channel/result/permission configuration. |
| Review and production deployment | Completed | Joel (`Saimper`) merged PR #19 at 2026-09-10 22:45:37 UTC. Production workflow [34539006759](https://github.com/Saimper/crm1.0/actions/runs/34539006759) passed; the deploy job finished at 22:49:30 UTC. |

The PR includes implementation commit `6ea69f0` and the earlier production-parity
audit documentation. No production deployment or production data change was
performed during this publication. The workflow already handles database
backup, migrations, base role/permission seeders, assets, caches, queue restart,
PHP-FPM reload, and the application healthcheck. The administrator still needs
to choose each project's channel/type restrictions and supervisor grants after
deployment. Do not run demo seeders or copy local operational data.

Post-merge verification: the deployment log confirms production checkout
`852a964`, both September 10 migrations completed, and a successful HTTP 200
healthcheck. A subsequent read-only request to the production login page also
returned HTTP 200. This status check did not modify production or verify the
administrator's current project configuration.

Publication and deployment are complete. Next: review the deployed UI and choose
the intended project channel/result associations and supervisor grants as needed.
Usage, remaining-context, and cost counters are unavailable in this session.

## Local administration, catalogs, and imports — 2026-09-10

Objective: implement the user's reported local administration failures and
catalog/import improvements before a later production review. Branch:
`fix/local-administration-catalogs-imports`. Production changes and publication
are outside this implementation stage.

| Milestone | Status | Scope |
| --- | --- | --- |
| Reproduce failures and define behavior | Completed | Soft deletion preserves operational history; disabled/archived portfolios must leave active workflows. |
| Fix administration and portfolio permissions/visibility | Completed | Global administrator tenant/project deletion; independently assignable portfolio permissions. |
| Configure management types by channel and explicit no-contact results | Completed | Project-scoped channel/type associations; show contact-failure reasons only when explicitly configured; maintain editable reason catalogs. |
| Improve native import mapping | Completed | Predefined email/phone destinations, automatic header matching, and manual overrides using the existing import pipeline. |
| Verify, document, and prepare the local handoff | Completed | Full suite, final hierarchy regressions, PHPStan, Pint, build, Blade compilation, isolation ratchet, and browser checks passed. |

Implementation choices: a management type can serve multiple channels. Existing
unconfigured types remain available in every enabled channel until explicitly
restricted. The result flag `es_no_contactado` controls reason selection;
historical interactions remain unchanged. Native import destinations retain the
existing operation fields and now include automatic/manual email, phone, and
reference contact mapping.

Both September 10 migrations have been applied only to the local `crm` database.
Verification completed: `composer test` reported 1,712 passed, 14 existing
deprecation notices, 5 skipped, and 5,472 assertions. The final hierarchy
follow-up (deleting a project also disables its portfolios so queued work stops)
passed 29 targeted tests / 119 assertions. Pint passed. PHPStan passed across
the application and at level 8 for the three changed Domain directories; the
CLI needed `--memory-limit=512M` because the local 128 MB default was exhausted.
The asset build, Blade cache compilation, and isolation ratchet passed (zero
known pending leaks). A numerical coverage percentage was not measured: this
PHP installation has neither Xdebug nor PCOV.
No usage/context/cost counters are available in this session.

### Local behavior and review paths

- Mandantes and projects: deletion archives the selected hierarchy and records
  an administrative audit event. Mandante deletion also revokes its integration
  tokens. Cases, management history, commitments, and assignments are retained.
- Portfolios: `/proyectos/{id}/carteras` reuses the existing editor. Permissions
  `carteras.ver`, `carteras.crear`, `carteras.editar`, and `carteras.eliminar`
  are available to custom project roles. Supervisors receive read access by
  default; editing/deactivation and deletion require explicit grants. Existing
  project administrators retain their portfolio administration capabilities.
- Inactive/deleted portfolios no longer contribute accounts to case lists,
  search, work views, assignment inboxes/counters, mass assignment, or the
  integration account preview. New case/management/assignment writes and
  import processing check availability too. Re-enabling an inactive portfolio
  restores its accounts; deleted portfolios have no UI restore action. Reserved
  historical codes cannot cause an unhandled duplicate-key error on recreation.
- Project configuration → management types: select one or more allowed channels.
  The table shows the saved association. Empty selection means every enabled
  channel, preserving existing configuration until the administrator narrows it.
  Inactive types/channels and forged cross-project combinations are rejected.
- Project configuration → results: `No contactado` explicitly enables the
  optional no-contact reason selector. It cannot coexist with effective contact.
  Switching the channel/type/result clears dependent selections. Reasons and
  causes are also accessible under type catalogs, using the same existing CRUD.
- Imports: native destinations are selectable manually. Email/phone/contact
  headers (including numbered columns) are detected automatically; unrecognized
  headers can still be mapped manually or kept as custom fields. Contacts use
  the existing project-scoped person/contact pipeline. Duplicate native
  destinations and destinations from other operation types are rejected.
  Downloadable XLSX templates now include `correo` and `contacto`.
- Browser review also fixed unresolved Alpine translation expressions on the
  mandante/project editor controls. No customer case or catalog configuration
  was changed during the UI review. A synthetic CSV was uploaded only through
  the mapping preview; no import was executed. The temporary verification
  account and its sessions were removed afterward.

Browser checks at desktop and mobile widths verified the deletion confirmation,
portfolio page, six channel choices, explicit no-contact checkbox, shared reason
catalog, and automatic native/contact mapping. The final run had zero JavaScript
errors and no document-level horizontal overflow. Evidence and successful check
logs are stored locally under
`storage/app/private/local-admin-review-20260910/` (not publication artifacts).
The local application uses the synchronous queue driver, so no local queue
worker restart was needed.

Local implementation is complete. Next: review the changes on this branch,
configure each type's intended channels and any supervisor grants in the UI,
then schedule the later production publication.

Production remains unchanged by this implementation. A later deployment needs
both September 10 migrations, the normal asset build/cache refresh, and a queue
worker restart. Do not run demo seeders against production. Custom supervisor
grants and the desired channel/type assignments are project configuration to be
chosen by the administrator. No additional channel-specific business outcomes
were invented or assigned automatically.

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
