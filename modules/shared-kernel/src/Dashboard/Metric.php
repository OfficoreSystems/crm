<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Eine Kennzahl fuer die Startseite.
 *
 * Label und Beschreibung sind *Uebersetzungsschluessel*, keine fertigen Texte.
 * Uebersetzt wird im Dashboard-Template. Der Umweg hat einen Grund: die
 * Kennzahlen entstehen in der Infrastructure-Schicht der Module, und die darf
 * Symfony nicht sehen - ein injizierter Uebersetzer waere genau diese
 * Abhaengigkeit.
 *

 * Der Wert ist eine *Zeichenkette*, keine Zahl. Das ist Absicht: ein
 * Geldbetrag, eine Prozentangabe und eine Anzahl haben nichts gemeinsam ausser
 * dass sie angezeigt werden. Wuerde hier ein int oder float stehen, muesste
 * die Formatierung ins Dashboard wandern - und das muesste dann wissen, dass
 * deal in Cent rechnet.
 *
 * Das liefernde Modul weiss am besten, wie sein Wert zu lesen ist.
 */
final readonly class Metric
{
    /**
     * @param string                    $key             Eindeutig, nach dem Muster "modul.kennzahl".
     * @param string                    $label           Uebersetzungsschluessel, kein fertiger Text.
     * @param string                    $value           Bereits formatiert - das liefernde Modul weiss
     *                                                   am besten, wie sein Wert zu lesen ist.
     * @param string|null               $description     Uebersetzungsschluessel.
     * @param array<string, string|int> $parameters      Platzhalter fuer Label und Beschreibung,
     *                                                   etwa %count%.
     * @param array<string, string|int> $routeParameters
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
        public string|TranslatableInterface|null $description = null,
        public ?string $route = null,
        public array $routeParameters = [],
        public int $priority = 0,
        public MetricTone $tone = MetricTone::NEUTRAL,
        public array $parameters = [],
        /**
         * Waehrungscode, wenn der Wert ein Geldbetrag ist - sonst null.
         *
         * Nur dann darf das Dashboard den Wert umformatieren. Ohne diese
         * Angabe muesste es raten, und "50 %" oder "12" nach
         * Waehrungsregeln zu formatieren waere schlimmer als gar nichts.
         *
         * Der Grund fuer die Ausnahme: Tausendertrennung und Dezimalzeichen
         * haengen an der Sprache, die Zahl selbst nicht. Ein vom Modul
         * fertig formatierter Betrag stuende in jeder Sprache gleich da -
         * und in mindestens einer falsch.
         */
        public ?string $currency = null,
    ) {
        self::assertValidKey($key);

        if ('' === trim($label)) {
            throw new \InvalidArgumentException('Metric.label darf nicht leer sein.');
        }
    }

    public function isLinkable(): bool
    {
        return null !== $this->route;
    }

    /**
     * Das Modul, aus dem die Kennzahl stammt - der Teil vor dem Punkt.
     */
    public function module(): string
    {
        return substr($this->key, 0, (int) strpos($this->key, '.'));
    }

    /**
     * Der Praefix zwingt zur Namensraumtrennung. Ohne ihn heisst die
     * Kennzahl in drei Modulen "total", und wer zuletzt registriert wird,
     * gewinnt - lautlos.
     */
    private static function assertValidKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9-]{1,39}\.[a-z][a-z0-9_]{1,39}$/', $key)) {
            throw new \InvalidArgumentException(sprintf(
                'Metric-Schluessel "%s" ist ungueltig: erwartet wird "modul.kennzahl", beides klein geschrieben.',
                $key,
            ));
        }
    }
}
