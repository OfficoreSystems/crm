<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * Ein polymorpher Verweis auf einen Datensatz irgendeines Moduls.
 *
 * Zwei Skalare - Typ und ID - statt einer Doctrine-Beziehung. Das ist nicht
 * nur der Modulgrenze geschuldet: ein Verweis, der mal auf einen Kontakt, mal
 * auf eine Firma und mal auf eine Verkaufschance zeigt, laesst sich mit einem
 * Fremdschluessel ohnehin nicht abbilden.
 *
 * Der Typ ist eine Zeichenkette und kein Enum. Ein Enum muesste alle Typen
 * kennen und laege damit im Shared Kernel - jedes neue Modul waere eine
 * Aenderung daran. Genau das soll der Extension-Point vermeiden.
 */
final readonly class SubjectRef
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        self::assertValidType($type);

        if ('' === trim($id)) {
            throw new \InvalidArgumentException('SubjectRef.id darf nicht leer sein.');
        }
    }

    public static function of(string $type, string $id): self
    {
        return new self($type, $id);
    }

    /**
     * Eindeutiger Schluessel fuer Arrays - Typ und ID zusammen.
     */
    public function key(): string
    {
        return self::keyFor($this->type, $this->id);
    }

    public static function keyFor(string $type, string $id): string
    {
        return $type.':'.$id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    /**
     * Kleinbuchstaben, Ziffern, Bindestrich.
     *
     * Ohne Festlegung stuenden "contact", "Contact" und "contacts"
     * nebeneinander in der Spalte, und kein Resolver fuehlte sich zustaendig.
     */
    private static function assertValidType(string $type): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9-]{1,39}$/', $type)) {
            throw new \InvalidArgumentException(sprintf(
                'Subjekt-Typ "%s" ist ungueltig: erlaubt sind 2 bis 40 Zeichen aus a-z, 0-9 und Bindestrich, beginnend mit einem Buchstaben.',
                $type,
            ));
        }
    }
}
