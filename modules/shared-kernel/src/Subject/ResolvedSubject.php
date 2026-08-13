<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

use Crm\SharedKernel\Localization\TranslatableText;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A resolved reference: enough to display and link it.
 *
 * Deliberately a route name plus parameters instead of a finished URL. Building
 * the URL is the template's job, because the template knows the router - and a
 * pre-built path would be silently wrong once the routing changes.
 */
final readonly class ResolvedSubject
{
    /**
     * @param string                            $label           Data, not a key - the name of the
     *                                                           record.
     * @param array<string, string|int>         $routeParameters
     * @param string|TranslatableInterface|null $typeLabel       Translation key.
     * @param string|TranslatableInterface|null $description     Second line: what distinguishes this
     *                                                           record from ones with the same name.
     *                                                           Without it a result list with three
     *                                                           "Nordwind" entries is unusable.
     *                                                           Often mixes data and translation -
     *                                                           that is what {@see TranslatableText}
     *                                                           is for.
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public ?string $route = null,
        public array $routeParameters = [],
        public string|TranslatableInterface|null $typeLabel = null,
        public string|TranslatableInterface|null $description = null,
    ) {
    }

    public function isLinkable(): bool
    {
        return null !== $this->route;
    }

    public function key(): string
    {
        return SubjectRef::keyFor($this->type, $this->id);
    }
}
