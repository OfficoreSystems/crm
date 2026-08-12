<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Domain;

use Crm\Company\Domain\Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    #[Test]
    public function it_trims_every_field(): void
    {
        $address = new Address('  Am Hafen 12 ', ' 20457 ', ' Hamburg ', ' de ');

        self::assertSame('Am Hafen 12', $address->street);
        self::assertSame('20457', $address->postalCode);
        self::assertSame('Hamburg', $address->city);
        self::assertSame('DE', $address->country);
    }

    #[Test]
    public function blank_fields_become_null(): void
    {
        $address = new Address('   ', '', null, '');

        self::assertNull($address->street);
        self::assertNull($address->postalCode);
        self::assertNull($address->city);
        self::assertNull($address->country);
        self::assertTrue($address->isEmpty());
    }

    #[Test]
    public function an_address_with_any_field_is_not_empty(): void
    {
        self::assertFalse((new Address(city: 'Hamburg'))->isEmpty());
        self::assertTrue(Address::empty()->isEmpty());
    }

    #[Test]
    public function it_uppercases_the_country_code(): void
    {
        self::assertSame('CH', (new Address(country: 'ch'))->country);
    }

    #[Test]
    #[DataProvider('invalidCountries')]
    public function it_rejects_anything_but_a_two_letter_code(string $country): void
    {
        // Ohne diese Festlegung stehen "de", "DE", "Deutschland" und "Germany"
        // nebeneinander in derselben Spalte.
        $this->expectException(\InvalidArgumentException::class);

        new Address(country: $country);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCountries(): iterable
    {
        yield 'ausgeschrieben' => ['Deutschland'];
        yield 'drei Buchstaben' => ['DEU'];
        yield 'ein Buchstabe' => ['D'];
        yield 'mit Ziffer' => ['D1'];
    }

    #[Test]
    public function it_renders_a_single_line(): void
    {
        $address = new Address('Am Hafen 12', '20457', 'Hamburg', 'DE');

        self::assertSame('Am Hafen 12, 20457 Hamburg, DE', $address->asLine());
    }

    #[Test]
    public function the_line_skips_missing_parts_without_stray_separators(): void
    {
        self::assertSame('Hamburg', (new Address(city: 'Hamburg'))->asLine());
        self::assertSame('20457 Hamburg', (new Address(postalCode: '20457', city: 'Hamburg'))->asLine());
        self::assertSame('Am Hafen 12, DE', (new Address(street: 'Am Hafen 12', country: 'DE'))->asLine());
        self::assertSame('', Address::empty()->asLine());
    }
}
