<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Application;

use Crm\Company\Application\CreateCompany;
use Crm\Company\Application\CreateCompanyCommand;
use Crm\Company\Domain\Address;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateCompanyTest extends TestCase
{
    #[Test]
    public function it_persists_the_new_company(): void
    {
        $companies = new InMemoryCompanyRepository();

        $company = (new CreateCompany($companies))(new CreateCompanyCommand('Nordwind Logistik', 'Logistik'));

        self::assertSame(1, $companies->countAll());
        self::assertSame($company, $companies->find($company->id()));
        self::assertSame('Logistik', $company->industry());
    }

    #[Test]
    public function it_passes_the_address_through(): void
    {
        $companies = new InMemoryCompanyRepository();

        $company = (new CreateCompany($companies))(new CreateCompanyCommand(
            'Nordwind',
            address: new Address('Am Hafen 12', '20457', 'Hamburg', 'DE'),
        ));

        self::assertSame('Hamburg', $company->address()->city);
    }

    #[Test]
    public function a_company_without_an_address_gets_an_empty_one(): void
    {
        $company = (new CreateCompany(new InMemoryCompanyRepository()))(new CreateCompanyCommand('Nordwind'));

        self::assertTrue($company->address()->isEmpty());
    }

    #[Test]
    public function it_stores_nothing_when_the_name_is_blank(): void
    {
        $companies = new InMemoryCompanyRepository();

        try {
            (new CreateCompany($companies))(new CreateCompanyCommand('   '));
            self::fail('Ein leerer Name haette abgelehnt werden muessen.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $companies->countAll());
        }
    }

    #[Test]
    public function duplicate_names_are_allowed(): void
    {
        // Anders als beim Team gibt es hier bewusst keine Eindeutigkeit:
        // zwei Firmen duerfen gleich heissen, und ein Zwang zur Eindeutigkeit
        // wuerde beim Import echter Daten sofort im Weg stehen.
        $companies = new InMemoryCompanyRepository();
        $createCompany = new CreateCompany($companies);

        $createCompany(new CreateCompanyCommand('Nordwind'));
        $createCompany(new CreateCompanyCommand('Nordwind'));

        self::assertSame(2, $companies->countAll());
    }
}
