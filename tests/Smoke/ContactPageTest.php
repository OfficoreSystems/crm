<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Prueft die Verdrahtung, nicht die Fachlichkeit: Routing-Glob, Twig-Namespace
 * des Moduls, Live-Component und Menue-Registry muessen zusammen eine Seite
 * ergeben. Genau die Kette, die kaputtgeht, wenn ein Modul falsch andockt.
 */
final class ContactPageTest extends WebTestCase
{
    use SignsIn;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge();

        // Seit der Firewall fuehrt jeder Pfad ohne Anmeldung auf /login. Die
        // Seite selbst hat sich nicht geaendert - man kommt nur nicht mehr
        // ohne Konto hin.
        $this->signIn($this->client, $this->givenUser('smoke@example.test', 'Smoke Benutzer'));
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    #[Test]
    public function the_contact_page_renders(): void
    {
        $this->client->request('GET', '/contacts');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Contacts');
    }

    #[Test]
    public function it_renders_the_live_component_with_a_search_field(): void
    {
        $crawler = $this->client->request('GET', '/contacts');

        $search = $crawler->filter('input[type="search"]');

        self::assertCount(1, $search, 'Das Suchfeld der Live-Component fehlt.');
        self::assertSame('debounce(200)|query', $search->attr('data-model'));

        // Das Attribut, an dem man erkennt, dass die Component wirklich als
        // Live-Component gerendert wurde und nicht nur als statisches Twig.
        self::assertCount(1, $crawler->filter('[data-live-name-value="ContactList"]'));
    }

    #[Test]
    public function the_module_registers_itself_in_the_navigation(): void
    {
        // Der Core kennt das Modul nicht - der Eintrag kann nur ueber
        // MenuProviderInterface + Tag hierher gekommen sein.
        $crawler = $this->client->request('GET', '/contacts');

        self::assertSelectorTextContains('.sidebar__nav', 'Contacts');
        self::assertCount(1, $crawler->filter('.sidebar__nav a[aria-current="page"]'));
    }
}
