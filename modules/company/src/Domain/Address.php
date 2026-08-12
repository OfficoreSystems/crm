<?php

declare(strict_types=1);

namespace Crm\Company\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Anschrift als Wertobjekt.
 *
 * Als Embeddable statt als vier lose Spalten: die Anschrift wird in weiteren
 * Modulen wieder gebraucht, und dort soll niemand erneut entscheiden muessen,
 * ob das Feld nun "zip", "plz" oder "postal_code" heisst. Unveraenderlich -
 * eine geaenderte Anschrift ist eine neue.
 */
#[ORM\Embeddable]
final readonly class Address
{
    #[ORM\Column(length: 180, nullable: true)]
    public ?string $street;

    #[ORM\Column(name: 'postal_code', length: 20, nullable: true)]
    public ?string $postalCode;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $city;

    #[ORM\Column(length: 2, nullable: true)]
    public ?string $country;

    public function __construct(
        ?string $street = null,
        ?string $postalCode = null,
        ?string $city = null,
        ?string $country = null,
    ) {
        $this->street = self::normalize($street);
        $this->postalCode = self::normalize($postalCode);
        $this->city = self::normalize($city);
        $this->country = self::normalizeCountry($country);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function isEmpty(): bool
    {
        return null === $this->street
            && null === $this->postalCode
            && null === $this->city
            && null === $this->country;
    }

    /**
     * Einzeilige Darstellung fuer Listen und Exporte.
     */
    public function asLine(): string
    {
        $postalAndCity = trim(implode(' ', array_filter([$this->postalCode, $this->city])));

        return implode(', ', array_filter([$this->street, '' === $postalAndCity ? null : $postalAndCity, $this->country]));
    }

    private static function normalize(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * Zwei Buchstaben, gross - ISO 3166-1 alpha-2.
     *
     * Ohne diese Festlegung stehen "de", "DE", "Deutschland" und "Germany"
     * nebeneinander in derselben Spalte, und jede Auswertung nach Land wird
     * zum Ratespiel.
     */
    private static function normalizeCountry(?string $value): ?string
    {
        $trimmed = strtoupper(trim((string) $value));

        if ('' === $trimmed) {
            return null;
        }

        if (1 !== preg_match('/^[A-Z]{2}$/', $trimmed)) {
            throw new \InvalidArgumentException(sprintf(
                'Land muss ein zweibuchstabiger ISO-3166-1-Code sein, "%s" ist keiner.',
                $value,
            ));
        }

        return $trimmed;
    }
}
