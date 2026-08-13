<?php

declare(strict_types=1);

namespace Crm\Document\Application;

use Crm\Document\Domain\FileSize;
use Crm\SharedKernel\Localization\TranslatableText;

/**
 * Die Datei ueberschreitet das serverseitige Limit.
 *
 * Serverseitig, weil ein `max`-Attribut im Formular niemanden aufhaelt, der
 * die Anfrage selbst baut - und weil PHPs upload_max_filesize eine weisse
 * Seite erzeugt statt einer Meldung.
 */
final class DocumentTooLarge extends \RuntimeException
{
    private function __construct(
        string $message,
        /**
         * Dieselbe Aussage, nur uebersetzbar.
         *
         * Die Ausnahme traegt beides: eine feste englische Meldung fuers Log
         * und einen Schluessel fuer die Oberflaeche. Ein Log in der Sprache
         * des gerade angemeldeten Benutzers waere beim Suchen nach Fehlern
         * nicht hilfreich.
         */
        public readonly TranslatableText $translatable,
    ) {
        parent::__construct($message);
    }

    public static function of(int $size, int $maxBytes): self
    {
        return new self(
            sprintf('File is %s, limit is %s.', FileSize::humanize($size), FileSize::humanize($maxBytes)),
            TranslatableText::of('document.error.too_large', [
                '%size%' => FileSize::humanize($size),
                '%limit%' => FileSize::humanize($maxBytes),
            ]),
        );
    }
}
