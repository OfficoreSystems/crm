<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Double;

/**
 * Zaehlt die Aufrufe von findMany().
 *
 * Damit laesst sich nachweisen, dass die Kontaktliste die Firmen einmal fuer
 * die ganze Seite aufloest und nicht je Zeile - ein N+1 waere hier besonders
 * teuer, weil es ueber eine Modulgrenze laeuft.
 */
final class CountingCompanyFinder extends FakeCompanyFinder
{
    public int $findManyCalls = 0;

    public function findMany(array $ids): array
    {
        ++$this->findManyCalls;

        return parent::findMany($ids);
    }
}
