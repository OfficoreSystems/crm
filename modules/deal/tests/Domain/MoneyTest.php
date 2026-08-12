<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Domain;

use Crm\Deal\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function it_stores_the_amount_in_cents(): void
    {
        self::assertSame(123456, Money::fromCents(123456)->amount);
        self::assertSame('EUR', Money::fromCents(1)->currency);
    }

    #[Test]
    #[DataProvider('decimals')]
    public function it_parses_decimal_notation(string $input, int $expectedCents): void
    {
        self::assertSame($expectedCents, Money::fromDecimal($input)->amount);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function decimals(): iterable
    {
        yield 'ganze Zahl' => ['1234', 123400];
        yield 'mit Punkt' => ['1234.56', 123456];
        yield 'mit Komma' => ['1234,56', 123456];
        yield 'eine Nachkommastelle' => ['1234.5', 123450];
        yield 'null' => ['0', 0];
        yield 'negativ' => ['-42.50', -4250];
        yield 'mit Leerzeichen' => [' 1 234.56 ', 123456];
    }

    #[Test]
    public function parsing_never_goes_through_a_float(): void
    {
        // 0.1 + 0.2 ist in Fliesskomma nicht 0.3. Ueber Zeichenketten geparst
        // stimmt die Summe exakt - genau darum geht es bei diesem Wertobjekt.
        $sum = Money::fromDecimal('0.10')->plus(Money::fromDecimal('0.20'));

        self::assertSame(30, $sum->amount);
        self::assertSame('0.30', $sum->asDecimal());
    }

    #[Test]
    #[DataProvider('invalidDecimals')]
    public function it_rejects_unparsable_amounts(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromDecimal($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDecimals(): iterable
    {
        yield 'leer' => [''];
        yield 'Buchstaben' => ['zwoelf'];
        yield 'drei Nachkommastellen' => ['1.234'];
        yield 'mit Waehrung' => ['1234 EUR'];
        yield 'zwei Trenner' => ['1.2.3'];
    }

    #[Test]
    public function it_adds_amounts_of_the_same_currency(): void
    {
        $sum = Money::fromCents(1000)->plus(Money::fromCents(2500));

        self::assertSame(3500, $sum->amount);
    }

    #[Test]
    public function it_refuses_to_add_different_currencies(): void
    {
        // Ohne diese Pruefung addiert man irgendwann Euro und Franken zu
        // einer Zahl, die nichts bedeutet.
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(1000, 'EUR')->plus(Money::fromCents(1000, 'CHF'));
    }

    #[Test]
    public function it_normalises_the_currency_code(): void
    {
        self::assertSame('CHF', Money::fromCents(1, ' chf ')->currency);
    }

    #[Test]
    #[DataProvider('invalidCurrencies')]
    public function it_rejects_anything_but_a_three_letter_code(string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(1, $currency);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencies(): iterable
    {
        yield 'Symbol' => ['€'];
        yield 'zwei Buchstaben' => ['EU'];
        yield 'vier Buchstaben' => ['EURO'];
        yield 'leer' => [''];
    }

    #[Test]
    public function it_renders_decimal_notation(): void
    {
        self::assertSame('1234.56', Money::fromCents(123456)->asDecimal());
        self::assertSame('0.05', Money::fromCents(5)->asDecimal());
        self::assertSame('0.00', Money::zero()->asDecimal());
        self::assertSame('-42.50', Money::fromCents(-4250)->asDecimal());
    }

    #[Test]
    public function it_answers_the_obvious_questions(): void
    {
        self::assertTrue(Money::zero()->isZero());
        self::assertFalse(Money::fromCents(1)->isZero());
        self::assertTrue(Money::fromCents(-1)->isNegative());
        self::assertFalse(Money::zero()->isNegative());
        self::assertTrue(Money::fromCents(200)->isGreaterThan(Money::fromCents(100)));
        self::assertTrue(Money::fromCents(100)->equals(Money::fromCents(100)));
        self::assertFalse(Money::fromCents(100, 'EUR')->equals(Money::fromCents(100, 'CHF')));
    }

    #[Test]
    public function comparing_different_currencies_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(1, 'EUR')->isGreaterThan(Money::fromCents(1, 'CHF'));
    }
}
