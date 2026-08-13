<?php

declare(strict_types=1);

use AsyncAws\S3\S3Client;
use Crm\Document\Application\UploadDocument;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\Document\Domain\DocumentStorageInterface;
use Crm\Document\DocumentModule;
use Crm\Document\Infrastructure\Doctrine\DoctrineDocumentRepository;
use Crm\Document\Infrastructure\Storage\FlysystemDocumentStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Crm\\Document\\', '../src/')
        ->exclude('../src/Domain/');

    $services->alias(DocumentRepositoryInterface::class, DoctrineDocumentRepository::class);

    // Der S3-Client. Eigene Definition statt Autowiring, weil die Zugangsdaten
    // aus der Umgebung kommen und nirgends im Repo stehen duerfen.
    //
    // pathStyleEndpoint ist fuer MinIO noetig: der Bucket steht im Pfad
    // (storage:9000/crm-documents) statt in der Subdomain. Ohne das laeuft
    // jede Anfrage gegen einen Hostnamen, den es lokal nicht gibt.
    $services->set('crm.document.s3_client', S3Client::class)
        ->args([[
            'endpoint' => '%env(STORAGE_ENDPOINT)%',
            'accessKeyId' => '%env(STORAGE_KEY)%',
            'accessKeySecret' => '%env(STORAGE_SECRET)%',
            'region' => '%env(STORAGE_REGION)%',
            'pathStyleEndpoint' => true,
        ]]);

    $services->set(FlysystemDocumentStorage::class)
        ->args([service(DocumentModule::STORAGE)]);

    $services->alias(DocumentStorageInterface::class, FlysystemDocumentStorage::class);

    $services->set(UploadDocument::class)
        ->arg('$maxBytes', '%env(int:DOCUMENT_MAX_BYTES)%');
};
