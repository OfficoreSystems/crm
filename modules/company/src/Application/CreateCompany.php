<?php

declare(strict_types=1);

namespace Crm\Company\Application;

use Crm\Company\Domain\Company;
use Crm\Company\Domain\CompanyRepositoryInterface;

final readonly class CreateCompany
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {
    }

    public function __invoke(CreateCompanyCommand $command): Company
    {
        $company = Company::create(
            $command->name,
            $command->industry,
            $command->website,
            $command->email,
            $command->phone,
            $command->address,
        );

        $this->companies->save($company);

        return $company;
    }
}
