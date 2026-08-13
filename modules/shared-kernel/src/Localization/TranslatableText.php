<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Localization;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A text that only becomes text when it is displayed.
 *
 * Modules build their labels where the data lives - in the infrastructure layer.
 * That layer must not see Symfony; an injected translator would be exactly that
 * dependency. So instead they pass keys and placeholders along, and translation
 * happens in the template.
 *
 * Implements TranslatableInterface from the Symfony *contracts* - an
 * interface-only package, comparable to the Doctrine mapping attributes in the
 * domain. The gain: Twig's |trans filter knows this interface and needs no
 * special path.
 *
 * Placeholders may themselves be translatable. That is why this class exists
 * rather than a plain array being enough: a description such as "Nordwind
 * Logistik · Proposal" consists of a name (data) and a stage (translation), and
 * the two have to be treated differently.
 */
final readonly class TranslatableText implements TranslatableInterface
{
    /**
     * @param array<string, string|int|TranslatableInterface> $parameters
     */
    public function __construct(
        public string $key,
        public array $parameters = [],
    ) {
    }

    /**
     * @param array<string, string|int|TranslatableInterface> $parameters
     */
    public static function of(string $key, array $parameters = []): self
    {
        return new self($key, $parameters);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $resolved = [];

        foreach ($this->parameters as $name => $value) {
            $resolved[$name] = $value instanceof TranslatableInterface
                ? $value->trans($translator, $locale)
                : $value;
        }

        return $translator->trans($this->key, $resolved, null, $locale);
    }
}
