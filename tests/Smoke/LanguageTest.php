<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\SharedKernel\Localization\Locale;
use Crm\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Die Sprache am Konto, durch die ganze Kette geprueft.
 *
 * Die Kette ist laenger, als sie aussieht: Konto → SecurityUser →
 * ActorInterface → Listener → Request *und* LocaleSwitcher → Uebersetzer →
 * Template. Faellt ein Glied aus, ist die Anwendung halb uebersetzt - und
 * zwar je nach Stelle unterschiedlich.
 */
final class LanguageTest extends WebTestCase
{
    use SignsIn;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    #[Test]
    public function without_a_choice_the_application_speaks_english(): void
    {
        $this->signIn($this->client, $this->givenUser('vera@example.test', 'Vera'));

        $this->client->request('GET', '/dashboard');

        self::assertSelectorTextContains('.sidebar__account-logout', 'Sign out');
        self::assertSame('en', $this->client->getRequest()->getLocale());
    }

    #[Test]
    public function the_html_tag_names_the_language(): void
    {
        // Fuer Screenreader und die Silbentrennung des Browsers. Ein festes
        // lang="de" auf englischem Text ist der Fehler, den niemand sieht und
        // jeder Screenreader hoert.
        $this->signIn($this->client, $this->givenUser('vera@example.test', 'Vera'));

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertSame('en', $crawler->filter('html')->attr('lang'));
    }

    #[Test]
    public function a_users_choice_changes_the_interface(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera');
        $vera->switchTo(Locale::GERMAN);
        static::getContainer()->get(UserRepositoryInterface::class)->save($vera);

        $this->signIn($this->client, $vera);
        $crawler = $this->client->request('GET', '/dashboard');

        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextContains('.sidebar__account-logout', 'Abmelden');
    }

    #[Test]
    public function the_switcher_stores_the_choice_on_the_account(): void
    {
        // Nicht in der Sitzung: die Wahl soll auch nach dem naechsten
        // Anmelden noch gelten - und spaeter fuer Mails, bei denen es keine
        // Sitzung gibt.
        $vera = $this->givenUser('vera@example.test', 'Vera');
        $this->signIn($this->client, $vera);

        $crawler = $this->client->request('GET', '/dashboard');
        $form = $crawler->filter('.sidebar__language')->form();
        $form['locale'] = 'de';
        $this->client->submit($form);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $stored = static::getContainer()->get(UserRepositoryInterface::class)->find($vera->id());

        self::assertSame(Locale::GERMAN, $stored?->locale());
    }

    #[Test]
    public function switching_returns_to_the_page_it_was_used_on(): void
    {
        // Der Umschalter steht in der Navigation und damit auf jeder Seite.
        // Ihn immer zur Startseite zurueckwerfen zu lassen waere der
        // schnellste Weg, ihn unbenutzbar zu machen.
        $this->signIn($this->client, $this->givenUser('vera@example.test', 'Vera'));

        $crawler = $this->client->request('GET', '/contacts');
        $form = $crawler->filter('.sidebar__language')->form();
        $form['locale'] = 'de';
        $this->client->submit($form);

        self::assertResponseRedirects('/contacts');
    }

    #[Test]
    public function a_foreign_host_in_the_return_field_is_ignored(): void
    {
        // Sonst waere der Umschalter eine offene Weiterleitung: ein Link mit
        // praepariertem _back schickt den Angemeldeten auf eine fremde Seite,
        // die wie diese hier aussieht.
        $vera = $this->givenUser('vera@example.test', 'Vera');
        $this->signIn($this->client, $vera);

        $crawler = $this->client->request('GET', '/dashboard');
        $form = $crawler->filter('.sidebar__language')->form();
        $form['_back'] = '//example.invalid/phishing';
        $this->client->submit($form);

        self::assertResponseRedirects('/');
    }

    #[Test]
    public function a_request_without_a_valid_token_changes_nothing(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera');
        $this->signIn($this->client, $vera);

        $this->client->request('POST', '/settings/language', [
            'locale' => 'de',
            '_token' => 'falsch',
        ]);

        static::getContainer()->get('doctrine')->getManager()->clear();

        self::assertNull(
            static::getContainer()->get(UserRepositoryInterface::class)->find($vera->id())?->locale(),
        );
    }

    #[Test]
    public function an_unknown_language_is_ignored(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera');
        $this->signIn($this->client, $vera);

        $crawler = $this->client->request('GET', '/dashboard');
        $form = $crawler->filter('.sidebar__language')->form();
        // An der Auswahlliste vorbei - so, wie es eine selbstgebaute Anfrage
        // taete.
        $this->client->request('POST', '/settings/language', [
            'locale' => 'kl',
            '_token' => $form['_token']->getValue(),
        ]);

        static::getContainer()->get('doctrine')->getManager()->clear();

        self::assertNull(
            static::getContainer()->get(UserRepositoryInterface::class)->find($vera->id())?->locale(),
        );
    }

    #[Test]
    public function the_login_page_has_no_switcher(): void
    {
        // Dort gibt es kein Konto, an dem sich etwas speichern liesse. Ein
        // Umschalter, der nichts umschaltet, waere schlimmer als keiner.
        $this->client->request('GET', '/login');

        self::assertSelectorNotExists('.sidebar__language');
    }
}
