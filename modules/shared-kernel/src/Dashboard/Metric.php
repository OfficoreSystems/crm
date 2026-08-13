<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Dashboard;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A figure for the home page.
 *
 * Label and description are *translation keys*, not finished texts. Translation
 * happens in the dashboard template. There is a reason for the detour: the
 * figures are created in the infrastructure layer of the modules, and that layer
 * must not see Symfony - an injected translator would be exactly that
 * dependency.
 *

 * The value is a *string*, not a number. That is deliberate: an amount of money,
 * a percentage and a count have nothing in common except that they get
 * displayed. Were an int or float to stand here, the formatting would have to
 * move into the dashboard - and that would then have to know that deal counts in
 * cents.
 *
 * The delivering module knows best how its value reads.
 */
final readonly class Metric
{
    /**
     * @param string                    $key             Unique, following the pattern "module.figure".
     * @param string                    $label           Translation key, not a finished text.
     * @param string                    $value           Already formatted - the delivering module
     *                                                   knows best how its value reads.
     * @param string|null               $description     Translation key.
     * @param array<string, string|int> $parameters      Placeholders for label and description,
     *                                                   %count% for instance.
     * @param array<string, string|int> $routeParameters
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
        public string|TranslatableInterface|null $description = null,
        public ?string $route = null,
        public array $routeParameters = [],
        public int $priority = 0,
        public MetricTone $tone = MetricTone::NEUTRAL,
        public array $parameters = [],
        /**
         * Currency code when the value is an amount of money - null otherwise.
         *
         * Only then may the dashboard reformat the value. Without this hint it
         * would have to guess, and formatting "50 %" or "12" by currency rules
         * would be worse than nothing.
         *
         * The reason for the exception: thousands separators and decimal marks
         * depend on the language, the number itself does not. An amount
         * formatted by the module would look the same in every language - and
         * wrong in at least one of them.
         */
        public ?string $currency = null,
    ) {
        self::assertValidKey($key);

        if ('' === trim($label)) {
            throw new \InvalidArgumentException('Metric.label must not be empty.');
        }
    }

    public function isLinkable(): bool
    {
        return null !== $this->route;
    }

    /**
     * The module the figure comes from - the part before the dot.
     */
    public function module(): string
    {
        return substr($this->key, 0, (int) strpos($this->key, '.'));
    }

    /**
     * The prefix forces namespace separation. Without it the figure is called
     * "total" in three modules, and whoever registers last wins - silently.
     */
    private static function assertValidKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9-]{1,39}\.[a-z][a-z0-9_]{1,39}$/', $key)) {
            throw new \InvalidArgumentException(sprintf(
                'Metric key "%s" is invalid: expected "module.figure", both lower case.',
                $key,
            ));
        }
    }
}
