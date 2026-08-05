<?php

declare(strict_types=1);

/*
 * Coverage-Gate.
 *
 * PHPUnit kennt kein "fail under" - es schreibt nur den Bericht. Dieses Skript
 * liest den Clover-Report und bricht ab, wenn die Zeilenabdeckung unter der
 * Schwelle liegt.
 *
 *   php tools/coverage-gate.php var/clover.xml 80
 *
 * Die Schwelle wird nie gesenkt, um einen Build gruen zu bekommen. Wer sie
 * reisst, schreibt Tests.
 */

$reportPath = $argv[1] ?? 'var/clover.xml';
$threshold = (float) ($argv[2] ?? 80);

if (!is_file($reportPath)) {
    fwrite(STDERR, sprintf(
        "Coverage-Gate: %s nicht gefunden.\nLief PHPUnit mit --coverage-clover und war ein Coverage-Treiber aktiv (pcov oder xdebug)?\n",
        $reportPath,
    ));

    exit(1);
}

$xml = simplexml_load_file($reportPath);

if (false === $xml) {
    fwrite(STDERR, sprintf("Coverage-Gate: %s ist kein lesbares XML.\n", $reportPath));

    exit(1);
}

$metrics = $xml->project->metrics ?? null;

if (null === $metrics) {
    fwrite(STDERR, "Coverage-Gate: Im Report fehlen die Projekt-Metriken.\n");

    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if (0 === $statements) {
    fwrite(STDERR, "Coverage-Gate: Der Report enthaelt keine Zeilen. Stimmen die <source>-Pfade in phpunit.xml.dist?\n");

    exit(1);
}

$percentage = $covered / $statements * 100;

// Die schwaechsten Dateien mit ausgeben - bei einem roten Gate will man nicht
// erst suchen muessen, wo es fehlt.
$files = [];

foreach ($xml->xpath('//file') ?: [] as $file) {
    $fileStatements = (int) $file->metrics['statements'];

    if (0 === $fileStatements) {
        continue;
    }

    $fileCovered = (int) $file->metrics['coveredstatements'];
    $files[(string) $file['name']] = [
        'percentage' => $fileCovered / $fileStatements * 100,
        'missing' => $fileStatements - $fileCovered,
    ];
}

asort($files);

printf("Zeilenabdeckung: %.2f%% (%d von %d) - Schwelle %.2f%%\n", $percentage, $covered, $statements, $threshold);

if ($percentage >= $threshold) {
    exit(0);
}

fwrite(STDERR, "\nCoverage-Gate gerissen. Am wenigsten abgedeckt:\n");

$shown = 0;

foreach ($files as $name => $data) {
    if ($data['percentage'] >= $threshold && $shown > 0) {
        break;
    }

    fwrite(STDERR, sprintf(
        "  %6.2f%%  %s  (%d Zeilen ohne Test)\n",
        $data['percentage'],
        str_replace(getcwd().'/', '', $name),
        $data['missing'],
    ));

    if (++$shown >= 10) {
        break;
    }
}

exit(1);
