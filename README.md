# crm

Modulares CRM auf Symfony 7.4 LTS. Monorepo: jedes Modul ist gleichzeitig ein
Composer-Paket und ein Symfony-Bundle.

## Setup

```bash
git clone https://github.com/OfficoreSystems/crm.git && cd crm
make fresh          # startet Container, installiert, migriert, seedet
```

Danach: <http://localhost:8080/contacts> · Mails unter <http://localhost:8025> ·
Objektspeicher unter <http://localhost:9001> (`officore` / `officore-dev-passwort`)

`user:seed` legt **drei** Konten an, alle mit dem Passwort
`officore-dev-passwort`:

| Konto | Rolle | Team |
| --- | --- | --- |
| `admin@officore.test` | Administrator | Vertrieb |
| `vertrieb@officore.test` | Benutzer | Vertrieb |
| `innendienst@officore.test` | Benutzer | Innendienst |

Drei und nicht eines, weil sich mit einem einzigen Administrator nicht
erkennen lässt, ob die Rechte überhaupt greifen — er darf ohnehin alles.
Melde dich nacheinander als `vertrieb@` und `innendienst@` an: dieselbe
Pipeline, unterschiedliche Zeilen. Siehe [Rechte](#rechte).

Der Befehl **verweigert die Arbeit in Produktion**. Dort legt
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

### Polymorphe Verweise

Eine Aktivität hängt mal an einem Kontakt, mal an einer Firma, mal an einer
Verkaufschance. Ein Fremdschlüssel scheidet hier schon technisch aus,
unabhängig von der Modulgrenze. Stattdessen `SubjectRef` — **Typ + ID als zwei
Skalare** — plus `SubjectResolverInterface` im Shared Kernel.

Der Typ ist eine Zeichenkette und **kein Enum**: ein Enum müsste alle Typen
kennen und läge damit im Shared Kernel, sodass jedes neue Modul eine Änderung
daran wäre. Genau das soll der Extension-Point vermeiden.

Ein Modul macht seine Datensätze verweisbar, indem es das Interface
implementiert — mehr nicht. Die Autoconfiguration übernimmt den Rest:

```php
final class ContactSubjectResolver implements SubjectResolverInterface
{
    public function type(): string { return 'contact'; }
    public function resolve(array $ids): array { /* ... */ }
    public function search(string $query, int $limit = 10): array { /* ... */ }
}
```

**`resolve()` nimmt eine Liste, keine einzelne ID.** Die `SubjectResolverRegistry`
gruppiert nach Typ und ruft jeden Resolver **genau einmal** auf — eine Timeline
mit 50 Einträgen über drei Module kostet drei Aufrufe, nicht 50.

**`search()` gehört dazu**, obwohl es nach „auflösen" klingt: wer etwas an ein
Subjekt hängen will, muss vorher eines auswählen können. Ohne diese Methode
müsste jedes solche Modul die konkreten Finder von `contact`, `company` und
`deal` kennen — der Extension-Point wäre nur halb.

Fehlt ein Resolver, fehlt der Eintrag im Ergebnis. Die Timeline zeigt dann
„Bezug nicht auflösbar" — die Historie bleibt, nur der Name fehlt.

**Der Beleg, dass der Schnitt trägt:** das `search`-Modul besitzt **keine
eigene Tabelle und keine Migration**. Es fragt nur die Registry und sortiert.
Ein neues Modul wird durchsuchbar, indem es einen Resolver mitbringt — `search`
erfährt davon nichts.

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
| `ContactFinderInterface` | `NullContactFinder` (findet nichts) | `contact` |

### Die fünf Extension-Points

Ein Modul dockt an, indem es ein Interface implementiert — mehr nicht. Die
Autoconfiguration im `CrmSharedKernelBundle` übernimmt die Registrierung, und
kein bestehendes Modul erfährt davon.

| Interface | Wozu | Registry |
| --- | --- | --- |
| `MenuProviderInterface` | Navigationseinträge | `MenuRegistry` |
| `CrmModuleInterface` | Selbstbeschreibung (Name, Version, Abhängigkeiten) | `ModuleRegistry` |
| `SubjectResolverInterface` | Datensätze als polymorphes Ziel verweisbar machen | `SubjectResolverRegistry` |
| `MetricProviderInterface` | Kennzahlen für die Übersicht | `MetricRegistry` |
| `RecordOwnershipInterface` | Wem ein Datensatz gehört, und in welchen Spalten | `OwnershipRegistry` |

Ein Test in `CrmSharedKernelBundleTest` prüft diese Liste **abschließend** —
ein sechster Extension-Point lässt ihn rot werden. Das ist Absicht: die
öffentliche Schnittstelle soll nicht nebenbei wachsen.

**Kennzahlen kommen fertig aggregiert.** Das Dashboard rechnet nichts und
fragt keine fremde Tabelle ab — jedes Modul zählt selbst, mit den Abfragen
die es ohnehin hat. Deshalb ist `Metric::$value` eine Zeichenkette: ein
Geldbetrag, eine Prozentangabe und eine Anzahl haben nichts gemeinsam außer
dass sie angezeigt werden.

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

### Rechte

Zwei Mechanismen, die verschiedene Fragen beantworten. Beide werden gebraucht
— wer einen weglässt, hat ein Loch.

| | Frage | Wo |
| --- | --- | --- |
| **Voter** | Darf dieser Benutzer *diesen* Datensatz? | `#[IsGranted]` am Controller |
| **Doctrine-Filter** | Welche Zeilen bekommt er überhaupt geladen? | `RecordVisibilityFilter`, in SQL |

Der Voter allein reicht für Listen nicht: eine Seite mit fünfzig Zeilen würde
fünfzig Mal abstimmen, und die Zeilen wären zu dem Zeitpunkt längst geladen.
Der Filter allein reicht nicht, weil er nur Abfragen einschränkt.

**Das Attribut heißt `modul.aktion`**, kleingeschrieben:

```php
#[IsGranted('deal.view')]                  // Listenseite: darf er das Modul?
#[IsGranted('deal.view', subject: 'deal')] // Detailseite: darf er den Datensatz?
```

Ein einzelnes Wort als `subject:` wäre von Symfony als *Controller-Argument*
gelesen worden — daher das zusammengesetzte Attribut statt zweier Parameter.

**Die Rechtematrix** (`PermissionMatrix::default()`) ordnet Rolle → Modul →
Aktion einen Scope zu: `all`, `team` oder `own`. Ein benannter Moduleintrag
fällt **nicht** auf den Platzhalter `*` zurück — sonst ließe sich „dieses Modul
darf niemand" gar nicht ausdrücken, und die Benutzerverwaltung war für alle
sichtbar.

**Ein Modul macht mit**, indem es `RecordOwnershipInterface` implementiert:
Modulname, Besitzer und Team eines Objekts — plus die Spaltennamen für den
Filter. Die Spalten stehen dort und **nicht als Attribut an der Entity**: die
Domain-Schicht hängt an nichts, und ein Attribut aus dem Shared Kernel wäre
genau so eine Abhängigkeit. Ein Modul ohne Implementierung hat keine
Besitzverhältnisse — seine Daten gehören allen, was für Stammdaten wie Firmen
und Kontakte richtig ist.

Zwei Fallen, die Zeit gekostet haben:

- **Der Filter ist ohne seinen Configurator wirkungslos.** Er wird pro Request
  von `RecordVisibilityConfigurator` scharfgeschaltet und parametrisiert. Fehlt
  der als Service-Definition, sieht die Anwendung völlig normal aus — sie zeigt
  nur zu viel. Deshalb prüft `RecordVisibilityTest` mit echten Requests, dass
  zwei Benutzer denselben Datensatz **unterschiedlich** zu sehen bekommen.
- **In Funktionstests muss die Identity Map geleert werden.** Der
  `KernelBrowser` startet den Kernel vor dem *ersten* Request nicht neu; ein
  soeben angelegter Datensatz liegt dann noch im EntityManager und wird ohne
  SQL — also ohne Filter — zurückgegeben. `SignsIn::signIn()` erledigt das.

### Dateien

Dokumente hängen wie Aktivitäten an einem **polymorphen Verweis** — Typ plus
ID, kein Fremdschlüssel. Das Modul weiß nicht, was ein Kontakt ist.

Die Datei selbst liegt im Objektspeicher, in der Datenbank steht nur, wo.
Lokal ist das MinIO aus [compose.yaml](compose.yaml), in Produktion S3, R2 oder
Hetzner — derselbe Adapter, andere Werte aus der Umgebung. In Tests ein
lokales Verzeichnis, damit die Suite ohne laufenden Container durchläuft.

Drei Entscheidungen, die man kennen sollte:

- **Der Speicherschlüssel hat nichts mit dem Dateinamen zu tun.** Er lautet
  `<typ>/<jahr>/<monat>/<uuid>`. Aus dem Dateinamen abgeleitet hätte er zwei
  Probleme, von denen das zweite das schlimmere ist: zwei Benutzer würden sich
  gegenseitig überschreiben, und wer den Dateinamen kennt, kennt den
  Speicherort.
- **Erst die Datei, dann die Datenbankzeile** — und beim Scheitern wird die
  Datei wieder gelöscht. Es gibt keine gemeinsame Transaktion, also entscheidet
  die Reihenfolge, welcher Fehlerfall übrig bleibt: eine verwaiste Datei ist
  aufräumbar, ein Eintrag ohne Datei zeigt dem Benutzer etwas, das beim Klick
  nicht existiert.
- **Der Download läuft durch die Anwendung**, nicht über eine Bucket-URL. Eine
  öffentliche URL wäre billiger, würde aber die Rechte aushebeln: wer den Link
  hat, hätte die Datei. Ausgeliefert wird immer als `attachment` mit
  `X-Content-Type-Options: nosniff` — eine hochgeladene HTML- oder SVG-Datei
  liefe sonst im Ursprung der Anwendung.

Löschen ist per Vorgabe dem Administrator vorbehalten, wie bei Chancen und
Aktivitäten. Wer das ändern will, ergänzt in `PermissionMatrix::default()`
einen `document`-Eintrag — das Modul selbst braucht dafür keine Änderung.

### Termine und der ICS-Feed

**Alle Zeiten sind UTC** — in der Datenbank, im Speicher, im Feed. Die
Umrechnung passiert an genau zwei Stellen: beim Lesen der Eingabe (der
Controller nimmt `Europe/Berlin` an) und beim Anzeigen. Dazwischen nie.

Ein `datetime-local`-Feld liefert Ortszeit *ohne* Zeitzone — welche gemeint
ist, weiß der Browser, sagt es aber nicht. Die Annahme steht deshalb an einer
Stelle im Controller und ist der Ort, an dem eine Zeitzone je Benutzer
andocken würde.

**Ganztägige Termine sind Daten, keine Zeitpunkte.** Wer sie erst nach UTC
umrechnet und dann die Uhrzeit abschneidet, verliert östlich von Greenwich
einen Tag: aus Mitternacht in Berlin wird 22:00 des Vortags. Im ICS stehen sie
als `VALUE=DATE` ganz ohne Zeitzone, und `DTEND` ist der **Folgetag** — mit
23:59:59 zeigt Outlook den Termin über zwei Tage.

#### Der öffentliche Bereich

Outlook, Google und Apple rufen den Feed ohne Sitzung ab und können keine
bekommen. Ihr einziger Ausweis ist ein Token in der URL. Solche Endpunkte
liegen unter dem generischen Präfix `/oeffentlich/`, das
[security.yaml](config/packages/security.yaml) freigibt — generisch, damit der
Core nicht weiß, welche Module ihn nutzen. Später kommen Abmeldelinks und
Webhooks dazu.

Der Grund für den Umweg: `security.access_control` lässt sich — wie
`security.firewalls` — **nicht** aus einem Modul heraus prependen. Symfony
bricht mit „cannot be overwritten" ab. Ein Test im calendar-Modul hält fest,
dass wir es gar nicht erst versuchen.

Was daraus für den Feed folgt:

- Gespeichert wird nur der **SHA-256-Hash** des Tokens, gesucht wird danach.
  Wer die Datenbank liest, kann keinen Feed abrufen.
- Die URL wird **einmal** angezeigt. Danach nie wieder — sonst wäre der Hash
  Dekoration. Wer sie verliert, erzeugt eine neue; die alte ist sofort wertlos.
- Der Feed enthält **nur die Termine seines Besitzers**, ausdrücklich in der
  Abfrage. Der Doctrine-Sichtbarkeitsfilter hilft hier nicht: ohne
  angemeldeten Benutzer ist er abgeschaltet. Wer sich auf ihn verließe,
  lieferte die Termine aller Benutzer aus.
- Ein unbekanntes Token bekommt **404 ohne Erklärung**. Ein „Token abgelaufen"
  wäre die Bestätigung, dass es das Token gab.

### Sprachen

**Englisch ist die Standardsprache**, Deutsch die zweite. Die Liste steht an
zwei Orten, die zusammenpassen müssen — `enabled_locales` in
[translation.yaml](config/packages/translation.yaml) und die Aufzählung
`Crm\SharedKernel\Localization\Locale`. Ein Test hält beide zusammen, denn
laufen sie auseinander, zeigt die Anwendung eine andere Sprache an, als der
Umschalter behauptet.

Die Wahl hängt **am Konto**, nicht an der Sitzung: sie soll auch nach dem
nächsten Anmelden gelten und später für Mails, bei denen es keine Sitzung
gibt. `null` in der Spalte heißt „nie gewählt" — der Unterschied zu `'en'`
zählt, wenn sich die Standardsprache einmal ändert.

Die Kette ist länger, als sie aussieht:

```
Konto → SecurityUser → ActorInterface → ActorLocaleListener
                                          ├→ Request
                                          └→ LocaleSwitcher → Übersetzer
```

Der Listener läuft bei **Priority 5**, also *nach* der Firewall (8) — vorher
gibt es keinen angemeldeten Benutzer. Damit kommt er zu spät für Symfonys
`LocaleAwareListener` (15) und setzt deshalb beides selbst. Nur eines von
beidem zu setzen ergibt eine Anwendung, die halb übersetzt ist, und zwar je
nach Stelle unterschiedlich.

**Texte gehören zum Modul.** Jedes Modul bringt sein eigenes `translations/`
mit und meldet es in seiner Bundle-Klasse an — genauso wie Twig-Pfad und
Doctrine-Mapping. Der Core hat nur den Katalog für das Grundlayout.

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
