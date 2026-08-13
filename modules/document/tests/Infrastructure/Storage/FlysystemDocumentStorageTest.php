<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Infrastructure\Storage;

use Crm\Document\Domain\DocumentFileMissing;
use Crm\Document\Infrastructure\Storage\FlysystemDocumentStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Gegen einen echten Flysystem-Adapter, nicht gegen einen Stub.
 *
 * Der Sinn dieser Klasse ist die Uebersetzung zwischen Flysystem und dem
 * Vertrag des Moduls - und die laesst sich nur pruefen, wenn Flysystem sich
 * auch wirklich mit Dateien beschaeftigt.
 */
final class FlysystemDocumentStorageTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->removeRecursively($directory);
        }

        $this->directories = [];

        parent::tearDown();
    }

    #[Test]
    public function a_string_comes_back_unchanged(): void
    {
        $storage = $this->storage();

        $storage->write('a/b/c', 'Inhalt');

        self::assertSame('Inhalt', stream_get_contents($storage->readStream('a/b/c')));
    }

    #[Test]
    public function a_stream_is_passed_through_as_a_stream(): void
    {
        // Der Unterschied ist nicht kosmetisch: eine 40-MB-Datei als
        // Zeichenkette kostet 40 MB Arbeitsspeicher.
        $storage = $this->storage();
        $source = fopen('php://memory', 'r+b');
        \assert(\is_resource($source));
        fwrite($source, 'Aus einem Stream');
        rewind($source);

        $storage->write('a/b/c', $source);

        self::assertSame('Aus einem Stream', stream_get_contents($storage->readStream('a/b/c')));
    }

    #[Test]
    public function a_missing_file_becomes_the_modules_own_exception(): void
    {
        // Damit die Oberflaeche daraus eine 404 machen kann, ohne die
        // Ausnahmen der Speicherbibliothek zu kennen.
        $this->expectException(DocumentFileMissing::class);

        $this->storage()->readStream('gibt/es/nicht');
    }

    #[Test]
    public function deleting_something_that_is_already_gone_is_not_an_error(): void
    {
        // Loeschen ist idempotent gemeint. Wuerde es werfen, bliebe die
        // Datenbankzeile stehen, weil die Datei fehlt - und der Benutzer
        // wuerde den Eintrag nie los.
        $storage = $this->storage();

        $storage->delete('gibt/es/nicht');

        self::assertFalse($storage->has('gibt/es/nicht'));
    }

    #[Test]
    public function it_knows_what_it_has(): void
    {
        $storage = $this->storage();
        $storage->write('a/b/c', 'Inhalt');

        self::assertTrue($storage->has('a/b/c'));
        self::assertFalse($storage->has('a/b/d'));
    }

    #[Test]
    public function an_unreachable_storage_reports_absence_instead_of_throwing(): void
    {
        // has() wird beim Aufraeumen benutzt. Wirft es, waehrend der Speicher
        // gerade nicht erreichbar ist, bricht das Aufraeumen mittendrin ab.
        $storage = new FlysystemDocumentStorage(new UnreachableFilesystem());

        self::assertFalse($storage->has('a/b/c'));
    }

    private function storage(): FlysystemDocumentStorage
    {
        $directory = sys_get_temp_dir().'/officore-storage-test-'.bin2hex(random_bytes(6));
        $this->directories[] = $directory;

        return new FlysystemDocumentStorage(new Filesystem(new LocalFilesystemAdapter($directory)));
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            \assert($entry instanceof \SplFileInfo);

            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($path);
    }
}

/**
 * Ein Speicher, der bei jedem Zugriff scheitert - etwa ein Bucket, der gerade
 * nicht erreichbar ist.
 */
final class UnreachableFilesystem extends Filesystem
{
    public function __construct()
    {
        parent::__construct(new LocalFilesystemAdapter(sys_get_temp_dir()));
    }

    public function fileExists(string $location): bool
    {
        throw UnableToReadFile::fromLocation($location, 'Bucket nicht erreichbar');
    }
}
