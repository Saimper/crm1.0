# Customer workspace, historical accounts, and regional configuration

Implementation branch: `feat/customer-workspace-history-settings`.
Scope: the eight decisions accepted in the September 10 functional review.
This implementation is prepared for pull-request review; it has not been deployed
to production.

## Operational model

- A person has one workspace containing all authorized accounts. Each account
  retains its own reference, portfolio, currency, balance, arrears and pending
  commitments. The sidebar has one **Clientes** entry.
- An account has one responsible advisor. Mass assignment can target an advisor
  directly or an optional team, with a portfolio filter and a batch limit. A
  subsequent batch selects remaining unassigned accounts and preserves existing
  owners. Teams group advisors; portfolios group accounts.
- Another advisor may read accounts within their permitted project/portfolios.
  Registering activity for another owner also requires `casos.colaborar` and
  `gestiones.crear`. Cooperation preserves the owner and records the actual author.
- Archived/inactive portfolios leave the active directory, search, and exports.
  A person with another operational account remains visible.

## Historical custody and reports

`/proyectos/{id}/historico` provides read-only accounts, activity and commitments,
with search, portfolio filtering, account detail and separate CSV exports.
Supervisor access is enabled by default, with the original advisor retained for
reporting. Custody is evaluated through the supervisor role; archival does not
rewrite historical assignments. Portfolio restrictions still apply.

Restrictions are evaluated for the permission being exercised. An unrelated,
unrestricted role cannot widen Historical access. Historical exports require
both read and export permission for the source portfolio.

`ADMIN_GLOBAL` can grant `historico.ver` and optionally `historico.exportar` to
advisors through the project role editor. Auditors receive read access by default.
The operational account's arrears and commitment expiry processes skip archived
portfolios. Stale commitment and linked-entity forms also reject writes after
archival.

Reincorporation stores an immutable portfolio movement with actor, import,
previous advisor, source/destination, financial snapshot, and activity/commitment
ID bounds. Historical exports use the source portfolio and snapshot, including
after the current account moves to another portfolio. Successive stages do not
duplicate the same historical activity. Commitment rows explicitly show their
current status; this is not a versioned snapshot of every later commitment change.
Reincorporating an account deliberately restores the same debt and its existing
commitments/activity for continuity of attention. Its active work view retains
that continuity; the separate Historical archive remains permission restricted.

## Operational archive follow-up

The September 10 follow-up found that archived commitments still appeared in
the operational list and summary cards. List, filters and CSV now share the
same active portfolio/account/person query and authorized portfolio scope.
The page explains where archived commitments are retained and links to
Historical for authorized users.

The same correction covers management CSV, all four dashboards, the four roots
of the custom report builder, notification lists/badges/generation and overdue
classification. Scheduled arrears and commitment expiry already excluded
archived accounts; automatic arrears-band assignment now excludes them too.
Stored notification rows are preserved. Generic notices linked through account
metadata are filtered; assignment batch notices without an individual account
remain as administrative receipts.

The integration preview now shares one authorized active-account scope for
accounts, commitments and latest activity, with project-local commitment dates.
An existing person without any debt remains a valid integration lookup; a
person whose debts are all archived or inaccessible does not expose an active
summary. Incoming screen-pop links also respect operational availability.

Mixed roles no longer widen scope for commitment listing/export, management
export, reports or Historical when the additional role does not grant that
specific action. Reading and exporting remain separate permissions.

Before the automatic-return correction, read-only verification of local project 8 found 8,429 historical accounts,
481 retained commitments and 4,359 retained management records. Each corresponding
operational count was zero, and no accounts had been reincorporated. The local
role configuration has no overrides for collaboration/Historical: supervisors
can collaborate and consult/export Historical; advisors can read accounts under
`casos.ver`, while `casos.colaborar` and Historical access remain disabled for
them until configured. No grants or assignments were changed in this follow-up.

## Import behavior

The confirmation step separates new references, accounts already in the selected
portfolio, matches in other active portfolios, and archived/inactive matches.

- Existing accounts in another active portfolio are skipped and explained;
  imports cannot duplicate the debt or silently transfer responsibility.
- Accounts in archived/inactive portfolios return automatically to the selected
  active portfolio in every import mode. There is no additional checkbox.
  Reincorporation preserves the existing debt, owner and historical evidence;
  INSERT and SKIP update returning accounts with the incoming data, while MERGE
  retains its fill-empty-fields behavior. Active duplicates still follow the
  selected mode. The actor requires import-processing access to both source and
  destination; editing portfolio settings is not required.
- Enqueue verifies that the actor is active and authorized for the destination;
  the portfolio selector uses the scope of the import-processing permission.
  MERGE preserves filled custom fields (including zero/false), existing CX text
  and sale estimates; repeated values within a batch fill a custom field once.
- Schema/project/operation, person identity, actor and destination are checked
  again during processing. A rejected row rolls back its changes, including any
  movement. Cancellation cannot be overwritten by completion or a late failure.
  Processing authorization is persisted server-side when the import is queued.
  Individually archived accounts/persons and mismatched debt ownership still
  require correction; automatic return concerns archived/inactive portfolios.
- INSERT can create a new account for an existing person. A person identifier
  alone is insufficient to identify a debt; an account reference is required.
