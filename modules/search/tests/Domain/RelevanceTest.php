<?php

declare(strict_types=1);

namespace Crm\Search\Tests\Domain;

use Crm\Search\Domain\Relevance;
use Crm\Search\Domain\SearchHit;
use Crm\SharedKernel\Subject\ResolvedSubject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RelevanceTest extends TestCase
{
    #[Test]
    public function an_exact_label_match_scores_highest(): void
    {
        self::assertSame(Relevance::EXACT, Relevance::score($this->subject('Nordwind Logistik'), 'Nordwind Logistik'));
    }

    #[Test]
    public function the_comparison_ignores_case_and_padding(): void
    {
        self::assertSame(Relevance::EXACT, Relevance::score($this->subject('Nordwind Logistik'), '  nordwind LOGISTIK '));
    }

    #[Test]
    public function a_prefix_beats_a_match_in_the_middle(): void
    {
        $prefix = Relevance::score($this->subject('Nordwind Logistik'), 'Nordwind');
        $middle = Relevance::score($this->subject('Reederei Nordwind'), 'Nordwind');

        self::assertSame(Relevance::PREFIX, $prefix);
        self::assertSame(Relevance::CONTAINS, $middle);
        self::assertGreaterThan($middle, $prefix);
    }

    #[Test]
    public function the_label_beats_the_description(): void
    {
        // Wer "nordwind" tippt, meint eher die Firma als einen Kontakt, der
        // dort nur seine E-Mail-Adresse hat.
        $label = Relevance::score($this->subject('Nordwind Logistik'), 'nordwind');
        $description = Relevance::score($this->subject('Anna Berger', 'anna@nordwind.example'), 'nordwind');

        self::assertGreaterThan($description, $label);
        self::assertSame(Relevance::DESCRIPTION, $description);
    }

    #[Test]
    public function a_hit_without_a_visible_match_still_counts(): void
    {
        // Das Modul hat den Datensatz geliefert, also passt er irgendwie -
        // vielleicht ueber ein Feld, das gar nicht angezeigt wird. Er faellt
        // ans Ende, verschwindet aber nicht.
        $score = Relevance::score($this->subject('Anna Berger'), 'nordwind');

        self::assertSame(Relevance::WEAK, $score);
        self::assertGreaterThan(0, $score);
    }

    #[Test]
    public function a_missing_description_does_not_break_the_scoring(): void
    {
        self::assertSame(Relevance::WEAK, Relevance::score($this->subject('Anna Berger', null), 'xyz'));
    }

    #[Test]
    public function an_empty_query_scores_everything_the_same(): void
    {
        self::assertSame(Relevance::WEAK, Relevance::score($this->subject('Nordwind'), ''));
        self::assertSame(Relevance::WEAK, Relevance::score($this->subject('Nordwind'), '   '));
    }

    #[Test]
    public function a_hit_knows_whether_it_is_strong(): void
    {
        self::assertTrue(SearchHit::for($this->subject('Nordwind'), 'Nordwind')->isStrong());
        self::assertTrue(SearchHit::for($this->subject('Nordwind Logistik'), 'Nordwind')->isStrong());
        self::assertFalse(SearchHit::for($this->subject('Reederei Nordwind'), 'Nordwind')->isStrong());
    }

    private function subject(string $label, ?string $description = null): ResolvedSubject
    {
        return new ResolvedSubject('company', 'x', $label, description: $description);
    }
}
