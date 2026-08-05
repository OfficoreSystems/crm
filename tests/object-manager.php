<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

// Nur der EntityManager, keine Verbindung: phpstan-doctrine liest daraus die
// Mapping-Metadaten und kann damit Query-Rueckgabetypen aufloesen.
return $kernel->getContainer()->get('doctrine')->getManager();
