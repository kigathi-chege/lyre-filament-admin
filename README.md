# Lyre Filament Admin

A runtime, metadata-driven Filament v5 admin engine for the Lyre ecosystem.

Instead of hand-writing a Filament Resource per Eloquent model, this package discovers your models at boot, inspects their schema and Lyre metadata, and **generates real Filament Resources, Pages, and Relation Managers entirely at runtime**. No PHP file per model. No `make:filament-resource` ceremony. New model → new admin page on the next request.

---

## What you get

- **Zero per-model boilerplate.** Add a model, get an admin page.
- **Schema-driven forms, tables, and infolists.** Columns, FK selects, date pickers, JSON editors, soft-delete filters, enum badges — all inferred.
- **Native Filament feel.** Real `Filament\Resources\Resource` subclasses, real routes, real navigation, real global search, real Livewire components.
- **Lyre-aware.** Reuses `Lyre\Model::generateConfig()`, `Lyre\Model::getModelRelationships()`, and `Lyre\Repositories\BaseRepository::buildQuery()` when available.
- **Hand-written resources still win.** If `App\Filament\Resources\PostResource` exists, the engine skips that model.
- **Filament Shield friendly.** Permissions and Laravel policies are respected via a three-stage authorization pipeline.

---

## Requirements

| Package | Version |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^10 \|\| ^11 \|\| ^12 \|\| ^13` |
| Filament | `^5.0` |
| Lyre | `^2.0` |

---

## Installation

### 1. Install Filament and the package

```bash
composer require filament/filament:^5.0
composer require lyre/filament-admin:^1.0
```

If you intend to use roles/permissions, also install Shield:

```bash
composer require bezhansalleh/filament-shield
```

### 2. Run the one-shot bootstrap

```bash
php artisan lyre-admin:install
```

This command:
- verifies `filament/filament` is installed,
- publishes `config/lyre-filament-admin.php`,
- ensures the `storage/framework/lyre-admin/` runtime directory exists,
- writes a clean `app/Providers/Filament/AdminPanelProvider.php` (backing up any existing one),
- registers the provider in `bootstrap/providers.php`,
- clears stale Filament caches.

Flags:

| Flag | Effect |
| --- | --- |
| `--brand="Your Brand"` | Brand name written into the panel provider. |
| `--with-shield` | Wires `FilamentShieldPlugin::make()` into the panel. |
| `--force` | Overwrites an existing `AdminPanelProvider.php` **without** a backup. |
| `--skip-cache-rebuild` | Skips clearing the Filament component cache. |

### 3. Visit `/admin`

That's it. Every Eloquent model in your configured paths is now an admin resource.

---

## How the engine works

1. **Discover** — `ModelDiscoverer` scans `config('lyre.path.model')` (override via `discovery.namespaces`), drops anything matching `discovery.exclude`, and dedupes against (a) hand-written resources in `coexist.handwritten_namespaces` and (b) any resource the panel has already registered.
2. **Resolve metadata** — `ModelMetadataResolver` builds a `ModelMetadata` value object (columns, casts, fillable, relationships, Lyre config). Forever-cached, keyed by `sha1(model) + schema fingerprint`.
3. **Materialize runtime classes** — `RuntimeClassFactory` writes one ~6-line PHP file per resource family (`Resource`, `ListPage`, `CreatePage`, `EditPage`, `ViewPage` + relation managers) into `storage/framework/lyre-admin/`. Stable hash → stable FQCN → stable Livewire component ID → safe across requests.
4. **Register with Filament** — `LyreFilamentAdminPlugin::register(Panel $panel)` calls `$panel->resources([...generated FQCNs])` before Filament queues Livewire components.
5. **Render** — `BaseDynamicResource` delegates to `DynamicTableBuilder`, `DynamicFormBuilder`, `DynamicInfolistBuilder`, `DynamicFilterBuilder`, all driven from `ModelMetadata`.

---

## Field type inference

| Source | Maps to |
| --- | --- |
| Primary key (`id`) | hidden, toggleable, sortable `TextColumn` |
| `*_id` foreign key | `BelongsTo` → `Select::relationship()` (form) / `TextColumn::make('relation.display')` (table) |
| `boolean` / cast `boolean` | `Toggle` / `IconColumn::boolean()` |
| `date` | `DatePicker` / `TextColumn::date()` |
| `datetime` / `timestamp` | `DateTimePicker` / `TextColumn::dateTime()` |
| `text` / `longtext` | `Textarea` / wrapped `TextColumn` |
| `json` / `jsonb` | JSON `Textarea` / formatted `TextColumn` |
| `integer` / `bigint` / `decimal` | numeric `TextInput` / numeric `TextColumn` |
| `email` (by name) | email `TextInput` / copyable `TextColumn::icon('envelope')` |
| `password` (by name) | hashed password `TextInput` / hidden in tables |
| `url`, `*_url`, `website`, `link` | url `TextInput` / linked `TextColumn::openUrlInNewTab()` |
| enum cast | options `Select` / `TextColumn::badge()` |
| `uuid` | copyable `TextColumn`, hidden by default |
| Timestamps & `deleted_at` | hidden, toggleable, sortable |
| Soft-delete model | `TrashedFilter`, `RestoreBulkAction`, `ForceDeleteBulkAction` |

---

## Authorization

`AuthorizationPipeline` checks every `can*()` call on a dynamic resource in this order:

1. **Laravel policy** — if `Gate::getPolicyFor($model)` returns a policy, defer to `Gate::check()`.
2. **Filament Shield** — when `config('lyre.filament-shield')` is true and Shield exposes a permission for the model, defer to `auth()->user()->can($permission)`.
3. **Config fallback** — `config('lyre-filament-admin.authorization.fallback')`:
   - `deny` (default — safest)
   - `allow`
   - `allow_for_super_admin` (checks the configured super-admin role)

No permissions are auto-generated in V1.

---

## Configuration (`config/lyre-filament-admin.php`)

```php
'enabled' => true,

