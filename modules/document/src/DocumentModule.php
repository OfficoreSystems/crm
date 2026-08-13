<?php

declare(strict_types=1);

namespace Crm\Document;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DocumentModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'document';

    /**
     * Der Name des Flysystem-Storage. Oeffentlich, weil die Dienstdefinition
     * in config/services.php ihn braucht - und weil er sonst an zwei Stellen
     * als Zeichenkette stuende.
     */
    public const STORAGE = 'document.storage';

    protected string $extensionAlias = 'crm_document';

    public function name(): string
    {
        return self::NAME;
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function dependencies(): array
    {
        // Keine. Dokumente haengen an Subjekten, die andere Module aufloesen -
        // gibt es keines, laesst sich nichts hochladen, aber die Anwendung
        // startet.
        return [];
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => ['uuid' => UuidType::class],
                ],
                'orm' => [
                    'mappings' => [
                        'CrmDocument' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Document\\Domain',
                            'alias' => 'Document',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [$this->getPath().'/templates' => 'DocumentModule'],
            ]);
        }

        // Der Objektspeicher. Steht hier und nicht in der Anwendung, weil er
        // zu diesem Modul gehoert: wer es entfernt, entfernt auch seinen
        // Speicher, ohne eine Datei im Core anfassen zu muessen.
        //
        // In Tests ein lokales Verzeichnis - ein Testlauf soll ohne laufenden
        // MinIO-Container durchgehen, sonst ist die Suite nicht mehr das, was
        // man vor dem Commit eben durchlaufen laesst.
        if ($builder->hasExtension('flysystem')) {
            // Verschachtelte Schreibweise, nicht 'adapter' plus 'options':
            // letztere ist seit flysystem-bundle 3.7 abgekuendigt.
            $builder->prependExtensionConfig('flysystem', [
                'storages' => [
                    self::STORAGE => 'test' === $builder->getParameter('kernel.environment')
                        ? ['local' => ['directory' => '%kernel.project_dir%/var/document-test']]
                        : ['asyncaws' => [
                            'client' => 'crm.document.s3_client',
                            'bucket' => '%env(STORAGE_BUCKET)%',
                        ]],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }
}
