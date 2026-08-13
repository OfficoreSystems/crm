<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * A polymorphic reference to a record of some module.
 *
 * Two scalars - type and ID - instead of a Doctrine association. That is not
 * only owed to the module boundary: a reference that points sometimes at a
 * contact, sometimes at a company and sometimes at a deal cannot be modelled
 * with a foreign key anyway.
 *
 * The type is a string and not an enum. An enum would have to know every type
 * and would therefore live in the shared kernel - every new module would be a
 * change to it. Avoiding exactly that is the point of the extension point.
 */
final readonly class SubjectRef
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        self::assertValidType($type);

        if ('' === trim($id)) {
            throw new \InvalidArgumentException('SubjectRef.id must not be empty.');
        }
    }

    public static function of(string $type, string $id): self
    {
        return new self($type, $id);
    }

    /**
     * Unique key for arrays - type and ID together.
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
     * Lower case letters, digits, hyphen.
     *
     * Without a rule, "contact", "Contact" and "contacts" would sit next to each
     * other in the column, and no resolver would feel responsible.
     */
    private static function assertValidType(string $type): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9-]{1,39}$/', $type)) {
            throw new \InvalidArgumentException(sprintf(
                'Subject type "%s" is invalid: allowed are 2 to 40 characters from a-z, 0-9 and hyphen, starting with a letter.',
                $type,
            ));
        }
    }
}
