<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],

    // --- Vertragsschicht ---
    // Muss vor den Modulen stehen: die Module ueberschreiben Vorgabedienste
    // aus dem Shared Kernel, und dabei gewinnt die spaetere Definition.
    Crm\SharedKernel\CrmSharedKernelBundle::class => ['all' => true],

    // --- Module ---
    Crm\User\UserModule::class => ['all' => true],
    Crm\Company\CompanyModule::class => ['all' => true],
    Crm\Contact\ContactModule::class => ['all' => true],
    Crm\Deal\DealModule::class => ['all' => true],
    Crm\Activity\ActivityModule::class => ['all' => true],
    Crm\Search\SearchModule::class => ['all' => true],
    Crm\Dashboard\DashboardModule::class => ['all' => true],
];
