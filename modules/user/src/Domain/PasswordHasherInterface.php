<?php

declare(strict_types=1);

namespace Crm\User\Domain;

/**
 * Port fuer das Passwort-Hashing.
 *
 * Die Domain gibt vor, *dass* gehasht wird, aber nicht *womit*. Die
 * Implementierung liegt in Infrastructure und reicht an Symfonys
 * PasswordHasher durch. Dadurch bleiben die Use-Cases ohne Framework
 * testbar - und ein Wechsel des Algorithmus beruehrt eine einzige Klasse.
 */
interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}
