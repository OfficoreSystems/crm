<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Document\Application\UploadDocument;
use Crm\Document\Application\UploadDocumentCommand;
use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\User\Domain\Role;
use Crm\User\Domain\User;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Der Negativfall fuer Dateien.
 *
 * Bei einem Datensatz ist eine zu weite Sichtbarkeit aergerlich. Bei einer
 * hochgeladenen Datei ist sie ein Datenleck: der Vertrag, den jemand an einen
 * Kontakt gehaengt hat, ist oft vertraulicher als der Kontakt selbst.
 *
 * Geprueft wird deshalb nicht nur die Liste, sondern der direkte Link auf die
 * Datei - der Weg, den jemand nimmt, der eine ID kennt.
 */
final class DocumentAccessTest extends WebTestCase
{
    use SignsIn;

    private const VERTRIEB = 'Vertrieb';
    private const INNENDIENST = 'Innendienst';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge(['document_documents']);
    }

    protected function tearDown(): void
    {
        // Erst die Dateien, dann die Zeilen: der Schluessel steht in der
        // Zeile. Ohne dieses Aufraeumen waechst var/ mit jedem Testlauf.
        $storage = static::getContainer()->get(DocumentStorageInterface::class);

        foreach (static::getContainer()->get(DocumentRepositoryInterface::class)->findRecent(1000) as $document) {
            $storage->delete($document->storageKey());
        }

        $this->purge(['document_documents']);

        parent::tearDown();
    }

    #[Test]
    public function the_owner_can_download_their_own_file(): void
    {
        // Die Gegenprobe. Ohne sie liesse sich nicht unterscheiden, ob die
        // Rechte greifen oder der Download schlicht kaputt ist.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $document = $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $vera);
        $this->client->request('GET', '/documents/datei/'.$document->id());

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function someone_from_another_team_cannot_download_it(): void
    {
        // DER Test. Wer die ID kennt, kommt trotzdem nicht an die Datei.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: self::INNENDIENST);
        $document = $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $ingo);
        $this->client->request('GET', '/documents/datei/'.$document->id());

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function the_foreign_file_does_not_even_appear_in_the_list(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: self::INNENDIENST);
        $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $ingo);
        $this->client->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Rahmenvertrag.pdf');
    }

    #[Test]
    public function a_teammate_may_download_it(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $vitali = $this->givenUser('vitali@example.test', 'Vitali', teamName: self::VERTRIEB);
        $document = $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $vitali);
        $this->client->request('GET', '/documents/datei/'.$document->id());

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function an_ordinary_user_may_not_delete_a_foreign_file(): void
    {
        // Loeschen ist in der Vorgabe dem Administrator vorbehalten - bei
        // Dateien besonders sinnvoll, weil ein Loeschen hier endgueltig ist.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $vitali = $this->givenUser('vitali@example.test', 'Vitali', teamName: self::VERTRIEB);
        $document = $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $vitali);
        $this->client->request('POST', '/documents/datei/'.$document->id());

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function the_administrator_reaches_every_file(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $chefin = $this->givenUser('chefin@example.test', 'Chefin', [Role::ADMIN], self::INNENDIENST);
        $document = $this->givenDocumentOf($vera, 'Rahmenvertrag.pdf');

        $this->signIn($this->client, $chefin);
        $this->client->request('GET', '/documents/datei/'.$document->id());

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function the_file_is_never_rendered_in_the_browser(): void
    {
        // Eine hochgeladene HTML- oder SVG-Datei wuerde sonst im Ursprung der
        // Anwendung ausgefuehrt - und haette damit Zugriff auf die Sitzung des
        // Betrachters.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: self::VERTRIEB);
        $document = $this->givenDocumentOf($vera, 'boese.html', 'text/html', '<script>alert(1)</script>');

        $this->signIn($this->client, $vera);
        $this->client->request('GET', '/documents/datei/'.$document->id());

        $headers = $this->client->getResponse()->headers;

        self::assertStringStartsWith('attachment;', (string) $headers->get('Content-Disposition'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
    }

    private function givenDocumentOf(
        User $owner,
        string $filename,
        string $mimeType = 'application/pdf',
        string $contents = 'Inhalt',
    ): Document {
        // Ueber den Use-Case, nicht direkt in die Tabelle: so wird
        // mitgeprueft, dass auch wirklich eine Datei im Speicher landet -
        // sonst wuerde der Download-Test aus dem falschen Grund fehlschlagen.
        return (static::getContainer()->get(UploadDocument::class))(new UploadDocumentCommand(
            subject: new SubjectRef('contact', 'kontakt-1'),
            filename: $filename,
            mimeType: $mimeType,
            size: \strlen($contents),
            contents: $contents,
            ownerId: $owner->id(),
            ownerTeamId: $owner->teamId(),
        ));
    }

}
