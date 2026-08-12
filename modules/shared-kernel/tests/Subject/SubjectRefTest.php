<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Subject;

use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubjectRefTest extends TestCase
{
    #[Test]
    public function it_carries_type_and_id(): void
    {
        $ref = SubjectRef::of('contact', 'abc');

        self::assertSame('contact', $ref->type);
        self::assertSame('abc', $ref->id);
    }

    #[Test]
    public function the_key_combines_both_parts(): void
    {
        // Zwei Datensaetze verschiedener Module koennen dieselbe ID haben -
        // erst Typ und ID zusammen sind eindeutig.
        self::assertSame('contact:abc', SubjectRef::of('contact', 'abc')->key());
        self::assertNotSame(
            SubjectRef::of('contact', 'abc')->key(),
            SubjectRef::of('company', 'abc')->key(),
        );
    }

    #[Test]
    public function refs_compare_by_value(): void
    {
        self::assertTrue(SubjectRef::of('contact', 'abc')->equals(SubjectRef::of('contact', 'abc')));
        self::assertFalse(SubjectRef::of('contact', 'abc')->equals(SubjectRef::of('company', 'abc')));
        self::assertFalse(SubjectRef::of('contact', 'abc')->equals(SubjectRef::of('contact', 'xyz')));
    }

    #[Test]
    #[DataProvider('invalidTypes')]
    public function it_rejects_malformed_types(string $type): void
    {
        // Ohne Festlegung stuenden "contact", "Contact" und "contacts"
        // nebeneinander in der Spalte, und kein Resolver fuehlte sich
        // zustaendig.
        $this->expectException(\InvalidArgumentException::class);

        SubjectRef::of($type, 'abc');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTypes(): iterable
    {
        yield 'leer' => [''];
        yield 'Grossbuchstaben' => ['Contact'];
        yield 'mit Unterstrich' => ['contact_person'];
        yield 'mit Doppelpunkt' => ['contact:person'];
        yield 'beginnt mit Ziffer' => ['1contact'];
        yield 'ein Zeichen' => ['c'];
        yield 'mit Leerzeichen' => ['contact person'];
    }

    #[Test]
    public function it_rejects_a_blank_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SubjectRef::of('contact', '   ');
    }

    #[Test]
    public function a_resolved_subject_knows_whether_it_can_be_linked(): void
    {
        $linkable = new ResolvedSubject('contact', 'abc', 'Anna Berger', 'contact_index');
        $plain = new ResolvedSubject('contact', 'abc', 'Anna Berger');

        self::assertTrue($linkable->isLinkable());
        self::assertFalse($plain->isLinkable());
        self::assertSame('contact:abc', $plain->key());
    }
}
