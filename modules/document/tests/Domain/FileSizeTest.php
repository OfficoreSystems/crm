<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Domain;

use Crm\Document\Domain\FileSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSizeTest extends TestCase
{
    #[Test]
    #[DataProvider('sizes')]
    public function it_reads_like_a_file_manager(int $bytes, string $expected): void
    {
        self::assertSame($expected, FileSize::humanize($bytes));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function sizes(): iterable
    {
        yield 'nichts' => [0, '0 B'];
        yield 'Bytes ohne Nachkomma' => [512, '512 B'];
        yield 'genau ein Kilobyte' => [1024, '1.0 KB'];
        yield 'krumme Kilobytes' => [1536, '1.5 KB'];
        yield 'Megabyte' => [26214400, '25.0 MB'];
        yield 'Gigabyte' => [2147483648, '2.0 GB'];
        yield 'Terabyte' => [1099511627776, '1.0 TB'];

        // Groesser als die groesste Einheit: lieber eine grosse Zahl in TB als
        // eine Einheit, die niemand kennt.
        yield 'jenseits von Terabyte' => [2199023255552, '2.0 TB'];
    }

    #[Test]
    public function a_negative_size_is_treated_as_nothing(): void
    {
        // Sollte nicht vorkommen - aber "-1.0 KB" in einer Oberflaeche sieht
        // nach einem Fehler aus, den niemand findet.
        self::assertSame('0 B', FileSize::humanize(-5));
    }
}
