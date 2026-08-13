<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Localization;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ein Text, der erst beim Anzeigen zu Text wird.
 *
 * Module bauen ihre Beschriftungen dort, wo die Daten liegen - in der
 * Infrastructure-Schicht. Die darf Symfony nicht sehen, ein injizierter
 * Uebersetzer waere genau diese Abhaengigkeit. Also reichen sie stattdessen
 * Schluessel und Platzhalter weiter, und uebersetzt wird im Template.
 *
 * Implementiert TranslatableInterface aus den Symfony-*Contracts* - das ist
 * ein Interface-Paket ohne Implementierung, vergleichbar mit den
 * Doctrine-Mapping-Attributen in der Domain. Der Gewinn: Twigs |trans-Filter
 * kennt dieses Interface und braucht keinen Sonderweg.
 *
 * Platzhalter duerfen selbst uebersetzbar sein. Das ist der Grund, warum diese
 * Klasse existiert und nicht einfach ein Array genuegt: eine Beschreibung wie
 * "Nordwind Logistik · Angebot" besteht aus einem Namen (Daten) und einer
 * Stufe (Uebersetzung), und beide muessen unterschiedlich behandelt werden.
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
