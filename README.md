# crm

Modulares CRM auf Symfony 7.4 LTS. Monorepo: jedes Modul ist gleichzeitig ein
Composer-Paket und ein Symfony-Bundle.

## Setup

```bash
git clone https://github.com/OfficoreSystems/crm.git && cd crm
make fresh          # startet Container, installiert, migriert, seedet
```

Danach: <http://localhost:8080/contacts> · Mails unter <http://localhost:8025>

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

Das ist keine Vereinbarung, sondern ein CI-Gate:

```bash
make arch     # deptrac analyse --report-uncovered --fail-on-uncovered
```

Der Lauf muss **0 Violations und 0 Uncovered** melden. `--fail-on-uncovered`
ist der wichtigere Schalter: er fängt auch den Fall ab, dass jemand ein neues
Modul anlegt und vergisst, es in `deptrac.yaml` einzutragen.

### Schichten innerhalb eines Moduls

| Schicht          | darf sehen                              |
| ---------------- | --------------------------------------- |
| `Domain`         | nichts (nur Mapping-Attribute und Uuid) |
| `Application`    | `Domain`                                |
| `Infrastructure` | `Domain`, `Application`, Doctrine       |
| `UI`             | `Domain`, `Application`, `shared-kernel` |

## Befehle

| Befehl         | Wirkung                                            |
| -------------- | -------------------------------------------------- |
| `make fresh`   | up + install + migrate + seed, gibt die URLs aus    |
| `make up/down` | Container starten / stoppen                         |
| `make sh`      | Shell im PHP-Container                              |
| `make install` | `composer install`                                  |
| `make migrate` | Migrationen anwenden                                |
| `make test`    | PHPUnit über alle Modul-Suites                      |
| `make stan`    | PHPStan Level 8                                     |
| `make arch`    | Deptrac                                             |
| `make ci`      | alle Gates in CI-Reihenfolge                        |

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
