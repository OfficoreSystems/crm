<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Localization;

use Crm\SharedKernel\Localization\Locale;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class LocaleTest extends TestCase
{
    #[Test]
    public function english_is_the_default(): void
    {
        self::assertSame(Locale::ENGLISH, Locale::default());
    }

    #[Test]
    public function the_default_matches_the_framework_configuration(): void
    {
        // Zwei Wahrheiten, die auseinanderlaufen koennen: die Aufzaehlung hier
        // und default_locale in der Konfiguration. Laufen sie auseinander,
        // zeigt die Anwendung eine andere Sprache an, als der Umschalter
        // behauptet - und niemand sucht den Fehler in einer YAML-Datei.
        $config = Yaml::parseFile(__DIR__.'/../../../../config/packages/translation.yaml');

        self::assertSame(Locale::default()->value, $config['framework']['default_locale']);
    }

    #[Test]
    public function every_enabled_locale_exists_as_a_case(): void
    {
        $config = Yaml::parseFile(__DIR__.'/../../../../config/packages/translation.yaml');
        $enabled = $config['framework']['enabled_locales'];

        self::assertSame(
            array_map(static fn (Locale $l): string => $l->value, Locale::all()),
            $enabled,
            'enabled_locales und die Aufzaehlung muessen dieselben Sprachen in derselben Reihenfolge fuehren.',
        );
    }

    #[Test]
    public function the_language_names_itself_in_its_own_language(): void
    {
        // Wer die Oberflaeche gerade nicht versteht, sucht nach "Deutsch" und
        // nicht nach "German". Deshalb sind diese Namen bewusst nicht
        // uebersetzt.
        self::assertSame('English', Locale::ENGLISH->label());
        self::assertSame('Deutsch', Locale::GERMAN->label());
    }

    #[Test]
    public function unknown_stored_values_fall_back_instead_of_breaking(): void
    {
        // Faengt drei Faelle auf einmal: nie gewaehlt, von Hand in die
        // Datenbank geschrieben, und eine Sprache, die es einmal gab.
        self::assertSame(Locale::default(), Locale::fromStringOrDefault(null));
        self::assertSame(Locale::default(), Locale::fromStringOrDefault(''));
        self::assertSame(Locale::default(), Locale::fromStringOrDefault('kl'));
        self::assertSame(Locale::default(), Locale::fromStringOrDefault('de_DE'), 'Regionale Varianten fuehren wir nicht.');
    }

    #[Test]
    public function a_known_value_comes_back_unchanged(): void
    {
        self::assertSame(Locale::GERMAN, Locale::fromStringOrDefault('de'));
    }

    #[Test]
    public function every_language_has_a_catalogue_in_the_core(): void
    {
        // Eine Sprache ohne Katalog waere eine Oberflaeche, die stumm auf
        // Englisch zurueckfaellt - sichtbar erst beim Ausprobieren.
        foreach (Locale::all() as $locale) {
            self::assertFileExists(
                __DIR__.'/../../../../translations/messages.'.$locale->value.'.yaml',
                sprintf('Fuer "%s" fehlt der Katalog im Core.', $locale->value),
            );
        }
    }

    #[Test]
    public function the_catalogues_carry_the_same_keys(): void
    {
        // Fehlt ein Schluessel, faellt er auf Englisch zurueck - eine halb
        // uebersetzte Seite sieht nach einem Fehler aus, weil es einer ist.
        $keys = [];

        foreach (Locale::all() as $locale) {
            $path = __DIR__.'/../../../../translations/messages.'.$locale->value.'.yaml';
            $keys[$locale->value] = $this->flatten(Yaml::parseFile($path));
            sort($keys[$locale->value]);
        }

        $reference = $keys[Locale::default()->value];

        foreach ($keys as $language => $found) {
            self::assertSame($reference, $found, sprintf('Der Katalog "%s" weicht ab.', $language));
        }
    }

    /**
     * @param array<string, mixed> $catalogue
     *
     * @return list<string>
     */
    private function flatten(array $catalogue, string $prefix = ''): array
    {
        $keys = [];

        foreach ($catalogue as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;

            if (\is_array($value)) {
                $keys = [...$keys, ...$this->flatten($value, $path)];

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
