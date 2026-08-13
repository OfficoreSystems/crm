# crm

Modular CRM on Symfony 7.4 LTS. Monorepo: every module is a Composer package
and a Symfony bundle at the same time.

## Setup

```bash
git clone https://github.com/OfficoreSystems/crm.git && cd crm
make fresh          # starts containers, installs, migrates, seeds
```

Then: <http://localhost:8080/contacts> · mail at <http://localhost:8025> ·
object storage at <http://localhost:9001> (`officore` / `officore-dev-passwort`)

`user:seed` creates **three** accounts, all with the password
`officore-dev-passwort`:

| Account | Role | Team |
| --- | --- | --- |
| `admin@officore.test` | Administrator | Vertrieb |
| `vertrieb@officore.test` | User | Vertrieb |
| `innendienst@officore.test` | User | Innendienst |

Three and not one, because a single administrator makes it impossible to tell
whether permissions work at all — he is allowed everything anyway. Sign in as
`vertrieb@` and then as `innendienst@`: same pipeline, different rows. See
[Permissions](#permissions).

The command **refuses to run in production**. There, `bin/console user:create`
creates the first account and prints a generated password once.

## Adding a new module

```bash
cp -r modules/contact modules/invoice     # 1. copy the reference module
```

2. In `modules/invoice/composer.json`, set the name to `crm/invoice-module` and
   the PSR-4 prefix to `Crm\Invoice\`.
3. Replace `Crm\Contact\` with `Crm\Invoice\` everywhere, `ContactModule` with
   `InvoiceModule` (including the Twig namespace `@ContactModule` →
   `@InvoiceModule`).
4. `config/bundles.php`: `Crm\Invoice\InvoiceModule::class => ['all' => true]`.
5. `deptrac.yaml`: add the layers `InvoiceDomain/Application/Infrastructure/UI`
   plus `InvoiceBundle` and wire them into `ruleset` — **without this step
   `make arch` fails**, and that is on purpose.
6. `composer require crm/invoice-module:^0.1 && make migrate`

Routing, Twig path, Doctrine mapping and menu entry wire themselves up — the
core is not touched for any of it.

---

## The one rule

**The core knows no concrete module, and no module knows another.**

Communication runs exclusively through interfaces in the `shared-kernel` and
through Messenger events. Only two files name modules, and both are
registration rather than coupling: `config/bundles.php` and `deptrac.yaml`.

### Polymorphic references

An activity hangs off a contact, sometimes a company, sometimes a deal. A
foreign key is out of the question on technical grounds alone, quite apart from
the module boundary. Instead there is `SubjectRef` — **type + ID as two
scalars** — plus `SubjectResolverInterface` in the shared kernel.

The type is a string and **not an enum**: an enum would have to know every
type, would therefore live in the shared kernel, and every new module would be
a change to it. Avoiding exactly that is the point of the extension point.

A module makes its records referenceable by implementing the interface —
nothing more. Autoconfiguration does the rest:

```php
final class ContactSubjectResolver implements SubjectResolverInterface
{
    public function type(): string { return 'contact'; }
    public function resolve(array $ids): array { /* ... */ }
    public function search(string $query, int $limit = 10): array { /* ... */ }
}
```

**`resolve()` takes a list, not a single ID.** The `SubjectResolverRegistry`
groups by type and calls each resolver **exactly once** — a timeline with 50
entries across three modules costs three calls, not 50.

**`search()` belongs here**, even though it sounds like the opposite of
"resolve": whoever wants to attach something to a subject must be able to pick
one first. Without this method every such module would have to know the
concrete finders of `contact`, `company` and `deal` — the extension point would
only be half of one.

If a resolver is missing, the entry is missing from the result. The timeline
then shows "Reference not resolvable" — the history stays, only the name is
gone.

**The proof that the cut holds:** the `search` module has **no table of its own
and no migration**. It only asks the registry and sorts. A new module becomes
searchable by bringing a resolver along — `search` never hears about it.

### How module routes are loaded

Through the list of **registered bundles** in
[`src/Kernel.php`](src/Kernel.php), not through a glob over `modules/`:

```php
foreach ($this->getBundles() as $bundle) {
    if (!$bundle instanceof CrmModuleInterface) {
        continue;
    }
    // ... import <bundle-path>/config/routes.php
}
```

A glob would read the file system while registration runs through
`config/bundles.php` — the two can drift apart. A deregistered module would keep
its routes and the first call would end in *"has no container set"*, that is
**500 instead of 404**.

The filter on `CrmModuleInterface` is not optional: third-party bundles ship
`config/routes.php` too. `LiveComponentBundle`, for one, is already imported by
`config/routes/ux_live_component.yaml` with the prefix `/_components`; a second
import without the prefix produces `/{_live_component}/{_live_action}` — a route
that **swallows every single-segment path**. Guarded by
[`tests/Smoke/RoutingTest.php`](tests/Smoke/RoutingTest.php).

### Modularity test

A module must be removable without breaking the application:

```bash
# 1. remove it from config/bundles.php
# 2. composer remove crm/user-module
make fresh
```

Expectation: the application **degrades, it does not crash**. `/contacts` still
returns 200, `/login` and `/users` return **404** (not 500), the menu no longer
shows the module's entry, and `UserFinderInterface` falls back to
`NullUserFinder`.

### Optional extension points

A module that injects `UserFinderInterface` must not thereby make the `user`
module mandatory. For such contracts the shared kernel therefore ships a **null
implementation as the default**, which the module in question overrides:

| Contract | Default in the shared kernel | Replaced by |
| --- | --- | --- |
| `UserFinderInterface` | `NullUserFinder` (finds nobody) | `user` |
| `crm.security.user_provider` | `NullUserProvider` (knows nobody) | `user` |
| `CompanyFinderInterface` | `NullCompanyFinder` (finds nothing) | `company` |
| `ContactFinderInterface` | `NullContactFinder` (finds nothing) | `contact` |

### The five extension points

A module plugs in by implementing an interface — nothing more. The
autoconfiguration in `CrmSharedKernelBundle` handles registration, and no
existing module hears about it.

| Interface | What for | Registry |
| --- | --- | --- |
| `MenuProviderInterface` | Navigation entries | `MenuRegistry` |
| `CrmModuleInterface` | Self-description (name, version, dependencies) | `ModuleRegistry` |
| `SubjectResolverInterface` | Make records referenceable as a polymorphic target | `SubjectResolverRegistry` |
| `MetricProviderInterface` | Figures for the overview | `MetricRegistry` |
| `RecordOwnershipInterface` | Who owns a record, and in which columns | `OwnershipRegistry` |

A test in `CrmSharedKernelBundleTest` checks this list **exhaustively** — a
sixth extension point turns it red. That is deliberate: the public interface
should not grow by accident.

**Figures arrive pre-aggregated.** The dashboard computes nothing and queries no
foreign table — every module counts for itself, using the queries it has anyway.
That is why `Metric::$value` is a string: an amount of money, a percentage and a
count have nothing in common except that they get displayed.

This works because the `shared-kernel` sits **before** the modules in
`config/bundles.php` and the later service definition wins. Without the module
the application degrades instead of failing at container build time.

**One exception Symfony forces on us:** `security.firewalls` is a prototyped
node and must come in full from *one* configuration file — a
`prependExtension()` from the module aborts with "You are not allowed to define
new elements for path security.firewalls". The firewall therefore lives in
`config/packages/security.yaml` and points at the fixed service ID above, using
literal paths instead of route names.

This is not an agreement but a CI gate:

```bash
make arch     # deptrac analyse --report-uncovered --fail-on-uncovered
```

The run must report **0 violations and 0 uncovered**. `--fail-on-uncovered` is
the more important switch: it also catches the case where somebody adds a new
module and forgets to enter it in `deptrac.yaml`.

### Permissions

Two mechanisms answering different questions. Both are needed — leave one out
and you have a hole.

| | Question | Where |
| --- | --- | --- |
| **Voter** | May this user have *this* record? | `#[IsGranted]` on the controller |
| **Doctrine filter** | Which rows does he get loaded at all? | `RecordVisibilityFilter`, in SQL |

The voter alone is not enough for lists: a page with fifty rows would vote fifty
times, and by then the rows would long since be loaded. The filter alone is not
enough because it only constrains queries.

**The attribute is named `module.action`**, lower case:

```php
#[IsGranted('deal.view')]                  // list page: may he have the module?
#[IsGranted('deal.view', subject: 'deal')] // detail page: may he have the record?
```

A single word as `subject:` would have been read by Symfony as a *controller
argument* — hence the compound attribute instead of two parameters.

**The permission matrix** (`PermissionMatrix::default()`) maps role → module →
action to a scope: `all`, `team` or `own`. A named module entry does **not** fall
back to the wildcard `*` — otherwise "nobody may have this module" could not be
expressed at all, and user administration was visible to everyone.

**A module joins in** by implementing `RecordOwnershipInterface`: module name,
owner and team of an object — plus the column names for the filter. The columns
live there and **not as an attribute on the entity**: the domain layer depends on
nothing, and an attribute from the shared kernel would be exactly such a
dependency. A module without an implementation has no ownership — its data
belongs to everyone, which is right for master data like companies and contacts.

Two traps that cost time:

- **The filter is useless without its configurator.** It is armed and
  parameterised per request by `RecordVisibilityConfigurator`. If that is missing
  as a service definition, the application looks entirely normal — it just shows
  too much. That is why `RecordVisibilityTest` uses real requests to check that
  two users see the same record **differently**.
- **Functional tests have to clear the identity map.** The `KernelBrowser` does
  not reboot the kernel before the *first* request; a record created moments ago
  is then still in the EntityManager and is returned without SQL — and therefore
  without the filter. `SignsIn::signIn()` takes care of it.

### Files

Documents hang off a **polymorphic reference** just like activities — type plus
ID, no foreign key. The module does not know what a contact is.

The file itself lives in object storage; the database only records where. Locally
that is the MinIO from [compose.yaml](compose.yaml), in production S3, R2 or
Hetzner — same adapter, different values from the environment. In tests a local
directory, so the suite runs without a container.

Three decisions worth knowing about:

- **The storage key has nothing to do with the file name.** It reads
  `<type>/<year>/<month>/<uuid>`. Derived from the file name it would have two
  problems, the second being the worse: two users would overwrite each other, and
  whoever knows the file name knows the storage location.
- **File first, database row second** — and on failure the file is deleted again.
  There is no shared transaction, so the order decides which failure mode is left
  over: an orphaned file can be cleaned up, an entry without a file shows the user
  something that does not exist when clicked.
- **The download runs through the application**, not through a bucket URL. A
  public URL would be cheaper but would defeat permissions: whoever has the link
  would have the file. Delivery is always `attachment` with
  `X-Content-Type-Options: nosniff` — an uploaded HTML or SVG file would otherwise
  run in the application's origin.

Deleting is reserved for the administrator by default, as with deals and
activities. To change that, add a `document` entry to
`PermissionMatrix::default()` — the module itself needs no change.

### Appointments and the ICS feed

**All times are UTC** — in the database, in memory, in the feed. Conversion
happens in exactly two places: when reading input (the controller assumes
`Europe/Berlin`) and when displaying. Never in between.

A `datetime-local` field delivers local time *without* a time zone — which one is
meant is known to the browser, but it does not say. The assumption therefore sits
in one place in the controller and is where a per-user time zone would plug in.

**All-day appointments are dates, not points in time.** Converting them to UTC
first and truncating the time afterwards loses a day east of Greenwich: midnight
in Berlin becomes 22:00 the day before. In ICS they appear as `VALUE=DATE`
without any time zone, and `DTEND` is the **following day** — with 23:59:59
Outlook shows the appointment across two days.

#### The public area

Outlook, Google and Apple fetch the feed without a session and cannot get one.
Their only credential is a token in the URL. Such endpoints live under the
generic prefix `/public/`, which
[security.yaml](config/packages/security.yaml) opens up — generic, so that the
core does not know which modules use it. Unsubscribe links and webhooks will join
them later.

The reason for the detour: `security.access_control`, like
`security.firewalls`, **cannot** be prepended from a module. Symfony aborts with
"cannot be overwritten". A test in the calendar module records that we do not
even try.

What follows for the feed:

- Only the **SHA-256 hash** of the token is stored, and that is what is looked
  up. Reading the database gets you no feed.
- The URL is shown **once**. Never again afterwards — otherwise the hash would be
  decoration. Lose it and you generate a new one; the old one is worthless
  immediately.
- The feed contains **only its owner's appointments**, stated explicitly in the
  query. The Doctrine visibility filter does not help here: without a signed-in
  user it is switched off. Relying on it would serve every user's appointments.
- An unknown token gets **404 without explanation**. A "token expired" would
  confirm that the token once existed.

### Languages

**English is the default language**, German the second. The list lives in two
places that must agree — `enabled_locales` in
[translation.yaml](config/packages/translation.yaml) and the enum
`Crm\SharedKernel\Localization\Locale`. A test holds them together, because if
they drift apart the application shows a different language than the switcher
claims.

The choice lives **on the account**, not in the session: it should still apply
after the next sign-in, and later for emails, where there is no session. `null`
in the column means "never chosen" — the difference from `'en'` matters if the
default language ever changes.

The chain is longer than it looks:

```
Account → SecurityUser → ActorInterface → ActorLocaleListener
                                            ├→ Request
                                            └→ LocaleSwitcher → translator
```

The listener runs at **priority 5**, that is *after* the firewall (8) — before
that there is no signed-in user. That makes it too late for Symfony's
`LocaleAwareListener` (15), so it sets both itself. Setting only one of the two
produces an application that is half translated, and differently so depending on
where you look.

**Texts belong to the module.** Every module brings its own `translations/`;
Symfony loads it automatically because the modules are bundles. The core only
holds the catalogue for the shell layout.

**Labels are keys, not texts.** Menu entries, figures and search hits are created
in the infrastructure layer of the modules — and that layer must not see Symfony.
An injected translator would be exactly that dependency. So the modules pass keys
and placeholders along, and translation happens in the template. Where data and
translation mix — "Nordwind Logistik · Proposal" — there is `TranslatableText`
with nested placeholders.

**Numbers and dates follow the language**, not the template: `format_date`,
`format_datetime` and `format_currency` from `twig/intl-extra` instead of
`date('d.m.Y')`. For amounts of money `Metric` carries a currency code — only
then does the dashboard reformat. Treating percentages and counts by currency
rules would be worse than nothing.

**URLs and form fields are English**, because English is the default language:
`/calendar`, `/documents/for/{type}/{id}`, `name="start"`. Localised routes
(`/de/kalender`) are deliberately absent — they would have doubled the ICS feed
path, which external calendars store permanently.

### Coverage gate

At least **80 % line coverage**, enforced in CI by
[`tools/coverage-gate.php`](tools/coverage-gate.php). PHPUnit itself has no
"fail under" — the script reads the Clover report and aborts when the threshold
is missed. On a red gate it lists the weakest files.

The threshold lives in two places and has to be maintained together:
`COVERAGE_MIN` in the [Makefile](Makefile) and in
[`.github/workflows/ci.yml`](.github/workflows/ci.yml).

**The threshold is never lowered to make a build green.** Whoever misses it
writes tests.

### References across module boundaries

A contact belongs to a company — but `contact` and `company` are separate
modules. The rule for that, by example:

| | **not** like this | but like this |
| --- | --- | --- |
| Column | `ManyToOne` to `Company` | `company_id UUID NULL`, **without** a foreign key |
| Reading | `$contact->company()->name()` | `CompanyFinderInterface::findMany()` |
| Checking | database constraint | `CompanyFinderInterface::exists()` in the use case |
| Searching | `JOIN company_companies` | resolve name → IDs, then filter the own table |

**No foreign key is deliberate.** A constraint across the module boundary would
chain both modules together — `company` could no longer be removed without taking
the `contact` table apart. The price: the database does not guarantee validity. A
`company_id` can point nowhere, and that is a **normal state**, not an error —
the company was deleted, or the module is not installed. Callers get `null`.

**Searching without a join.** "Show me all contacts of Nordwind" is answered by
`contact` in two steps: first resolve the term to company IDs via
`searchByName()`, then filter its own table by them. Two queries instead of one
join, and the boundary stays intact. The repository never learns what a company
is — it filters on a column.

**Avoiding N+1.** The list resolves all companies on the page in *one*
`findMany()` call, not per row. Across a module boundary an N+1 is especially
expensive; a test records that.

### Layers inside a module

| Layer            | may see                                      |
| ---------------- | -------------------------------------------- |
| `Domain`         | nothing (only mapping attributes and Uuid)   |
| `Application`    | `Domain`                                     |
| `Infrastructure` | `Domain`, `Application`, Doctrine            |
| `UI`             | `Domain`, `Application`, `shared-kernel`     |

## Commands

`make` without an argument prints the list.

| Command        | Effect                                                    |
| -------------- | --------------------------------------------------------- |
| `make fresh`   | up + install + migrate + seed, prints the URLs             |
| `make up/down` | start / stop containers                                    |
| `make build`   | rebuild images                                             |
| `make sh`      | shell in the PHP container                                 |
| `make logs`    | follow logs                                                |
| `make install` | `composer install` in the container                        |
| `make migrate` | apply migrations                                           |
| `make seed`    | create sample records                                      |
| `make test`    | PHPUnit across all module suites (creates the test DB too)  |
| `make coverage`| PHPUnit with coverage, fails below `COVERAGE_MIN` percent   |
| `make stan`    | PHPStan level 8                                            |
| `make arch`    | Deptrac                                                    |
| `make ci`      | all gates in CI order                                      |
| `make reset`   | delete containers **and** the DB volume — data is gone then |

### Debugging

Xdebug is installed in the dev image but switched off (`XDEBUG_MODE=off`),
because it slows down every request otherwise. To turn it on:

```bash
XDEBUG_MODE=debug make up
```

## Stack

Symfony 7.4 LTS · PHP 8.4 · PostgreSQL 17 · FrankenPHP (worker mode in prod) ·
Twig + UX Live Components · PHPStan 8 · Deptrac · PHPUnit

## Layout

```
src/                     App\  — wiring only, no business logic
templates/base.html.twig layout; renders the navigation from the MenuRegistry
modules/shared-kernel/   Crm\SharedKernel\  — contracts, depends on no module
modules/contact/         Crm\Contact\       — reference module
migrations/              central, because the order has to be global
```

### Two things that can surprise you

- **`composer.lock` is resolved locally under PHP 8.3, the container runs 8.4.**
  That is harmless (8.3-compatible packages run on 8.4), but whoever wants a
  reproducible resolve does it inside `make sh`.
- **Flex rewrites `config/bundles.php`** on every `composer require` and throws
  the comments away in the process. Give it a quick read after larger Composer
  operations.

### A note on language

Documentation and code comments are English. Commit messages and pull request
descriptions are German — that is a deliberate split, not an oversight: the code
is meant to be readable by anyone, the change history is written for the team
that works here.
