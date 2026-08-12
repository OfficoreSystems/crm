<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Domain;

use Crm\Company\Domain\Address;
use Crm\Company\Domain\Company;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class CompanyTest extends TestCase
{
    #[Test]
    public function it_trims_the_name(): void
    {
        self::assertSame('Nordwind Logistik', Company::create('  Nordwind Logistik  ')->name());
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Company::create('   ');
    }

    #[Test]
    public function it_starts_without_an_address(): void
    {
        self::assertTrue(Company::create('Nordwind')->address()->isEmpty());
    }

    #[Test]
    public function optional_fields_normalise_to_null(): void
    {
        $company = Company::create('Nordwind', '  ', '', '   ', '');

        self::assertNull($company->industry());
        self::assertNull($company->website());
        self::assertNull($company->email());
        self::assertNull($company->phone());
    }

    #[Test]
    #[DataProvider('websites')]
    public function it_completes_a_missing_url_scheme(string $input, string $expected): void
    {
        // Ohne Schema haengt der Browser die Adresse an die aktuelle URL an -
        // der Link zeigt dann ins eigene CRM statt zur Firma.
        self::assertSame($expected, Company::create('Nordwind', website: $input)->website());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function websites(): iterable
    {
        yield 'ohne Schema' => ['nordwind.example', 'https://nordwind.example'];
        yield 'mit https' => ['https://nordwind.example', 'https://nordwind.example'];
        yield 'mit http bleibt http' => ['http://nordwind.example', 'http://nordwind.example'];
        yield 'mit Pfad' => ['nordwind.example/impressum', 'https://nordwind.example/impressum'];
    }

    #[Test]
    public function it_rejects_an_unusable_website(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Company::create('Nordwind', website: 'https://');
    }

    #[Test]
    public function it_normalises_the_email(): void
    {
        self::assertSame('info@nordwind.example', Company::create('Nordwind', email: '  Info@Nordwind.Example ')->email());
    }

    #[Test]
    public function it_rejects_an_invalid_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Company::create('Nordwind', email: 'keine-adresse');
    }

    #[Test]
    public function it_can_be_changed(): void
    {
        $company = Company::create('Nordwind');

        $company->rename(' Nordwind Logistik ');
        $company->changeIndustry(' Logistik ');
        $company->changeWebsite('nordwind.example');
        $company->changeEmail('INFO@nordwind.example');
        $company->changePhone(' +49 40 123456 ');
        $company->moveTo(new Address('Am Hafen 12', '20457', 'Hamburg', 'DE'));

        self::assertSame('Nordwind Logistik', $company->name());
        self::assertSame('Logistik', $company->industry());
        self::assertSame('https://nordwind.example', $company->website());
        self::assertSame('info@nordwind.example', $company->email());
        self::assertSame('+49 40 123456', $company->phone());
        self::assertSame('Hamburg', $company->address()->city);
    }

    #[Test]
    public function changing_optional_fields_to_blank_clears_them(): void
    {
        $company = Company::create('Nordwind', 'Logistik', 'nordwind.example', 'info@nordwind.example', '040');

        $company->changeIndustry(null);
        $company->changeWebsite('');
        $company->changeEmail('  ');
        $company->changePhone(null);

        self::assertNull($company->industry());
        self::assertNull($company->website());
        self::assertNull($company->email());
        self::assertNull($company->phone());
    }

    #[Test]
    public function renaming_rejects_a_blank_name(): void
    {
        $company = Company::create('Nordwind');

        $this->expectException(\InvalidArgumentException::class);

        $company->rename('  ');
    }

    #[Test]
    public function it_assigns_a_sortable_uuid_and_keeps_the_timestamp(): void
    {
        $moment = new \DateTimeImmutable('2026-03-01 09:15:00');

        $company = Company::create('Nordwind', createdAt: $moment);

        self::assertInstanceOf(UuidV7::class, $company->id());
        self::assertSame($moment, $company->createdAt());
    }
}
