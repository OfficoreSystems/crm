<?php

declare(strict_types=1);

namespace Crm\Document\Tests\UI;

use Crm\Document\UI\Twig\DocumentExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DocumentExtensionTest extends TestCase
{
    #[Test]
    public function the_filter_is_usable_from_a_template(): void
    {
        // Geprueft wird die Registrierung, nicht die Umrechnung - die hat
        // ihren eigenen Test. Ein Filter, der nicht ankommt, faellt sonst erst
        // beim Aufruf der Seite auf.
        $twig = new Environment(new ArrayLoader([
            'test' => '{{ 26214400|document_size }}',
        ]));
        $twig->addExtension(new DocumentExtension());

        self::assertSame('25.0 MB', $twig->render('test'));
    }
}
