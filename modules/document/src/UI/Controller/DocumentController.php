<?php

declare(strict_types=1);

namespace Crm\Document\UI\Controller;

use Crm\Document\Application\DeleteDocument;
use Crm\Document\Application\DocumentTooLarge;
use Crm\Document\Application\UploadDocument;
use Crm\Document\Application\UploadDocumentCommand;
use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentFileMissing;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;
use Crm\Document\Domain\UnresolvableSubject;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/documents', name: 'document_')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly SubjectResolverRegistry $subjects,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('document.view')]
    public function index(): Response
    {
        $documents = $this->documents->findRecent(100);

        return $this->render('@DocumentModule/document/index.html.twig', [
            'documents' => $documents,
            'labels' => $this->resolveLabels($documents),
        ]);
    }

    /**
     * Die Liste zu einem Datensatz - der Weg, ueber den Dokumente im Alltag
     * gefunden werden.
     */
    #[Route('/an/{type}/{id}', name: 'for_subject', methods: ['GET'])]
    #[IsGranted('document.view')]
    public function forSubject(string $type, string $id): Response
    {
        $subject = new SubjectRef($type, $id);
        $documents = $this->documents->findForSubject($subject);

        return $this->render('@DocumentModule/document/subject.html.twig', [
            'documents' => $documents,
            'subject' => $subject,
            'label' => $this->subjects->resolve($subject),
        ]);
    }

    #[Route('/an/{type}/{id}', name: 'upload', methods: ['POST'])]
    #[IsGranted('document.create')]
    public function upload(string $type, string $id, Request $request, UploadDocument $upload): Response
    {
        $file = $request->files->get('datei');
        $subject = new SubjectRef($type, $id);

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            // Trifft auch zu, wenn PHP selbst abgebrochen hat - etwa bei
            // ueberschrittenem upload_max_filesize. Ohne diesen Zweig
            // bekaeme der Benutzer eine leere Seite.
            $this->addFlash('error', 'Es kam keine gueltige Datei an. Vielleicht war sie zu gross fuer den Server.');

            return $this->redirectToRoute('document_for_subject', ['type' => $type, 'id' => $id]);
        }

        $actor = $this->getUser();
        $stream = fopen($file->getPathname(), 'rb');

        try {
            ($upload)(new UploadDocumentCommand(
                subject: $subject,
                filename: $file->getClientOriginalName(),
                // Nicht getClientMimeType(): den bestimmt der Browser, und er
                // laesst sich frei setzen. getMimeType() rueckt der Datei
                // selbst auf den Leib.
                mimeType: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize() ?: 0,
                contents: false === $stream ? '' : $stream,
                ownerId: $this->actorId($actor),
                ownerTeamId: $this->actorTeamId($actor),
            ));

            $this->addFlash('success', 'Datei abgelegt.');
        } catch (DocumentTooLarge|UnresolvableSubject $e) {
            $this->addFlash('error', $e->getMessage());
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $this->redirectToRoute('document_for_subject', ['type' => $type, 'id' => $id]);
    }

    /**
     * Der Download laeuft bewusst durch die Anwendung.
     *
     * Eine oeffentliche Bucket-URL waere billiger, wuerde aber die Rechte
     * aushebeln: wer den Link hat, haette die Datei. Signierte URLs waeren die
     * Alternative - dann muesste die Gueltigkeitsdauer zu den Rechten passen,
     * und ein entzogenes Recht wirkte erst nach Ablauf.
     */
    #[Route('/datei/{document}', name: 'download', methods: ['GET'])]
    #[IsGranted('document.view', subject: 'document')]
    public function download(Document $document, DocumentStorageInterface $storage): Response
    {
        try {
            $stream = $storage->readStream($document->storageKey());
        } catch (DocumentFileMissing) {
            throw $this->createNotFoundException('Zu diesem Eintrag existiert keine Datei mehr.');
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            if (\is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        // ATTACHMENT, nicht INLINE: eine hochgeladene HTML- oder SVG-Datei
        // wuerde sonst im Ursprung der Anwendung ausgefuehrt. makeDisposition
        // kuemmert sich zugleich um die Kodierung von Umlauten im Namen.
        $response->headers->set('Content-Type', $document->mimeType());
        $response->headers->set('Content-Length', (string) $document->size());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->filename(),
            'datei',
        ));

        return $response;
    }

    #[Route('/datei/{document}', name: 'delete', methods: ['POST'])]
    #[IsGranted('document.delete', subject: 'document')]
    public function delete(Document $document, Request $request, DeleteDocument $delete): Response
    {
        $subject = $document->subject();

        if (!$this->isCsrfTokenValid('document_delete_'.$document->id(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Die Anfrage war nicht gueltig. Bitte erneut versuchen.');
        } else {
            ($delete)($document);
            $this->addFlash('success', 'Datei geloescht.');
        }

        return $this->redirectToRoute('document_for_subject', [
            'type' => $subject->type,
            'id' => $subject->id,
        ]);
    }

    /**
     * @param list<Document> $documents
     *
     * @return array<string, ResolvedSubject>
     */
    private function resolveLabels(array $documents): array
    {
        // Gesammelt aufloesen, nicht je Zeile: sonst haette eine Liste mit
        // hundert Eintraegen hundert Abfragen zur Folge.
        return $this->subjects->resolveAll(array_map(
            static fn (Document $d): SubjectRef => $d->subject(),
            $documents,
        ));
    }

    private function actorId(?object $actor): ?Uuid
    {
        return $actor instanceof ActorInterface && Uuid::isValid($actor->actorId())
            ? Uuid::fromString($actor->actorId())
            : null;
    }

    private function actorTeamId(?object $actor): ?Uuid
    {
        if (!$actor instanceof ActorInterface) {
            return null;
        }

        $teamId = $actor->actorTeamId();

        return null !== $teamId && Uuid::isValid($teamId) ? Uuid::fromString($teamId) : null;
    }
}