'discovery' => [
    'namespaces' => null,                  // null → falls back to config('lyre.path.model')
    'include'    => [],
    'exclude'    => ['App\\Models\\User'], // sane default — auth user usually hand-managed
],

'coexist' => [
    'respect_hand_written'    => true,
    'handwritten_namespaces'  => ['App\\Filament\\Resources'],
],

'use_repository' => true,                  // proxy table queries through Lyre repositories

'authorization' => [
    'fallback'         => 'deny',
    'super_admin_role' => 'super_admin',
],

'runtime' => [
    'namespace' => 'Lyre\\Filament\\Admin\\Runtime\\Generated',
    'path'      => null,                   // null → storage_path('framework/lyre-admin')
],

'navigation' => [
    'group_map'    => [],                  // 'App\Models\Order' => 'Commerce'
    'icon_default' => 'heroicon-o-cube',
],

'forms' => [
    'show_system_fields_on_edit' => false,
],

'tables' => [
    'default_per_page' => 25,
    'bulk_actions'     => true,
],

'polymorphic' => [
    'read_only' => true,                   // V1: morphTo/morphMany render read-only
],

'cache' => [
    'metadata_store' => null,              // null → default cache store
],
```

---

## Artisan commands

| Command | Purpose |
| --- | --- |
| `lyre-admin:install` | One-shot bootstrap: config + provider + dirs + caches. |
| `lyre-admin:doctor` | Sanity check: runtime dir writable, cache freshness, Shield wiring, model counts. |
| `lyre-admin:list` | Print every model and whether it gets a dynamic resource or is skipped. |
| `lyre-admin:show {model}` | Dump the resolved `ModelMetadata` for a model. |
| `lyre-admin:metadata:cache` | Warm the metadata cache for every discovered model. |
| `lyre-admin:metadata:clear` | Flush the metadata cache (run after schema changes). |
| `lyre-admin:rebuild-filament-cache` | Replacement for `php artisan filament:optimize` that bakes runtime resources in. |

---

## Coexisting with hand-written resources

A model is **skipped** (and you keep your hand-crafted Filament Resource) if:

- a class matching `{Model}Resource` is found in any of `coexist.handwritten_namespaces` AND its `::getModel()` returns the same FQCN, **or**
- a resource already registered with the panel (e.g. by another plugin) returns that model from `::getModel()`.

That means you can adopt the engine incrementally: start with everything dynamic, then write a hand-rolled `PostResource` when you need custom behaviour. The engine will pick it up and step aside on the next request.

---

## Filament cache (`filament:optimize`)

`php artisan filament:optimize` writes a cached components map. If it runs **before** dynamic resources are materialized, Filament will restore the stale cache on every request and silently drop the dynamic resources.

The plugin detects this and **throws** with a fix:

> Filament's component cache is stale. Run `php artisan lyre-admin:rebuild-filament-cache` or `php artisan filament:clear-cached-components`.

In deployments, use `lyre-admin:rebuild-filament-cache` instead of (or right after) `filament:optimize` — it clears the cache, lets the plugin re-materialize, then re-bakes the cache with the runtime FQCNs included.

---

## Uninstalling / starting fresh

The full reset flow that gets you back to a clean state:

```bash
# 1. Detach the panel so the app boots without Filament
rm -f app/Providers/Filament/AdminPanelProvider.php
#    (then remove the App\Providers\Filament\AdminPanelProvider line from bootstrap/providers.php)

# 2. Drop Filament
composer remove filament/filament

# 3. Bring it back
composer require filament/filament:^5.0 lyre/filament-admin:^1.0

# 4. Bootstrap
php artisan lyre-admin:install

# 5. (optional) wipe generated runtime classes — they regenerate on demand
rm -rf storage/framework/lyre-admin
```

---

## Testing

The package ships with Testbench-based PHPUnit tests:

```bash
cd packages/lyre-filament-admin
composer install
vendor/bin/phpunit
```

---

## License

MIT
