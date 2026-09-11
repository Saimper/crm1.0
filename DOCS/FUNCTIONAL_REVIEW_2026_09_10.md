# Functional review: customer workspace, portfolios, imports, and administration

## Scope and status

The user requested analysis of the local project, recommendations on retaining
or removing overlapping features, and questions with options before changing
the design. Reviewed `/Users/pc/crm-bpo`, its instructions, and the existing
`DOCS/LOCAL_REVIEW_2026_09_09.md` progress record. The only pre-existing working
tree change was that progress document; it was preserved.

| Milestone | Status | Evidence |
| --- | --- | --- |
| Inspect reported behavior and local data | Completed | Read-only transactions against local `crm`; code review; Safari inspection of entity 1 and permissions matrix. |
| Evaluate consolidation and dependencies | Completed | Findings and recommendations below. |
| Verify existing targeted behavior | Completed | 39 tests, 122 assertions, all passed against `crm_test`. |
| Ask product questions with options | Completed | Eight questions presented in the conversation; no answers received at documentation time. |
| Implement chosen behavior | Pending | Resolve product choices first; no application code or operational data changed in this audit. |

No production access, deployment, import execution, account transfer, permission
change, or portfolio/entity deletion was performed. Usage, remaining-context,
and cost counters are unavailable.

## 1. People, accounts, promises, and archived portfolios

The data model should retain person -> accounts -> commitments. A person can
have several debts, each with its own balance, currency, delinquency, owner,
and payment promises. A promise is not a debt. Navigation and presentation can
be unified without merging these records or duplicating identity/contact data.

Local project 8 contains 8,428 live person records and 8,429 retained case
records. One person has two accounts. All these cases belong to portfolio 5,
code `SEM36`, which is disabled and archived. There are zero operational cases
at this snapshot. Portfolio 6, code `222`, is active and has no cases.

Portfolio archival deliberately retains identities, cases, and interactions:
`app/Modules/Tenancy/Application/UseCases/AdministrarDisponibilidad.php:64`.
However, the person list's base query, portfolio restriction, and account count
do not check portfolio availability:
`app/Modules/Personas/Application/Services/ConsultaListadoPersonas.php:25`,
`:54`, and `:101`. The person CSV shares those queries. Person search also
retains this gap, while account search already applies operational filtering.
The earlier availability regression covers case lists, work views, and inboxes,
but omits the person list/count/CSV scenario.

The existing work view already groups cases under one person:
`app/Modules/Casos/Infrastructure/Http/Livewire/VistaDeTrabajo.php:120`.
Its selector shows portfolio and state but omits loan number, balance, and
days overdue; two debts in one portfolio can look alike:
`resources/views/modules/casos/livewire/vista-de-trabajo.blade.php:107`.
Financial detail is fetched only for the selected case (`VistaDeTrabajo.php:287`).
The same component loads only the first pending commitment (`:239`), although
the model allows several promises for an account.

Recommendation: one navigation entry and a person workspace with shared
identity/contact details plus a visible account table: account number,
portfolio, balance/currency, days overdue, status, assignee, and pending
promises. Selecting an account opens its interaction/history controls.
Keep a commitment agenda for due-date follow-up. Never combine different
currencies into an unqualified total. Offer explicitly separated operational
and historical views, hiding people with only archived debts from operation
by default while retaining their historical identity.

Additional code-review concern: the work view applies project and operational
portfolio filtering, but not the user's `carterasPermitidas` restriction.
The route requires project-level `casos.ver`; another account of the same
person can be included outside the allowed portfolios. This was identified by
inspection, not by a new exploit test. The unified read path must enforce
portfolio access before loading account details, history, or commitments.

## 2. Import failure into portfolio 222

Verified local facts:

| Import | Destination | Mode | Rows | Account matches in archived SEM36 | Result |
| --- | --- | --- | --- | --- | --- |
| 27 | Active portfolio 222 (id 6) | upsert | 255 | 72 | Failed, 0 processed |
| 28 | Active portfolio 222 (id 6) | upsert | 233 | 55 | Failed, 0 processed |

Both saved global errors are exactly:
`La cuenta pertenece a una cartera desactivada o eliminada.`

