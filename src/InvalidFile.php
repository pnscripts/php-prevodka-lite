<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** Thrown when a file is asked for while the payments still have errors. */
final class InvalidFile extends \RuntimeException
{
    /** @param list<Problem> $problems */
    public function __construct(public readonly array $problems)
    {
        $errors = array_values(array_filter($problems, static fn (Problem $p) => $p->error));
        parent::__construct(count($errors) . ' error(s): ' . implode(' | ', array_map(static fn (Problem $p) => $p->line(), array_slice($errors, 0, 5))));
    }
}
