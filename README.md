# CUK Concours — Refactor v2

Laravel 13 + PostgreSQL 16 served via **WAMP / Apache** on Windows, modular architecture (`nWidart/laravel-modules`), strict service-layer pattern, three-segment RBAC with scope resolvers.

> This is the v2 replacement for the legacy PHP app at the repo root. The two run side-by-side until cutover.

## Quick start (Windows + WAMP)

You'll need WAMP (PHP ≥ 8.4 + Apache + the `pdo_pgsql`, `pgsql`, `intl`, `bcmath`, `gd`, `mbstring`, `zip` extensions enabled), Composer, Node 22+, and Docker (only for Postgres).

```powershell
# 1. PostgreSQL only — Docker (about 200 MB image, ~50 MB at idle)
docker compose up -d
docker compose exec postgres pg_isready -U cuk -d cuk    # smoke test

# 2. PHP deps + asset build, on the host
copy .env.example .env
php artisan key:generate
composer install
npm install
npm run build      # or `npm run dev` for hot-reload while you work

# 3. Schema + seed data
php artisan migrate --seed

# 4. Point your WAMP vhost at this folder.
#    DocumentRoot:  C:/Users/<you>/.../koulamoutou/cuk-app/public
#    (or alias `cuk-app/` itself — the project-root .htaccess rewrites into public/).
#    Then restart Apache.
```

Open `http://localhost/` (or whatever vhost you configured).

### Cache / sessions / mail

All on the local filesystem — **no Redis, no Mailhog** required.

* Cache + sessions + queue → `storage/framework/{cache,sessions}` (driver `file`, queue `sync`)
* Outgoing mail → appended to `storage/logs/laravel.log` (driver `log`); switch `MAIL_MAILER=smtp` in `.env` + fill `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` for staging/prod.

### Updating after a pull

```powershell
composer install
npm install
npm run build
php artisan migrate
php artisan view:clear
php artisan config:clear
```

No container to rebuild — Apache serves the new files immediately.

## Stack

| Concern              | Choice                                               |
| -------------------- | ---------------------------------------------------- |
| PHP                  | 8.4 (WAMP)                                           |
| Framework            | Laravel 13                                           |
| Modules              | nWidart/laravel-modules 13                           |
| Database             | PostgreSQL 16 (Docker)                               |
| Cache / Queue / Sess | filesystem (`storage/framework`)                     |
| Mail (dev)           | `log` driver → `storage/logs/laravel.log`            |
| Frontend             | AdminLTE 4 (Bootstrap 5) via Vite                    |
| Auth                 | Laravel + Google2FA + reCAPTCHA v3 + tiered throttle |
| Tests                | Pest 3                                               |
| Static analysis      | Larastan 3, Pint, Rector                             |

## Project layout

```
cuk-app/
├── app/
│   ├── Foundation/              ← framework-agnostic, shared kernel
│   │   ├── Concerns/HasUuid.php
│   │   ├── Models/BaseModel.php
│   │   ├── DTOs/Dto.php
│   │   ├── Permissions/         ← the RBAC engine (see below)
│   │   └── Http/Middleware/
│   └── Providers/
├── Modules/                     ← 15 nWidart modules
│   ├── AcademicStructure/
│   ├── Concours/                ← candidats (renommé), sessions, épreuves, notes
│   ├── UserManagement/          ← users, roles, permissions, login, 2FA
│   ├── Parametrage/             ← frais, contenu page d'accueil, etc.
│   ├── Referentiels/            ← bacs, pays, documents requis
│   └── … 10 stubs ready for later stages
├── config/
│   ├── permissions.php
│   └── modules.php
├── docker/
└── scripts/
```

## RBAC — the short version

Permissions are strings with **exactly three segments**:

```
action : resource : scope
```

| Examples                          | Meaning                                         |
| --------------------------------- | ----------------------------------------------- |
| `view:candidats:*`                | voir tous les candidats                         |
| `view:candidats:own_center`      | voir uniquement les candidats de son centre    |
| `edit:candidats:own`              | n'éditer que son propre dossier                 |
| `*:parametrage:*`                 | tout faire sur le paramétrage                   |
| `validate:candidats:own_session` | valider les candidats de la session courante   |

Scope resolvers shipped:

| Key            | Resolver class             | Behaviour                                  |
| -------------- | -------------------------- | ------------------------------------------ |
| `*`            | `WildcardResolver`         | grants always; no WHERE filter             |
| `own`          | `OwnResolver`              | row's owner == current user                |
| `own_center`   | `OwnCenterResolver`        | row's centre is in user's accessible set   |
| `own_region`   | `OwnRegionResolver`        | row's region matches user's region         |
| `own_session`  | `OwnSessionResolver`       | row belongs to current concours session    |

