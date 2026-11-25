<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

/**
 * Makes external programs unstartable for the current process by pointing PATH at a directory that does not exist.
 * Call restore() in tearDown(): PATH is process-wide and would break later tests.
 */
final class ProgramSearchPath
{
    public const string NO_PROGRAMS = '/parcel-map-test-no-programs';

    private function __construct(private readonly string|false $saved) {}

    public static function hideAllPrograms(): self
    {
        $search = new self(getenv('PATH'));
        putenv('PATH=' . self::NO_PROGRAMS);

        return $search;
    }

    public function restore(): void
    {
        putenv($this->saved === false ? 'PATH' : 'PATH=' . $this->saved);
    }
}