- **Formato regional del proyecto** parses the configured separators and date
  order. **Excel numérico / intercambio** accepts canonical spreadsheet numbers
  with a decimal point and ISO dates. XLSX defaults to the latter; CSV defaults to
  the project profile. The confirmation step previews interpreted numbers/dates.

The previous failed imports remain evidence of their original attempts. Local
imports 27 and 28 have 72 and 55 archived matches respectively. They were not
reprocessed by this implementation.

The later automatic-return correction recovered the exact 55 omitted rows shared
by imports 30 and 31. New audited import 32 reused the rows from 31, retaining
its INSERT mode, input format, actor and 22 field mappings. It completed with
55 updates and zero inserted, invalid, omitted or duplicate rows. Portfolio 222
now contains 233 active accounts. Both original attempts remain unchanged.
The recovery preserved all 25 existing assignments, 110 management records and
11 commitments/promises of these accounts, and recorded 55 financial snapshots
for their previous portfolio stage. No accounts or persons were duplicated.
A fresh local database backup and before/after evidence were retained privately.
Final automatic-return verification passed 1,848 tests / 6,246 assertions,
with 14 existing deprecation notices and 5 skipped tests. Pint, application
PHPStan and changed Domain level 8 analysis, asset build, Blade compilation and
diff checks passed. PHPStan used a 512 MiB CLI memory limit. No migration or
dependency change was required for this correction.

## Configuration and permissions

Mandante settings define timezone, currency, date order, decimal precision,
decimal/thousands separators and start of week. Projects inherit them or save
their own complete override. Both editors provide a preview. Browser calendar
inputs retain their native date picker; presentation and import interpretation
use the configured regional settings.

Core money columns retain the previous integer capacity and now store three
decimal places. A project may require two or three meaningful decimal places;
excess precision is rejected rather than silently rounded. Existing account
currencies survive changes to the default. A downgrade to two places or migration
rollback is refused while stored values contain a meaningful third decimal.
Machine CSV exports preserve canonical monetary precision for interchange.

Base roles SUPERVISOR, GESTOR and AUDITOR can be edited by `ADMIN_GLOBAL` through
**Roles y permisos**. The global template supports name, description and grants;
project decisions support inherit/allow/deny. A project's decision replaces that
role's template grant, while grants from multiple assigned roles remain additive.
Saved template choices survive deployment seeders. Newly introduced permissions
must be explicitly reviewed for previously customized templates.

The editor includes search, collapsible groups and a comparison tab. System role
codes and protected administrative permissions retain their existing function.

## Local changes and deployment requirements

The local database was backed up before migrations. The three migrations applied
to `crm` are:

1. `2026_09_11_100000_add_project_regional_settings_and_decimal_precision`
2. `2026_09_11_110000_usuarios_add_base_role_configuration`
3. `2026_09_11_130000_casos_create_cartera_movimientos`

Only permission/base-role seeders were run locally, not demo data seeders.
Entity definition 1 (Banco Azteca) was archived with audit after rechecking that
it contained zero fields and zero active records. Its prior records remain.
Configurable entities still support useful person/account-linked information;
direct access requires valid context, active hierarchy and portfolio permission.

During the initial implementation review, Chrome verified the active directory, Historical, roles editor/comparison,
regional preview and import confirmation. The temporary preview import and its
unused generated field were removed; at that stage no operational account was imported,
reassigned or transferred. Initial local counts were 0 operational accounts,
8,429 historical accounts, and 0 portfolio movements.

A later deployment requires a database backup, these migrations, normal base
permission seeders, asset build/cache refresh and queue-worker restart. Do not
copy local business records, backups or preview files to production. Regional
overrides, advisor cooperation and advisor Historical access should be configured
for the intended project before its next real import or assignment.

### Reviewer and deployment handoff

Joel (`@Saimper`) should review the PR before merging into upstream `main`.
The existing CI/CD workflow deploys a push to `main` after its CI gate passes;
opening this PR does not deploy the application.

1. Confirm CI passes and the deployment database backup is available. Schedule
   the monetary-column alterations appropriately for the database size.
2. Apply the three migrations listed above with `php artisan migrate --force`.
3. Run only the normal metadata seeders, in order: `RolesSeeder`, `PermisosSeeder`,
   `RolPermisoSeeder`, under `Database\\Seeders\\Usuarios`. These preserve edited
   base-role templates. Do not run `DatabaseSeeder` or demo/user seeders.
4. Build assets, clear/rebuild application caches, and restart queue workers
   through the existing deployment workflow. Confirm the `imports` worker runs.
5. Verify Clientes and Histórico, archived commitment/report visibility,
   automatic return to an active portfolio, regional previews and project role
   overrides. Confirm owners and historical activity survive account return.

The 55-account recovery (local import 32), archived entity definition 1 and local
configuration are local business-data actions, not migrations. Do not replay
their IDs or copy the local database into production. Production recovery, if
needed, uses its own account file through the corrected importer.

Do not blindly roll back the money migration after storing meaningful third
decimals: its rollback guard intentionally refuses precision loss. Use the
verified backup and an explicit recovery plan if schema deployment fails.

Validation results and the current completion status are recorded in
`DOCS/LOCAL_REVIEW_2026_09_09.md`. Usage/context/cost counters are unavailable.