`ProcesarFilaDinamica.php:101` checks the destination portfolio, which is valid.
The account lookup then searches by project and account identifier across
portfolios (`:269`; batch equivalent in `EjecutarImportacionDinamica.php:374`).
The availability guard at `ProcesarFilaDinamica.php:136` rejects an existing
account in an archived portfolio before applying the selected import mode.
Its exception aborts the enclosing chunk transaction, and execution stops.
This explains why an active destination still produces the reported message.
It is not evidence that portfolio 222 is inactive.

The loan-number unique constraint is project-wide:
`database/migrations/2026_04_19_100002_cobranza_create_casos_cobranza_table.php:52`.
Ignoring archived rows and inserting a duplicate is therefore not a solution.

Recommendation: add a preflight showing existing accounts and their source
portfolio, with an explicit transfer/reincorporation policy. Preserve the
account ID and history for a continuing debt; validate identity and project
boundaries; handle row conflicts without presenting an unrelated destination
as inactive. Preview expected created, updated, transferred, and skipped rows.
Do not silently reopen an entire archived portfolio.

A transfer needs more than updating `cartera_id`: custom fields are defined by
portfolio, and historical report filters can follow the case's current
portfolio. Record movement provenance, decide historical attribution, map
compatible fields, and review existing ownership and commitments. No transfer
or rerun was performed here.

## 3. Regional settings

Project 8's mandante (id 4) stores `America/Panama`, `USD`, `es`, and a Monday
week start. Projects have no regional override columns. The clock service works:
`hora_local('2026-09-10 18:00:00')` yields `10/09/2026 13:00`, whereas direct
Carbon formatting retains `18:00`.

Integration is incomplete:

- `AdminMandantes.php:53`, `:81`, `:101`, and `:149` only load/validate/save code,
  name, and document. No regional form was found in the current application.
- `bandeja-equipo.blade.php:161` and
  `gestor-registros-entidad.blade.php:79` still format timestamps directly.
- `resources/views/modules/cobranza/partials/panel-caso.blade.php:7` forces two
  decimals, decimal point, and thousands comma; dates at `:68` and `:74` force
  `d/m/Y`. There is no configurable date format or number separator found.
- Import DTO defaults and custom money field import still force USD rather
  than consistently consuming the mandante default.
- Core monetary columns are `DECIMAL(15,2)`; `MontoCobranza` and `MontoPromesa`
  also permit only two fractional digits. Displaying a third zero is not
  equivalent to preserving three-decimal input or calculations.
- Import parsing is ambiguous: `ProcesarFilaDinamica.php:917` treats `1,234`
  as 1234, while `1.234` remains fractional. Its date parser at `:963` delegates
  to `DateTimeImmutable`; `10/09/2026` is interpreted as October 9, 2026.
  Regional input parsing and an import preview are necessary alongside display.

Recommendation: mandante defaults with explicit project overrides and a preview
showing the effective values. Configure time zone, date format, default currency,
fractional precision, decimal separator, and thousands separator. Display
whether each value is inherited. Keep timestamp storage in UTC and date-only
values unchanged by time-zone conversion. Preserve each record's currency;
changing a default does not convert existing money. Changes to daily cutoffs,
jobs, parsers, exports, and validation must use the same effective settings.

## 4. Roles and permissions matrix

Base roles are currently shared global definitions, despite project-scoped
assignments. `AdminRolesCustom.php:50` edits only custom roles. Base permission
evaluation reads global `rol_permiso` (`app/Models/User.php:262`).
Direct base edits would currently affect every project.

Recommendation: let ADMIN_GLOBAL edit base-role permissions and presentation,
with a global default template and project-specific overrides. Preserve role
codes required by integrations and keep ADMIN_GLOBAL protected. Make scope
explicit in the UI; a custom role should not be necessary merely to adjust a
base role. Separate allowed actions from account/portfolio visibility.

Defaults must stop overwriting saved choices: `RolesSeeder.php:22` updates
names/descriptions, and `RolPermisoSeeder.php:189` restores default grants.
Both run during deployment (`.github/workflows/deploy.yml:408`). Add audited,
project-isolated persistence and revocation tests as part of implementation.