Every scope resolver implements two operations:

```php
interface ScopeResolver {
    public function key(): string;
    public function grants(PermissionHolder $user, Permission $required, ?Model $target = null): bool;
    public function applyToQuery(Builder $query, PermissionHolder $user, Permission $required): Builder;
}
```

so the *same* permission can both authorise a single-record action **and** scope a list query — you never load 10 000 rows to filter 9 990 of them in PHP.

### Using it

In a Form Request / Policy:
```php
$this->user()->can('edit:candidats:any', $candidat);   // → Gate::before delegates to PermissionChecker
```

In a Service (list scoping):
```php
$query = Candidat::query();
$query = $this->scoped->apply($query, $user, action: 'view', resource: 'candidats');
return $query->paginate();
```

In a route:
```php
Route::get('/candidats', [CandidatController::class, 'index'])
    ->middleware('perm:view:candidats');
```

## Service pattern (every module)

```
HTTP request
   ↓
Route  →  Controller (thin: 5–15 lines)
            ↓
        FormRequest      ← validation + authorize() via Policy
            ↓ (validated DTO)
        Service          ← business logic, transactions, events
            ↓
        Repository       ← only when query is complex enough to extract
            ↓
        Eloquent Model   ← HasUuid + SoftDeletes + Scopable
```

Controllers never touch Eloquent directly. Services never read `$request`. DTOs are immutable (`readonly` classes).

## Authentication

* Login by **email** or **telephone** (the historical admin login form used phone).
* On first successful login, if the user has no `google2fa_secret`, the app generates one, shows the QR, and forces enrolment before reaching the dashboard.
* Legacy SHA1 passwords from the old `utilisateurs` table are accepted *once*, then transparently rehashed to bcrypt (flagged `password_legacy=false` afterwards).
* Throttling:
  * 3 failed attempts in 15 min → 15 min lockout
  * 5 cumulative failed attempts in 24 h → 24 h lockout + email alert
  * 2FA verification has its own 5-attempt / 15-min throttle.
* reCAPTCHA v3 on the login form, min score 0.5.

## Backward compatibility

* Legacy file folders (`imageprofilecupk/`, `documentcupk/`) live next to the project (or wherever `LEGACY_DOCUMENTS_PATH` / `LEGACY_PROFILE_IMAGES_PATH` point) — old document links keep resolving during the transition.
* A `LegacyAdminImportSeeder` ingests the 13 admin accounts on `db:seed`.
* Bulk data (1795 candidats + their documents + motifs + payments) is imported by a one-shot artisan command **after** the initial migrations:

```bash
# Drop the dump in storage/legacy/dump.sql, then:
php artisan cuk:legacy-import --dry-run         # validate
php artisan cuk:legacy-import                   # import everything
php artisan cuk:legacy-import --tables=payments # subset re-run
```

The importer is idempotent (legacy IDs tracked on each new row) and reports per-table counts + per-row errors at the end.

## Common commands

Run from the project directory directly (no container exec — PHP lives on the host):

```bash
php artisan migrate           # run migrations
php artisan module:list       # list modules
composer test                 # Pest
composer stan                 # static analysis
composer fmt                  # Pint format
npm run build                 # rebuild JS/CSS bundle (production)
npm run dev                   # Vite dev server with HMR
```

Only Postgres operations stay inside Docker:

```bash
docker compose up -d                              # start postgres
docker compose down                               # stop (volumes preserved)
docker compose exec postgres psql -U cuk -d cuk   # open a psql shell
docker compose exec postgres pg_dump -U cuk cuk > backup.sql
```

## Repository conventions

* Strict types everywhere (`declare(strict_types=1);`).
* `final` by default on every class; only `abstract` for explicit extension points.
* `readonly` on every DTO / value object.
* UUID primary keys, soft deletes on every domain model.
* Migrations include indexes for every column appearing in a WHERE / JOIN.
* No facades inside Services — inject dependencies via constructor.

## Roadmap

| Stage | Scope                                                              | Status |
| ----- | ------------------------------------------------------------------ | ------ |
| 1     | Foundation: Docker, RBAC engine, module scaffolds                  | ✅ done |
| 2     | UserManagement + auth (2FA, reCAPTCHA, throttle), legacy importer | next   |
| 3     | Referentiels + Parametrage (public homepage driven by settings)    | -      |
| 4     | AcademicStructure (cycles, niveaux, sections, années, semestres)  | -      |
| 5     | Concours (candidats, épreuves, notes, sélection, eBilling)        | -      |
| 6     | Scaffold remaining 10 modules + cutover docs                       | -      |
