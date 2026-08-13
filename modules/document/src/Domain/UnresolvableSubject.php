<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

use Crm\SharedKernel\Localization\TranslatableText;

/**
 * Ein Dokument soll an einem Typ haengen, den niemand aufloest.
 *
 * Geprueft wird der *Typ*, nicht die ID - wie bei Aktivitaeten. Ob der
 * Datensatz noch existiert, ist eine Frage von morgen, und ein geloeschter
 * Kontakt soll seine Dokumente nicht mitreissen.
 */
final class UnresolvableSubject extends \InvalidArgumentException
{
    private function __construct(
        string $message,
        /**
         * Dieselbe Aussage fuer die Oberflaeche. Die Meldung oben bleibt
         * englisch - ein Log in der Sprache des gerade angemeldeten Benutzers
         * waere beim Suchen nach Fehlern nicht hilfreich.
         */
        public readonly TranslatableText $translatable,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $known
     */
    public static function ofType(string $type, array $known): self
    {
        $knownList = [] === $known ? 'none' : implode(', ', $known);

        return new self(
            sprintf('No module resolves the type "%s". Known types: %s.', $type, $knownList),
            TranslatableText::of('document.error.unresolvable_subject', [
                '%type%' => $type,
                '%known%' => $knownList,
            ]),
        );
    }
}
