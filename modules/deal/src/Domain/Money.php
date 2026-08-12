<?php

declare(strict_types=1);

namespace Crm\Deal\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Geldbetrag als Wertobjekt.
 *
 * Der Betrag liegt in der kleinsten Einheit als Ganzzahl - Cent, nicht Euro.
 * Fliesskomma ist fuer Geld untauglich: 0.1 + 0.2 ergibt in PHP nicht 0.3,
 * und bei Summen ueber eine Pipeline addieren sich diese Abweichungen zu
 * Betraegen, die niemand erklaeren kann.
 *
 * Die Waehrung wird mitgefuehrt und beim Addieren geprueft. Ohne das addiert
 * man irgendwann Euro und Franken zu einer Zahl, die nichts bedeutet.
 */
#[ORM\Embeddable]
final readonly class Money
{
    /**
     * Betrag in der kleinsten Einheit der Waehrung.
     */
    #[ORM\Column(type: 'bigint')]
    public int $amount;

    #[ORM\Column(length: 3)]
    public string $currency;

    private function __construct(int $amount, string $currency)
    {
        $this->amount = $amount;
        $this->currency = self::normalizeCurrency($currency);
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return new self(0, $currency);
    }

    public static function fromCents(int $amount, string $currency = 'EUR'): self
    {
        return new self($amount, $currency);
    }

    /**
     * Aus einer Dezimalschreibweise wie "1234.56" oder "1234,56".
     *
     * Bewusst ueber Zeichenketten statt ueber float: der Umweg ueber
     * Fliesskomma wuerde genau den Fehler einbauen, den dieses Wertobjekt
     * vermeiden soll.
     */
    public static function fromDecimal(string $amount, string $currency = 'EUR'): self
    {
        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($amount));

        if (1 !== preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new \InvalidArgumentException(sprintf('"%s" ist kein gueltiger Geldbetrag.', $amount));
        }

        $cents = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '0', 2, '0');

        return new self('-' === $matches[1] ? -$cents : $cents, $currency);
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /**
     * Dezimalschreibweise ohne Waehrungssymbol, z. B. "1234.56".
     *
     * Formatierung fuer die Anzeige gehoert ins Template - dort kennt man die
     * Sprache des Benutzers, hier nicht.
     */
    public function asDecimal(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $absolute = abs($this->amount);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(sprintf(
                'Betraege in %s und %s lassen sich nicht verrechnen.',
                $this->currency,
                $other->currency,
            ));
        }
    }

    private static function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        if (1 !== preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new \InvalidArgumentException(sprintf(
                'Waehrung muss ein dreibuchstabiger ISO-4217-Code sein, "%s" ist keiner.',
                $currency,
            ));
        }

        return $normalized;
    }
}
