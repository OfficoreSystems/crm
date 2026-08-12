<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\Action;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\SharedKernel\Security\CrmVoter;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Security\PermissionMatrix;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
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
    public function it_abstains_on_attributes_that_are_not_actions(): void
    {
        // Sonst wuerde er anderen Votern ins Handwerk pfuschen - etwa dem
        // Rollen-Voter bei ROLE_ADMIN.
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']), 'deal', ['ROLE_ADMIN']),
        );
    }

    // --- Modulweite Pruefung ohne Datensatz, fuer Listenseiten ---

    #[Test]
    public function a_module_name_alone_answers_whether_the_page_may_be_opened(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), 'deal', [Action::VIEW->value]),
            'Ein eingeschraenktes Recht ist ein Recht - welche Zeilen, entscheidet sich spaeter.',
        );
    }

    #[Test]
    public function a_module_without_any_right_stays_closed(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), 'user', [Action::VIEW->value]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_ADMIN']), 'user', [Action::VIEW->value]),
        );
    }

    #[Test]
    public function creating_needs_no_record(): void
    {
        $voter = $this->voter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(self::ANNA, self::VERTRIEB, ['ROLE_USER']), 'deal', [Action::CREATE->value]),
        );
    }

    // --- Hilfen ---

    private function vote(TokenInterface $token, Action $action, object $subject): int
    {
        return $this->voter()->vote($token, $subject, [$action->value]);
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
