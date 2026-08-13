<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Security;

use Crm\SharedKernel\Localization\Locale;
use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\Action;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\SharedKernel\Security\CrmVoter;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Security\PermissionMatrix;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Der Voter kennt kein Modul. Diese Tests pruefen vor allem den Negativfall -
 * "Admin darf" bliebe auch dann gruen, wenn die Pruefung ganz wegfiele.
 */
final class CrmVoterTest extends TestCase
{
    private const ANNA = 'user-anna';
    private const BOGDAN = 'user-bogdan';
    private const VERTRIEB = 'team-vertrieb';
    private const INNENDIENST = 'team-innendienst';

    #[Test]
    public function an_anonymous_visitor_gets_nothing(): void
    {
        $vote = $this->vote($this->anonymousToken(), Action::VIEW, $this->deal(self::ANNA, self::VERTRIEB));

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    #[Test]
    public function a_user_type_that_is_not_an_actor_gets_nothing(): void
    {
        // Etwa ein In-Memory-Benutzer aus einer Fremdkonfiguration.
        $token = new UsernamePasswordToken(new InMemoryUser('fremd', 'x', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($token, Action::VIEW, $this->deal(self::ANNA, self::VERTRIEB)),
        );
    }

    #[Test]
    public function an_owner_may_edit_their_own_record(): void
    {
        $vote = $this->vote(
            $this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']),
            Action::EDIT,
            $this->deal(self::ANNA, self::VERTRIEB),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    #[Test]
    public function a_teammate_may_not_edit_a_foreign_record(): void
    {
        // DER Negativfall: Bogdan sieht Annas Deal, darf ihn aber nicht
        // umschreiben. Genau das schlaegt bei Regressionen an.
        $vote = $this->vote(
            $this->tokenFor(self::BOGDAN, self::VERTRIEB, ['ROLE_USER']),
            Action::EDIT,
            $this->deal(self::ANNA, self::VERTRIEB),
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    #[Test]
    public function a_teammate_may_view_a_foreign_record(): void
    {
        $vote = $this->vote(
            $this->tokenFor(self::BOGDAN, self::VERTRIEB, ['ROLE_USER']),
            Action::VIEW,
            $this->deal(self::ANNA, self::VERTRIEB),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    #[Test]
    public function someone_from_another_team_may_not_even_view(): void
    {
        $vote = $this->vote(
            $this->tokenFor(self::BOGDAN, self::INNENDIENST, ['ROLE_USER']),
            Action::VIEW,
            $this->deal(self::ANNA, self::VERTRIEB),
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    #[Test]
    public function two_users_without_a_team_are_not_teammates(): void
    {
        // Sonst saehe jeder teamlose Benutzer die Daten aller anderen
        // teamlosen Benutzer - ein Loch, das man erst spaet bemerkt.
        $vote = $this->vote(
            $this->tokenFor(self::BOGDAN, null, ['ROLE_USER']),
            Action::VIEW,
            $this->deal(self::ANNA, null),
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    #[Test]
    public function an_admin_may_edit_a_foreign_record(): void
    {
        $vote = $this->vote(
            $this->tokenFor(self::BOGDAN, self::INNENDIENST, ['ROLE_ADMIN', 'ROLE_USER']),
            Action::EDIT,
            $this->deal(self::ANNA, self::VERTRIEB),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    #[Test]
    public function nobody_may_delete_by_default_except_the_admin(): void
    {
        $deal = $this->deal(self::ANNA, self::VERTRIEB);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), Action::DELETE, $deal),
            'Auch der Besitzer darf nicht loeschen.',
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']), Action::DELETE, $deal),
        );
    }

    #[Test]
    public function a_record_nobody_claims_is_only_reachable_with_full_rights(): void
    {
        // Die sichere Vorgabe: ohne Besitzangabe zaehlt nur ALL.
        $orphan = new \stdClass();
        $registry = new OwnershipRegistry([]);
        $voter = new CrmVoter(PermissionMatrix::default(), $registry);

        // Kein Anbieter fuehlt sich zustaendig, also stimmt der Voter gar
        // nicht erst ab.
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']), $orphan, [Action::VIEW->value]),
        );
    }

    #[Test]
    public function it_abstains_on_attributes_it_does_not_understand(): void
    {
        // Sonst wuerde er anderen Votern ins Handwerk pfuschen - etwa dem
        // Rollen-Voter bei ROLE_ADMIN.
        $voter = $this->voter();
        $token = $this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['deal']), 'ohne Aktion');
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['deal.exportieren']), 'unbekannte Aktion');
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['Deal.view']), 'Grossbuchstaben');
    }

    // --- Modulweite Pruefung ohne Datensatz, fuer Listenseiten ---

    #[Test]
    public function the_attribute_alone_answers_whether_the_page_may_be_opened(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), null, ['deal.view']),
            'Ein eingeschraenktes Recht ist ein Recht - welche Zeilen, entscheidet der Filter.',
        );
    }

    #[Test]
    public function a_module_without_any_right_stays_closed(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), null, ['user.view']),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']), null, ['user.view']),
        );
    }

    #[Test]
    public function creating_needs_no_record(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), null, ['deal.create']),
        );
    }

    #[Test]
    public function the_module_comes_from_the_attribute_not_from_the_record(): void
    {
        // Ein Deal-Objekt mit einem contact-Attribut wird nach den
        // contact-Regeln beurteilt - dort gilt ALL, also darf auch ein
        // Fremdteam.
        $vote = $this->voter()->vote(
            $this->tokenFor(self::BOGDAN, self::INNENDIENST, ['ROLE_USER']),
            $this->deal(self::ANNA, self::VERTRIEB),
            ['contact.view'],
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    // --- Hilfen ---

    private function vote(TokenInterface $token, Action $action, object $subject): int
    {
        return $this->voter()->vote($token, $subject, ['deal.'.$action->value]);
    }

    private function voter(): CrmVoter
    {
        return new CrmVoter(
            PermissionMatrix::default(),
            new OwnershipRegistry([new FakeDealOwnership()]),
        );
    }

    private function deal(?string $ownerId, ?string $teamId): FakeDeal
    {
        return new FakeDeal($ownerId, $teamId);
    }

    private function anonymousToken(): TokenInterface
    {
        return new UsernamePasswordToken(new InMemoryUser('anon', null), 'main');
    }

    /**
     * @param list<string> $roles
     */
    private function tokenFor(string $id, ?string $teamId, array $roles): TokenInterface
    {
        return new UsernamePasswordToken(new FakeActor($id, $teamId, $roles), 'main', $roles);
    }
}

final class FakeDeal
{
    public function __construct(
        public readonly ?string $ownerId,
        public readonly ?string $teamId,
    ) {
    }
}

final class FakeDealOwnership implements RecordOwnershipInterface
{
    public function module(): string
    {
        return 'deal';
    }

    public function supports(object $record): bool
    {
        return $record instanceof FakeDeal;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof FakeDeal);

        return new RecordOwnership($record->ownerId, $record->teamId);
    }

    public function restrictedColumns(): ?RestrictedColumns
    {
        // Der Voter braucht keine Spalten - er arbeitet auf geladenen
        // Objekten. Null ist hier also die richtige Antwort, nicht ein
        // Platzhalter.
        return null;
    }
}

final class FakeActor implements ActorInterface, \Symfony\Component\Security\Core\User\UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $teamId,
        private readonly array $roles,
    ) {
    }

    public function actorId(): string
    {
        return $this->id;
    }

    public function actorTeamId(): ?string
    {
        return $this->teamId;
    }

    public function actorRoles(): array
    {
        return $this->roles;
    }

    public function actorLocale(): ?Locale
    {
        // Der Voter interessiert sich nicht fuer die Sprache. Null heisst
        // "nie gewaehlt" und ist hier die ehrlichste Antwort.
        return null;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function eraseCredentials(): void
    {
    }
}
