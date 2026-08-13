<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Infrastructure\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Infrastructure\Security\RecordVisibilityFilter;
use Crm\SharedKernel\Security\RecordRestriction;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der Filter erzeugt eine WHERE-Bedingung. Diese Tests lesen sie.
 *
 * Ueber die Oberflaeche laesst sich nur beobachten, dass weniger Zeilen
 * ankommen - nicht, warum. Bei einer Sicherheitsgrenze ist das zu wenig: eine
 * Bedingung, die zufaellig nichts findet, sieht genauso aus wie eine, die
 * richtig einschraenkt.
 */
final class RecordVisibilityFilterTest extends TestCase
{
    #[Test]
    public function an_entity_nobody_declared_is_left_alone(): void
    {
        // Sonst muesste jede Entity im System Besitzspalten haben.
        $filter = $this->filter(['actor_id' => 'anna', 'scope_deal' => 'own']);

        self::assertSame('', $this->constraintFor($filter, UnrestrictedFixture::class));
    }

    #[Test]
    public function without_any_parameters_it_stays_silent(): void
    {
        // Der Zustand in Konsolenbefehlen und Migrationen: kein angemeldeter
        // Benutzer. Wuerde der Filter hier einschraenken, saehe jeder
        // Konsolenbefehl eine leere Datenbank.
        self::assertSame('', $this->constraintFor($this->filter([]), RestrictedFixture::class));
    }

    #[Test]
    public function a_known_actor_without_a_scope_is_not_restricted(): void
    {
        // Ein Modul ohne Eintrag in der Matrix wird nicht heimlich
        // eingeschraenkt - es wird gar nicht erst erreichbar sein, dafuer
        // sorgt der Voter.
        $filter = $this->filter(['actor_id' => 'anna']);

        self::assertSame('', $this->constraintFor($filter, RestrictedFixture::class));
    }

    #[Test]
    public function the_all_scope_produces_no_condition(): void
    {
        $filter = $this->filter(['actor_id' => 'anna', 'scope_deal' => AccessScope::ALL->value]);

        self::assertSame('', $this->constraintFor($filter, RestrictedFixture::class));
    }

    #[Test]
    public function the_own_scope_compares_the_owner_column(): void
    {
        $filter = $this->filter(['actor_id' => 'anna', 'scope_deal' => AccessScope::OWN->value]);

        self::assertSame("d.owner_id = 'anna'", $this->constraintFor($filter, RestrictedFixture::class));
    }

    #[Test]
    public function the_team_scope_also_admits_the_team_column(): void
    {
        $filter = $this->filter([
            'actor_id' => 'anna',
            'actor_team_id' => 'vertrieb',
            'scope_deal' => AccessScope::TEAM->value,
        ]);

        self::assertSame(
            "(d.owner_id = 'anna' OR d.owner_team_id = 'vertrieb')",
            $this->constraintFor($filter, RestrictedFixture::class),
        );
    }

    #[Test]
    public function a_teammate_of_nobody_falls_back_to_their_own_records(): void
    {
        // Der wichtigste Fall dieser Datei. Ohne diesen Rueckfall lautete die
        // Bedingung "owner = anna OR team IS NULL" - und jeder teamlose
        // Benutzer saehe die Datensaetze aller anderen teamlosen Benutzer.
        $filter = $this->filter([
            'actor_id' => 'anna',
            'actor_team_id' => '',
            'scope_deal' => AccessScope::TEAM->value,
        ]);

        self::assertSame("d.owner_id = 'anna'", $this->constraintFor($filter, RestrictedFixture::class));
    }

    #[Test]
    public function an_unknown_scope_value_restricts_instead_of_opening_up(): void
    {
        // Ein Tippfehler im Parameter darf nicht dazu fuehren, dass alles
        // sichtbar wird. tryFrom() liefert null, und null bedeutet hier
        // "keine Aussage" - der Voter laesst die Seite dann gar nicht erst zu.
        $filter = $this->filter(['actor_id' => 'anna', 'scope_deal' => 'alles']);

        self::assertSame('', $this->constraintFor($filter, RestrictedFixture::class));
    }

    #[Test]
    public function a_module_without_its_own_scope_uses_the_default(): void
    {
        $filter = $this->filter(['actor_id' => 'anna', 'scope_default' => AccessScope::OWN->value]);

        self::assertSame("d.owner_id = 'anna'", $this->constraintFor($filter, RestrictedFixture::class));
    }

    // --- Hilfen ---

    /**
     * @param array<string, string> $parameters
     */
    private function filter(array $parameters): RecordVisibilityFilter
    {
        // Stubs, keine Mocks: geprueft wird die erzeugte Bedingung, nicht wer
        // wie oft aufgerufen wurde. Das Quoting ist absichtlich naiv - hier
        // interessiert nur, an welchen Stellen der Filter quotet und an
        // welchen nicht.
        $connection = $this->createStub(Connection::class);
        $connection->method('quote')->willReturnCallback(
            static fn (string $value): string => "'".$value."'",
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $filter = new RecordVisibilityFilter($entityManager);

        // Das, was zur Laufzeit die OwnershipRegistry liefert.
        $filter->useRestrictions([
            RestrictedFixture::class => new RecordRestriction('deal', 'owner_id', 'owner_team_id'),
        ]);

        foreach ($parameters as $name => $value) {
            $filter->setParameter($name, $value);
        }

        return $filter;
    }

    /**
     * @param class-string $entity
     */
    private function constraintFor(RecordVisibilityFilter $filter, string $entity): string
    {
        $metadata = new ClassMetadata($entity);
        $metadata->initializeReflection(new RuntimeReflectionService());

        return $filter->addFilterConstraint($metadata, 'd');
    }
}

final class RestrictedFixture
{
}

final class UnrestrictedFixture
{
}
