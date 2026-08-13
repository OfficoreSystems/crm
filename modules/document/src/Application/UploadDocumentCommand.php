<?php

declare(strict_types=1);

namespace Crm\Document\Application;

use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final readonly class UploadDocumentCommand
{
    /**
     * @param resource|string $contents Ein Stream, wo immer es geht - eine
     *                                  40-MB-Datei als String kostet 40 MB
     *                                  Arbeitsspeicher.
     */
    public function __construct(
        public SubjectRef $subject,
        public string $filename,
        public string $mimeType,
        public int $size,
        public mixed $contents,
        public ?Uuid $ownerId = null,
        public ?Uuid $ownerTeamId = null,
    ) {
    }
}
