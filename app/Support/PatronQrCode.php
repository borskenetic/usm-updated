<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Generates the next unique patron QR code (S-xxxxxxxx / E-xxxxxxxx).
 *
 * Uses the highest existing numeric suffix—not the latest row id—so imports,
 * deletions, and concurrent approvals cannot reuse an already-assigned code.
 */
class PatronQrCode
{
    public static function nextStudent(): string
    {
        return self::next(Student::class, 'S-');
    }

    public static function nextEmployee(): string
    {
        return self::next(Employee::class, 'E-');
    }

    private static function next(string $modelClass, string $prefix): string
    {
        return DB::transaction(function () use ($modelClass, $prefix) {
            $lockName = 'patron_qr_'.str_replace('-', '', $prefix);
            $useNamedLock = DB::getDriverName() === 'mysql';

            if ($useNamedLock && ! self::acquireNamedLock($lockName)) {
                throw new \RuntimeException('Could not assign QR code right now. Please try again.');
            }

            try {
                $suffixStart = strlen($prefix) + 1;

                $query = $modelClass::query()
                    ->where('qrcode', 'like', $prefix.'%')
                    ->orderByRaw('CAST(SUBSTRING(qrcode, ?) AS UNSIGNED) DESC', [$suffixStart]);

                if (! $useNamedLock) {
                    $query->lockForUpdate();
                }

                $top = $query->value('qrcode');

                $nextNumber = 1;

                if ($top && preg_match('/'.preg_quote($prefix, '/').'(\d+)/', $top, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                }

                return $prefix.str_pad((string) $nextNumber, 8, '0', STR_PAD_LEFT);
            } finally {
                if ($useNamedLock) {
                    self::releaseNamedLock($lockName);
                }
            }
        });
    }

    private static function acquireNamedLock(string $name): bool
    {
        $result = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$name]);

        return (int) ($result->acquired ?? 0) === 1;
    }

    private static function releaseNamedLock(string $name): void
    {
        DB::select('SELECT RELEASE_LOCK(?)', [$name]);
    }
}
