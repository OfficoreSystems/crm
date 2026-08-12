# crm

Modulares CRM auf Symfony 7.4 LTS. Monorepo: jedes Modul ist gleichzeitig ein
Composer-Paket und ein Symfony-Bundle.

## Setup

```bash
git clone https://github.com/OfficoreSystems/crm.git && cd crm
make fresh          # startet Container, installiert, migriert, seedet
```

Danach: <http://localhost:8080/contacts> · Mails unter <http://localhost:8025>

Anmeldung mit `admin@officore.test` / `officore-dev-passwort`. Das Konto legt
`user:seed` an — der Befehl **verweigert die Arbeit in Produktion**. Dort legt
`bin/console user:create` das erste Konto an und gibt ein erzeugtes Passwort
einmalig aus.

## Ein neues Modul anlegen

```bash
cp -r modules/contact modules/invoice     # 1. Referenzmodul kopieren
```

2. In `modules/invoice/composer.json` Name auf `crm/invoice-module` und den
   PSR-4-Prefix auf `Crm\Invoice\` setzen.
3. `Crm\Contact\` → `Crm\Invoice\` in allen Dateien ersetzen, `ContactModule`
   → `InvoiceModule` (auch Twig-Namespace `@ContactModule` → `@InvoiceModule`).
4. `config/bundles.php`: `Crm\Invoice\InvoiceModule::class => ['all' => true]`.
5. `deptrac.yaml`: Layer `InvoiceDomain/Application/Infrastructure/UI` +
   `InvoiceBundle` anlegen und in `ruleset` verdrahten — **ohne diesen Schritt
   schlägt `make arch` fehl**, das ist Absicht.
6. `composer require crm/invoice-module:^0.1 && make migrate`

Routing, Twig-Pfad, Doctrine-Mapping und Menüeintrag ziehen sich von selbst —
der Core wird dafür nicht angefasst.

---

## Die eine Regel

**Der Core kennt kein konkretes Modul, und kein Modul kennt ein anderes.**

Kommunikation läuft ausschließlich über Interfaces im `shared-kernel` und über
Messenger Events. Nur zwei Dateien nennen Modulnamen, und beide sind
Registrierung statt Kopplung: `config/bundles.php` und `deptrac.yaml`.

### Wie Modulrouten geladen werden

Über die Liste der **registrierten Bundles** in
[`src/Kernel.php`](src/Kernel.php), nicht über einen Glob auf `modules/`:

```php
foreach ($this->getBundles() as $bundle) {
    if (!$bundle instanceof CrmModuleInterface) {
        continue;
    }
    // ... <bundle-pfad>/config/routes.php importieren
}
```

Ein Glob läse das Dateisystem, während die Registrierung über
`config/bundles.php` läuft — beides kann auseinanderlaufen. Ein deregistriertes
Modul behielte seine Routen und der erste Aufruf endete mit
*„has no container set"*, also **500 statt 404**.

Der Filter auf `CrmModuleInterface` ist nicht optional: Fremdbundles bringen
ebenfalls `config/routes.php` mit. `LiveComponentBundle` etwa wird von
`config/routes/ux_live_component.yaml` bereits mit dem Präfix `/_components`
importiert; ein zweiter Import ohne Präfix erzeugt
`/{_live_component}/{_live_action}` — eine Route, die **jeden einteiligen Pfad
schluckt**. Abgesichert durch
[`tests/Smoke/RoutingTest.php`](tests/Smoke/RoutingTest.php).

### Modularitätstest

Ein Modul muss sich entfernen lassen, ohne dass die Anwendung bricht:

```bash
# 1. Aus config/bundles.php austragen
# 2. composer remove crm/user-module
make fresh
```

Erwartung: Die Anwendung **degradiert, sie crasht nicht**. `/contacts` liefert
weiter 200, `/login` und `/users` liefern **404** (nicht 500), das Menü zeigt
den Eintrag des Moduls nicht mehr, und `UserFinderInterface` fällt auf
`NullUserFinder` zurück.

### Optionale Extension-Points

Ein Modul, das `UserFinderInterface` injiziert, darf dadurch nicht das
`user`-Modul zur Pflicht machen. Deshalb liefert der Shared Kernel für solche
Verträge eine **Null-Implementierung als Vorgabe**, die das jeweilige Modul
überschreibt:

| Vertrag | Vorgabe im Shared Kernel | Ersetzt durch |
| --- | --- | --- |
| `UserFinderInterface` | `NullUserFinder` (findet niemanden) | `user` |
| `crm.security.user_provider` | `NullUserProvider` (kennt niemanden) | `user` |
| `CompanyFinderInterface` | `NullCompanyFinder` (findet nichts) | `company` |

Das funktioniert, weil der `shared-kernel` in `config/bundles.php` **vor** den
Modulen steht und die spätere Service-Definition gewinnt. Ohne das Modul
degradiert die Anwendung, statt beim Container-Build zu scheitern.

**Eine Ausnahme, die Symfony erzwingt:** `security.firewalls` ist ein
prototypisierter Knoten und muss vollständig aus *einer* Konfigurationsdatei
kommen — ein `prependExtension()` aus dem Modul bricht mit
„You are not allowed to define new elements for path security.firewalls" ab.
Die Firewall steht deshalb in `config/packages/security.yaml` und verweist auf
die feste Service-ID oben, mit literalen Pfaden statt Routennamen.

Das ist keine Vereinbarung, sondern ein CI-Gate:

```bash
make arch     # deptrac analyse --report-uncovered --fail-on-uncovered
```

Der Lauf muss **0 Violations und 0 Uncovered** melden. `--fail-on-uncovered`
ist der wichtigere Schalter: er fängt auch den Fall ab, dass jemand ein neues
Modul anlegt und vergisst, es in `deptrac.yaml` einzutragen.

### Coverage-Gate

Mindestens **80 % Zeilenabdeckung**, erzwungen in der CI durch
[`tools/coverage-gate.php`](tools/coverage-gate.php). PHPUnit selbst kennt kein
„fail under" — das Skript liest den Clover-Report und bricht ab, wenn die
Schwelle gerissen ist. Bei rotem Gate listet es die schwächsten Dateien auf.

Die Schwelle steht an zwei Stellen und muss zusammen gepflegt werden:
`COVERAGE_MIN` im [Makefile](Makefile) und in
[`.github/workflows/ci.yml`](.github/workflows/ci.yml).

**Die Schwelle wird nie gesenkt, um einen Build grün zu bekommen.** Wer sie
reißt, schreibt Tests.

### Verweise über Modulgrenzen

Ein Kontakt gehört zu einer Firma — aber `contact` und `company` sind getrennte
Module. Die Regel dafür, am Beispiel:

| | so **nicht** | sondern |
| --- | --- | --- |
| Spalte | `ManyToOne` auf `Company` | `company_id UUID NULL`, **ohne** Foreign Key |
| Lesen | `$contact->company()->name()` | `CompanyFinderInterface::findMany()` |
| Prüfen | Datenbank-Constraint | `CompanyFinderInterface::exists()` im Use-Case |
| Suchen | `JOIN company_companies` | Name → IDs auflösen, dann eigene Tabelle filtern |

**Kein Foreign Key ist Absicht.** Ein Constraint über die Modulgrenze würde
beide Module aneinanderketten — `company` ließe sich nicht mehr entfernen, ohne
die `contact`-Tabelle zu zerlegen. Der Preis: die Datenbank garantiert die
Gültigkeit nicht. Eine `company_id` kann ins Leere zeigen, und das ist ein
**normaler Zustand**, kein Fehler — die Firma wurde gelöscht, oder das Modul ist
nicht installiert. Aufrufer bekommen dann `null`.

**Suchen ohne Join.** „Zeig mir alle Kontakte von Nordwind" beantwortet
`contact` in zwei Schritten: erst den Begriff über `searchByName()` zu Firmen-IDs
auflösen, dann die eigene Tabelle danach filtern. Zwei Abfragen statt eines
Joins, dafür bleibt die Grenze intakt. Das Repository erfährt nie, was eine
Firma ist — es filtert auf eine Spalte.

**N+1 vermeiden.** Die Liste löst alle Firmen der Seite in *einem*
`findMany()`-Aufruf auf, nicht pro Zeile. Über eine Modulgrenze ist ein N+1
besonders teuer; ein Test hält das fest.

### Schichten innerhalb eines Moduls

| Schicht          | darf sehen                              |
| ---------------- | --------------------------------------- |
| `Domain`         | nichts (nur Mapping-Attribute und Uuid) |
| `Application`    | `Domain`                                |
| `Infrastructure` | `Domain`, `Application`, Doctrine       |
| `UI`             | `Domain`, `Application`, `shared-kernel` |

## Befehle

`make` ohne Argument zeigt die Liste.

| Befehl         | Wirkung                                                  |
| -------------- | -------------------------------------------------------- |
| `make fresh`   | up + install + migrate + seed, gibt die URLs aus          |
| `make up/down` | Container starten / stoppen                               |
| `make build`   | Images neu bauen                                          |
| `make sh`      | Shell im PHP-Container                                    |
| `make logs`    | Logs folgen                                               |
| `make install` | `composer install` im Container                           |
| `make migrate` | Migrationen anwenden                                      |
| `make seed`    | Beispielkontakte anlegen                                  |
| `make test`    | PHPUnit über alle Modul-Suites (legt die Test-DB mit an)  |
| `make coverage`| PHPUnit mit Coverage, failt unter `COVERAGE_MIN` Prozent   |
| `make stan`    | PHPStan Level 8                                           |
| `make arch`    | Deptrac                                                   |
| `make ci`      | alle Gates in CI-Reihenfolge                              |
| `make reset`   | Container **und** DB-Volume löschen — Daten sind dann weg |

### Debuggen

Xdebug ist im Dev-Image installiert, aber aus (`XDEBUG_MODE=off`), weil es
sonst jeden Request bremst. Anschalten:

```bash
XDEBUG_MODE=debug make up
```

## Stack

Symfony 7.4 LTS · PHP 8.4 · PostgreSQL 17 · FrankenPHP (Worker-Mode in Prod) ·
Twig + UX Live Components · PHPStan 8 · Deptrac · PHPUnit

## Aufbau

```
src/                     App\  — nur Wiring, keine Geschäftslogik
templates/base.html.twig Layout; rendert die Navigation aus der MenuRegistry
modules/shared-kernel/   Crm\SharedKernel\  — Verträge, hängt an keinem Modul
modules/contact/         Crm\Contact\       — Referenzmodul
migrations/              zentral, weil die Reihenfolge global sein muss
```

### Zwei Dinge, die überraschen können

- **Die `composer.lock` wird lokal unter PHP 8.3 aufgelöst, der Container läuft
  8.4.** Das ist unkritisch (8.3-kompatible Pakete laufen auf 8.4), aber wer
  reproduzierbar auflösen will, macht das in `make sh`.
- **Flex überschreibt `config/bundles.php`** bei jedem `composer require` und
  wirft dabei die Kommentare weg. Nach größeren Composer-Operationen kurz
  gegenlesen.
