<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\User\Application\CreateUser;
use Crm\User\Application\CreateUserCommand;
use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Crm\SharedKernel\Menu\MenuRegistry;
use Crm\User\Infrastructure\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Prueft die Kette, die kein Unit-Test abdeckt: Firewall aus dem Core,
 * Provider-Alias aus dem Shared Kernel, Implementierung aus dem user-Modul,
 * Anmeldeformular aus dessen Twig-Namespace.
 */
final class AuthenticationTest extends WebTestCase
{
    private const PASSWORD = 'ein-hinreichend-langes-passwort';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // Ohne das scheitert jeder Testfall nach dem ersten mit "the kernel
        // should only be booted once" - die vorherige Instanz laeuft sonst
        // noch, wenn createClient() eine neue anlegen will.
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    /**
     * Aufraeumen per DELETE, nicht per Transaktion.
     *
     * Bei WebTestCase startet jeder Request den Kernel neu und damit eine
     * eigene Datenbankverbindung. Eine im Test geoeffnete Transaktion waere
     * fuer den Request unsichtbar - der angelegte Benutzer existierte fuer
     * die Anmeldung schlicht nicht, und in tearDown gaebe es die Transaktion
     * nicht mehr ("There is no active transaction").
     */
    private function purge(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        $connection->executeStatement('DELETE FROM user_users');
        $connection->executeStatement('DELETE FROM user_teams');
    }

    #[Test]
    public function the_login_page_is_reachable_without_authentication(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="_username"]'));
        self::assertCount(1, $crawler->filter('input[name="_password"]'));
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'), 'Ohne CSRF-Feld wird jede Anmeldung abgelehnt.');
    }

    #[Test]
    public function the_login_page_has_no_navigation(): void
    {
        // Sie ersetzt den shell-Block: eine Navigation vor der Anmeldung
        // wuerde nur ins Leere fuehren.
        $crawler = $this->client->request('GET', '/login');

        self::assertCount(0, $crawler->filter('.sidebar'));
    }

    #[Test]
    public function an_admin_only_page_redirects_anonymous_visitors_to_the_login(): void
    {
        $this->client->request('GET', '/users');

        self::assertResponseRedirects('http://localhost/login');
    }

    #[Test]
    public function correct_credentials_start_a_session(): void
    {
        $this->givenUser([Role::ADMIN]);

        // Zwei Weiterleitungen: erst auf das Ziel "/" der Firewall, von dort
        // ueber die MenuRegistry auf das erste Modul.
        $this->client->followRedirects();
        $this->submitLogin('admin@example.test', self::PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sidebar__account-name', 'Admin Benutzer');
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $this->givenUser();

        $this->submitLogin('admin@example.test', 'falsches-passwort');
        $crawler = $this->client->followRedirect();

        self::assertCount(1, $crawler->filter('.auth__error'));
    }

    #[Test]
    public function an_unknown_address_is_rejected(): void
    {
        $this->submitLogin('niemand@example.test', self::PASSWORD);
        $crawler = $this->client->followRedirect();

        self::assertCount(1, $crawler->filter('.auth__error'));
    }

    #[Test]
    public function a_deactivated_user_cannot_log_in(): void
    {
        $user = $this->givenUser();
        $user->deactivate();
        static::getContainer()->get(UserRepositoryInterface::class)->save($user);

        $this->submitLogin('admin@example.test', self::PASSWORD);
        $crawler = $this->client->followRedirect();

        self::assertCount(1, $crawler->filter('.auth__error'));
    }

    #[Test]
    public function a_plain_user_is_denied_access_to_the_user_administration(): void
    {
        // Der Negativfall - der Test, der bei Regressionen tatsaechlich
        // anschlaegt. "Admin darf" allein wuerde auch gruen bleiben, wenn
        // die Pruefung ganz wegfiele.
        $this->client->loginUser($this->securityUserFor($this->givenUser()));

        $this->client->request('GET', '/users');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function an_admin_reaches_the_user_administration(): void
    {
        $this->client->loginUser($this->securityUserFor($this->givenUser([Role::ADMIN])));

        $this->client->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Users');
    }

    #[Test]
    public function the_home_page_redirects_to_the_first_menu_entry(): void
    {
        // Bewusst kein fester Pfad: welches Modul vorne steht, entscheidet
        // dessen Priority. Ein hart erwarteter Pfad wuerde diesen Test jedes
        // Mal brechen, wenn ein Modul mit hoeherer Priority dazukommt - und
        // damit genau das pruefen, was der Core nicht wissen soll.
        $menu = static::getContainer()->get(MenuRegistry::class)->items();
        self::assertNotEmpty($menu, 'Ohne Menueeintrag hat die Startseite kein Ziel.');

        $expected = static::getContainer()->get(RouterInterface::class)->generate($menu[0]->route);

        // Angemeldet, sonst faengt die Firewall den Aufruf vorher ab und der
        // Test wuerde nur noch die Weiterleitung auf /login pruefen.
        $this->client->loginUser($this->securityUserFor($this->givenUser()));

        $this->client->request('GET', '/');

        self::assertResponseRedirects($expected);
    }

    #[Test]
    public function an_authenticated_user_sees_their_name_and_a_logout_link(): void
    {
        $this->client->loginUser($this->securityUserFor($this->givenUser()));

        $crawler = $this->client->request('GET', '/contacts');

        self::assertSelectorTextContains('.sidebar__account-name', 'Admin Benutzer');
        self::assertCount(1, $crawler->filter('a[href="/logout"]'));
    }

    /**
     * @param list<Role> $roles
     */
    private function givenUser(array $roles = []): User
    {
        return (static::getContainer()->get(CreateUser::class))(new CreateUserCommand(
            'admin@example.test',
            'Admin Benutzer',
            self::PASSWORD,
            $roles,
        ));
    }

    private function securityUserFor(User $user): SecurityUser
    {
        return SecurityUser::fromDomain($user);
    }

    private function submitLogin(string $username, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();

        $this->client->submit($form, [
            '_username' => $username,
            '_password' => $password,
        ]);
    }
}
