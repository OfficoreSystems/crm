<?php

/*
 * Die Installationsliste - und die einzige Stelle im Core, an der Modulnamen
 * stehen duerfen. Das ist Registrierung, keine Kopplung: es wird hier nichts
 * aus einem Modul importiert oder aufgerufen, und kein Core-Code haengt an
 * einer dieser Klassen.
 *
 * Deptrac analysiert config/ deshalb nicht mit; die Schranke gilt fuer Code
 * unter src/ und modules/.
 *
 * Achtung: Symfony Flex schreibt diese Datei bei composer require/remove
 * automatisch fort und wirft dabei Kommentare weg. Nach groesseren
 * Composer-Operationen also kurz gegenlesen.
 */

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

    // --- Vertragsschicht ---
    Crm\SharedKernel\CrmSharedKernelBundle::class => ['all' => true],

    // --- Module ---
    Crm\Contact\ContactModule::class => ['all' => true],
];
