<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Deal\Application\CreateDeal;
use Crm\Deal\Application\CreateDealCommand;
use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Der Negativfall, zusammengesetzt statt simuliert.
 *
 * Die Unit-Tests zum Voter und zur Rechtematrix pruefen die Regeln. Sie
 * koennten alle gruen bleiben, waehrend die Anwendung trotzdem alles zeigt -
 * naemlich dann, wenn der Doctrine-Filter gar nicht eingeschaltet wird. Genau
 * das ist bei der Entwicklung passiert: der Listener war keine
 * Service-Definition, und die Seiten sahen vollkommen normal aus.
 *
 * Deshalb hier: echte Requests, echte Datenbank, zwei Benutzer aus
 * verschiedenen Teams - und die Frage, wer denselben Datensatz zu sehen
 * bekommt.
 */
final class RecordVisibilityTest extends WebTestCase
{
    use SignsIn;

    private const VERTRIEB = 'Vertrieb';
    private const INNENDIENST = 'Innendienst';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge(['deal_deals']);
    }

    protected function tearDown(): void
    {
        $this->purge(['deal_deals']);

        parent::tearDown();
    }

    #[Test]
    public function the_owner_sees_their_own_deal_in_the_list(): void
    {
        // Die Gegenprobe zu allem Folgenden. Ohne sie liesse sich nicht
        // unterscheiden, ob der Filter richtig filtert oder einfach alles
        // wegnimmt.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $vera);
        $this->client->request('GET', '/deals');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Rahmenvertrag Seefracht');
    }

    #[Test]
    public function someone_from_another_team_does_not_see_the_deal_at_all(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: self::INNENDIENST);
        $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $ingo);
        $this->client->request('GET', '/deals');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Rahmenvertrag Seefracht');
    }

    #[Test]
    public function the_direct_link_to_a_foreign_deal_leads_nowhere(): void
    {
        // DER Test. Eine Liste zu filtern ist die halbe Miete - wer die ID
        // kennt, darf trotzdem nicht hineinkommen.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: self::INNENDIENST);
        $deal = $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $ingo);
        $this->client->request('GET', '/deals/'.$deal->id());

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function a_teammate_may_open_the_deal(): void
    {
        // Die Vorgabe fuer Verkaufschancen ist teamweite Sicht, nicht private.
        // Waere sie enger, wuerde dieser Test rot - und man merkte es, statt
        // sich ueber leere Listen zu wundern.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $vitali = $this->givenUser('vitali@example.test', 'Vitali', teamName: self::VERTRIEB);
        $deal = $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $vitali);
        $this->client->request('GET', '/deals/'.$deal->id());

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function the_administrator_sees_every_deal(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $chefin = $this->givenUser('chefin@example.test', 'Chefin', [Role::ADMIN], self::INNENDIENST);
        $deal = $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $chefin);
        $this->client->request('GET', '/deals/'.$deal->id());

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function the_dashboard_figures_shrink_with_the_visible_records(): void
    {
        // Kennzahlen sind der leiseste Weg, an einem Filter vorbei Daten zu
        // verraten: die Liste ist leer, die Summe stimmt trotzdem.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: self::INNENDIENST);
        $this->givenDealOf($vera, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $vera);
        $this->client->request('GET', '/dashboard');
        self::assertSelectorTextContains('.metrics', '84000.00 EUR');

        $this->signIn($this->client, $ingo);
        $this->client->request('GET', '/dashboard');
        self::assertSelectorTextContains('.metrics', '0.00 EUR');
        self::assertSelectorTextNotContains('.metrics', '84000.00 EUR');
    }

    #[Test]
    public function a_user_without_a_team_does_not_inherit_the_records_of_other_teamless_users(): void
    {
        // Sonst waere "kein Team" faktisch ein gemeinsames Team - ein Loch,
        // das erst auffaellt, wenn jemand ohne Zuordnung angelegt wird.
        $solo = $this->givenUser('solo@example.test', 'Solo');
        $duo = $this->givenUser('duo@example.test', 'Duo');
        $deal = $this->givenDealOf($solo, 'Rahmenvertrag Seefracht');

        $this->signIn($this->client, $duo);
        $this->client->request('GET', '/deals/'.$deal->id());

        self::assertResponseStatusCodeSame(404);
    }

    private function givenDealOf(User $owner, string $title): Deal
    {
        return (static::getContainer()->get(CreateDeal::class))(new CreateDealCommand(
            title: $title,
            value: Money::fromDecimal('84000.00'),
            stage: Stage::NEGOTIATION,
            ownerId: $owner->id(),
            ownerTeamId: $owner->teamId(),
        ));
    }
}
