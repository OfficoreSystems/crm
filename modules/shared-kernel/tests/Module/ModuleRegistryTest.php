<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Module;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Crm\SharedKernel\Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    #[Test]
    public function it_indexes_modules_by_name(): void
    {
        $registry = new ModuleRegistry([self::module('contact'), self::module('billing')]);

        self::assertTrue($registry->has('contact'));
        self::assertTrue($registry->has('billing'));
        self::assertFalse($registry->has('inventory'));
        self::assertSame('contact', $registry->get('contact')?->name());
        self::assertNull($registry->get('inventory'));
    }

    #[Test]
    public function it_reports_no_gaps_when_all_dependencies_are_installed(): void
    {
        $registry = new ModuleRegistry([
            self::module('contact'),
            self::module('billing', dependencies: ['contact']),
        ]);

        self::assertSame([], $registry->missingDependencies());
    }

    #[Test]
    public function it_reports_missing_dependencies(): void
    {
        // Der Fall, der ohne diese Pruefung erst zur Laufzeit als
        // "Service not found" auffaellt - und zwar irgendwo tief im Stack.
        $registry = new ModuleRegistry([
            self::module('billing', dependencies: ['contact', 'tax']),
        ]);

        self::assertSame(['billing' => ['contact', 'tax']], $registry->missingDependencies());
    }

    /**
     * @param list<string> $dependencies
     */
    private static function module(string $name, array $dependencies = []): CrmModuleInterface
    {
        return new class($name, $dependencies) implements CrmModuleInterface {
            /**
             * @param list<string> $dependencies
             */
            public function __construct(
                private readonly string $name,
                private readonly array $dependencies,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function version(): string
            {
                return '1.0.0';
            }

            public function dependencies(): array
            {
                return $this->dependencies;
            }
        };
    }
}
