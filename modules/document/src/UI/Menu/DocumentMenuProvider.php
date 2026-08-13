<?php

declare(strict_types=1);

namespace Crm\Document\UI\Menu;

use Crm\SharedKernel\Menu\MenuItem;
use Crm\SharedKernel\Menu\MenuProviderInterface;

final class DocumentMenuProvider implements MenuProviderInterface
{
    public function getMenuItems(): iterable
    {
        // Niedrige Priority: Dokumente sucht man ueber den Datensatz, an dem
        // sie haengen. Die Liste ist der Nachschlageweg, nicht der Einstieg.
        yield new MenuItem(
            label: 'Dokumente',
            route: 'document_index',
            icon: 'paperclip',
            priority: 40,
        );
    }
}
