<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Double;

use Crm\Document\Domain\DocumentFileMissing;
use Crm\Document\Domain\DocumentStorageInterface;

/**
 * Der Objektspeicher als Array.
 *
 * Genau dafuer gibt es {@see DocumentStorageInterface}: die Use-Cases lassen
 * sich pruefen, ohne dass ein MinIO-Container laufen muss.
 */
final class InMemoryDocumentStorage implements DocumentStorageInterface
{
    /**
     * @var array<string, string>
     */
    public array $files = [];

    public function write(string $key, mixed $contents): void
    {
        $this->files[$key] = \is_resource($contents)
            ? (string) stream_get_contents($contents)
            : (string) $contents;
    }

    public function readStream(string $key): mixed
    {
        if (!isset($this->files[$key])) {
            throw DocumentFileMissing::at($key);
        }

        $stream = fopen('php://memory', 'r+b');
        \assert(\is_resource($stream));

        fwrite($stream, $this->files[$key]);
        rewind($stream);

        return $stream;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->files[$key]);
    }
}
