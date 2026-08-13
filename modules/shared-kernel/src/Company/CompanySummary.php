<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Company;

/**
 * How other modules see a company.
 *
 * Like {@see \Crm\SharedKernel\User\UserSummary} deliberately a flat copy: a
 * module that only wants to display a company name should not depend on the
 * Doctrine mapping of the company module.
 */
final readonly class CompanySummary
{
    /**
     * @param string      $id       UUID as a string - scalar, because no
     *                              association crosses module boundaries.
     * @param string|null $industry Industry, for reporting.
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $industry = null,
        public ?string $city = null,
    ) {
    }
}
