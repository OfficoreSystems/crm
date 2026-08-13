<?php

declare(strict_types=1);

namespace Crm\Deal\Domain;

/**
 * Die Stufen der Verkaufspipeline.
 *
 * WON und LOST sind Endzustaende. Alles davor ist offen und zaehlt in den
 * Pipeline-Wert.
 */
enum Stage: string
{
    case LEAD = 'lead';
    case QUALIFIED = 'qualified';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case WON = 'won';
    case LOST = 'lost';

    public static function initial(): self
    {
        return self::LEAD;
    }

    public function label(): string
    {
        return match ($this) {
            self::LEAD => 'deal.stage.lead',
            self::QUALIFIED => 'deal.stage.qualified',
            self::PROPOSAL => 'deal.stage.proposal',
            self::NEGOTIATION => 'deal.stage.negotiation',
            self::WON => 'deal.stage.won',
            self::LOST => 'deal.stage.lost',
        };
    }

    /**
     * Reihenfolge im Board. Endzustaende stehen hinten.
     */
    public function position(): int
    {
        return match ($this) {
            self::LEAD => 1,
            self::QUALIFIED => 2,
            self::PROPOSAL => 3,
            self::NEGOTIATION => 4,
            self::WON => 5,
            self::LOST => 6,
        };
    }

    public function isOpen(): bool
    {
        return !$this->isClosed();
    }

    public function isClosed(): bool
    {
        return self::WON === $this || self::LOST === $this;
    }

    public function isWon(): bool
    {
        return self::WON === $this;
    }

    /**
     * Nur die offenen Stufen, in Board-Reihenfolge.
     *
     * @return list<self>
     */
    public static function openStages(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => $s->isOpen()));
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        $cases = self::cases();
        usort($cases, static fn (self $a, self $b): int => $a->position() <=> $b->position());

        return $cases;
    }
}