Safari inspection confirmed the matrix is a cramped read-only table leaving
most horizontal space unused. There are 84 permissions in 19 groups, repeated
technical labels, and faint check/dot states. The template is
`resources/views/modules/usuarios/admin/matriz-permisos.blade.php:22`.

Proposed single entry: `Roles y permisos`. Default to an editor for one role,
with readable action names, search, collapsible groups, and explicit granted
states. A `Comparar roles` tab provides a full-width matrix with fixed column
headers, consistent row spacing, selected-role comparison, and a differences
filter. Keep technical permission codes in secondary detail. Editing this UI
does not itself change privileges; saved scope must match runtime enforcement.

## 5. Teams versus portfolios

A portfolio groups accounts. A team groups users who work accounts. These are
independent dimensions: one portfolio can be distributed among several teams,
and one team can work several portfolios.

`AsignarCasosAEquipo.php:17` distributes cases among members and ultimately
stores `usuario_id` (`:101`), not an owner team ID.
`BandejaEquipo.php:169` gathers member assignments. Local project 8 has one
active team, `Cobranza`, with one active member. There are 955 historical
assignments; they must not be reported as current workload after archival.

Recommendation: keep optional `Equipos de gestores` under supervision and
assignment. Allow direct assignment without requiring a team per portfolio.
If this operation never needs group supervision or distribution, its team
screens can be hidden, retaining assignment/history compatibility.

## 6. Banco Azteca / Nuevo registro

Local entity definition 1 is named `Banco Azteca`, code `BAZTECA`, configured
with relation `persona`, no portfolio restriction, zero defined fields, and
zero active records. One archived record has neither a person nor a case link.
Safari confirmed the page only offers `Nuevo registro` and an empty list.
It does not represent the mandante, project, or imported debts.

Recommendation: archive this empty definition and remove its menu entry. Keep
the optional configurable-entity capability for genuinely needed repeated data
such as guarantees, vehicles, or policies, with meaningful fields and links.
Prefer creation from the relevant person/account workspace.

Further gap: `routes/web.php:205` supplies no person/account to the standalone
route, and `ServicioEntidades.php:138` accepts both null without enforcing
`relacion_con`; it also does not enforce active status at creation. Validate
these requirements server-side. Hiding a menu alone is insufficient.

## Product decisions presented

1. Main workspace: person with all debts (recommended), debt-first list grouped
   by person, or retain separate lists.
2. Import matches in archived portfolios: preview and authorize transfer
   (recommended), automatic transfer, or skip matches and import new accounts.
3. Archived portfolios: remove from operation and retain a historical view
   (recommended), or keep people visible with clearly archived accounts.
4. Regional scope: mandante defaults with project overrides (recommended),
   project-only, or mandante-only.
5. Three decimals: actual import/storage/calculation precision (recommended),
   or display-only padding while retaining two-decimal storage.
6. Base role scope: global template plus project adjustments (recommended),
   global edits affecting every project, or project-only edits.
7. Teams: optional teams of users (recommended), or direct assignment with team
   screens removed.
8. Empty Banco Azteca entity: archive the definition but retain the optional
   module (recommended), or repurpose it after defining real data requirements.

Answers are pending. Recommended options were not treated as approvals or
configuration choices.

## Verification and next step

Ran these existing tests against `crm_test`: `DisponibilidadOperativaTest`,
`RelojDelMandanteTest`, `ListadoPersonasTest`, `ExportarPersonasTest`, and
`EntidadesConfigurablesTest`. Result: 39 passed, 122 assertions. Log:
`/tmp/crm-functional-audit-20260910-tests.log`.

These passing tests verify current behavior, including the import rejection.
They do not establish that the requested design or uncovered cases are fixed.
No application files changed, so a full formatting/static-analysis/build cycle
was not required for this analysis-only stage.

Next: incorporate the user's answers and implement the agreed changes. Add
regressions for archived-only and mixed active/archived people, searches/CSV,
allowed-portfolio detail access, multiple debts/promises, import transfer and
conflict isolation, regional input/display/precision, saved role overrides,
and entity link/activity requirements. Complete the project verification order
and browser QA for that implementation before publication.
