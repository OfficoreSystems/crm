<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\User\Domain\Role;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Der Upload-Weg, wie ihn ein Browser geht.
 *
 * Die Use-Case-Tests pruefen die Regeln; hier geht es um das, was nur im
 * Zusammenspiel schiefgehen kann: eine abgebrochene Uebertragung, ein
 * fehlendes CSRF-Token, ein Dateiname aus einer feindlichen Anfrage.
 */
final class DocumentUploadTest extends WebTestCase
{
    use SignsIn;

    private const SUBJECT = '/documents/an/contact/kontakt-1';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge(['document_documents']);
    }

    protected function tearDown(): void
    {
        $storage = static::getContainer()->get(DocumentStorageInterface::class);

        foreach (static::getContainer()->get(DocumentRepositoryInterface::class)->findRecent(1000) as $document) {
            $storage->delete($document->storageKey());
        }

        $this->purge(['document_documents']);

        parent::tearDown();
    }

    #[Test]
    public function a_file_reaches_the_storage_and_the_list(): void
    {
        $this->signInAsUploader();

        $this->client->request('POST', self::SUBJECT, files: ['datei' => $this->file('Angebot.pdf')]);

        self::assertResponseRedirects(self::SUBJECT);
        self::assertSame(1, $this->documents()->countForSubject(new SubjectRef('contact', 'kontakt-1')));
    }

    #[Test]
    public function a_hostile_filename_is_stored_harmlessly(): void
    {
        // Der Weg, den ein Angreifer nimmt: nicht das Formular, sondern eine
        // selbstgebaute Anfrage.
        $this->signInAsUploader();

        $this->client->request('POST', self::SUBJECT, files: [
            'datei' => $this->file('../../etc/passwd'),
        ]);

        $document = $this->documents()->findRecent(1)[0];

        self::assertSame('passwd', $document->filename());
        self::assertStringNotContainsString('..', $document->storageKey());
    }

    #[Test]
    public function a_request_without_a_file_says_so_instead_of_breaking(): void
    {
        // Tritt auch ohne boese Absicht auf: PHP bricht bei ueberschrittenem
        // upload_max_filesize ab, und der Controller sieht dann schlicht keine
        // Datei. Ohne diesen Zweig bekaeme der Benutzer eine leere Seite.
        $this->signInAsUploader();

        $this->client->request('POST', self::SUBJECT);

        self::assertResponseRedirects(self::SUBJECT);
        self::assertSame(0, $this->documents()->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'keine gueltige Datei');
    }

    #[Test]
    public function an_unresolvable_subject_is_refused_with_a_message(): void
    {
        // Kein Modul loest "rechnung" auf. Der Upload waere sonst fuer immer
        // unauffindbar - der Eintrag taucht in keiner Detailseite auf.
        $this->signInAsUploader();

        $this->client->request('POST', '/documents/an/rechnung/r-1', files: [
            'datei' => $this->file('Angebot.pdf'),
        ]);

        self::assertSame(0, $this->documents()->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'Kein Modul loest den Typ');
    }

    #[Test]
    public function deleting_without_a_valid_token_does_nothing(): void
    {
        // Ohne diese Pruefung genuegte ein Bild-Tag auf einer fremden Seite,
        // um fremde Dateien loeschen zu lassen.
        $admin = $this->givenUser('chefin@example.test', 'Chefin', [Role::ADMIN], 'Vertrieb');
        $this->signIn($this->client, $admin);
        $this->client->request('POST', self::SUBJECT, files: ['datei' => $this->file('Angebot.pdf')]);

        $document = $this->documents()->findRecent(1)[0];

        $this->client->request('POST', '/documents/datei/'.$document->id(), ['_token' => 'falsch']);

        self::assertSame(1, $this->documents()->countAll(), 'Die Datei muss noch da sein.');
    }

    #[Test]
    public function deleting_with_a_valid_token_removes_the_file_too(): void
    {
        $admin = $this->givenUser('chefin@example.test', 'Chefin', [Role::ADMIN], 'Vertrieb');
        $this->signIn($this->client, $admin);
        $this->client->request('POST', self::SUBJECT, files: ['datei' => $this->file('Angebot.pdf')]);

        $document = $this->documents()->findRecent(1)[0];
        $key = $document->storageKey();

        // Das Token aus dem gerenderten Formular holen, nicht selbst erzeugen:
        // so wird mitgeprueft, dass das Template ueberhaupt eines ausgibt.
        $crawler = $this->client->request('GET', self::SUBJECT);
        $form = $crawler->filter('form[action="/documents/datei/'.$document->id().'"]')->form();

        $this->client->submit($form);

        self::assertSame(0, $this->documents()->countAll());
        self::assertFalse(
            static::getContainer()->get(DocumentStorageInterface::class)->has($key),
            'Die Datei im Speicher darf nicht zurueckbleiben.',
        );
    }

    private function signInAsUploader(): void
    {
        $this->signIn($this->client, $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb'));
    }

    private function documents(): DocumentRepositoryInterface
    {
        return static::getContainer()->get(DocumentRepositoryInterface::class);
    }

    /**
     * Eine echte hochgeladene Datei, wie sie PHP im Request ablegt.
     */
    private function file(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'officore-upload');
        \assert(\is_string($path));
        file_put_contents($path, '%PDF-1.4 Testinhalt');

        return new UploadedFile($path, $clientName, 'application/pdf', null, true);
    }
}
