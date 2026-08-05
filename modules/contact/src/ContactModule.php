<?php

declare(strict_types=1);

namespace Crm\Contact;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Das Modul-Bundle.
 *
 * Es konfiguriert sich selbst: Doctrine-Mapping und Twig-Pfad werden hier
 * geprependet, nicht in der App. Dadurch bleibt config/packages/ im Core frei
 * von Modulwissen und ein Modul ist durch Installieren + Eintragen in
 * config/bundles.php vollstaendig aktiv.
 */
final class ContactModule extends AbstractBundle implements CrmModuleInterface
{
    public const NAME = 'contact';

    protected string $extensionAlias = 'crm_contact';

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
        return [];
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Nur mappen, was auch da ist. Ohne diese Guards bricht ein Setup ohne
        // DoctrineBundle oder ohne TwigBundle beim Container-Build ab, statt
        // das Modul einfach reduziert laufen zu lassen.
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        'uuid' => UuidType::class,
                    ],
                ],
                'orm' => [
                    'mappings' => [
                        'CrmContact' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath().'/src/Domain',
                            'prefix' => 'Crm\\Contact\\Domain',
                            'alias' => 'Contact',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [
                    $this->getPath().'/templates' => 'ContactModule',
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
