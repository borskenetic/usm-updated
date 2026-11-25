<?php

namespace App\Support;

/**
 * Parses patron QR / barcode payloads from official USM IDs and library cards.
 *
 * Observed school-ID formats (change almost every year):
 * - tab:            {id}\t{NAME}\t{COURSE}\t{birthday}
 * - comma + id:     {id},{NAME},{COURSE},{semester...}
 * - comma name:     {NAME},{COURSE},,{birthday?}
 * - bare id:        {id}
 * - url:            https://…/{id}
 * - library multi:  {id}\n{NAME}\n{COURSE}
 */
class PatronQrPayload
{
    /**
     * @return array{
     *   student_no:?string,
     *   full_name:?string,
     *   course:?string,
     *   birthday:?string,
     *   raw:string
     * }
     */
    public static function parse(string $raw): array
    {
        $raw = trim(str_replace("\r", '', $raw));

        $empty = [
            'student_no' => null,
            'full_name' => null,
            'course' => null,
            'birthday' => null,
            'raw' => $raw,
        ];

        if ($raw === '') {
            return $empty;
        }

        // URL payloads: keep trailing path segment if it looks like an ID.
        if (preg_match('#^https?://#i', $raw)) {
            $path = parse_url($raw, PHP_URL_PATH) ?: '';
            $segment = trim((string) basename($path));
            if (self::looksLikeId($segment)) {
                return [
                    'student_no' => $segment,
                    'full_name' => null,
                    'course' => null,
                    'birthday' => null,
                    'raw' => $raw,
                ];
            }
        }

        // Tab-separated (seen on older laminated backs).
        if (str_contains($raw, "\t")) {
            $parts = array_map('trim', explode("\t", $raw));

            return self::fromParts($parts, $raw);
        }

        // Multiline library QR.
        if (str_contains($raw, "\n")) {
            $parts = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn ($l) => $l !== ''));

            return self::fromParts($parts, $raw);
        }

        // Comma-separated official IDs.
        if (str_contains($raw, ',')) {
            return self::parseComma($raw);
        }

        if (self::looksLikeId($raw)) {
            return [
                'student_no' => $raw,
                'full_name' => null,
                'course' => null,
                'birthday' => null,
                'raw' => $raw,
            ];
        }

        if (preg_match('/(\d{2}-\d+(?:-\d+)?)/', $raw, $m)) {
            return [
                'student_no' => $m[1],
                'full_name' => null,
                'course' => null,
                'birthday' => null,
                'raw' => $raw,
            ];
        }

        return [
            'student_no' => null,
            'full_name' => $raw,
            'course' => null,
            'birthday' => null,
            'raw' => $raw,
        ];
    }

    /**
     * @param  list<string>  $parts
     * @return array{student_no:?string,full_name:?string,course:?string,birthday:?string,raw:string}
     */
    private static function fromParts(array $parts, string $raw): array
    {
        $parts = array_values(array_map('trim', $parts));

        if (self::looksLikeId($parts[0] ?? null)) {
            return [
                'student_no' => $parts[0],
                'full_name' => self::nonEmpty($parts[1] ?? null),
                'course' => self::nonEmpty($parts[2] ?? null),
                'birthday' => self::nonEmpty($parts[3] ?? null),
                'raw' => $raw,
            ];
        }

        return [
            'student_no' => null,
            'full_name' => self::nonEmpty($parts[0] ?? null),
            'course' => self::nonEmpty($parts[1] ?? null),
            'birthday' => self::nonEmpty($parts[2] ?? null) ?? self::nonEmpty($parts[3] ?? null),
            'raw' => $raw,
        ];
    }

    /**
     * @return array{student_no:?string,full_name:?string,course:?string,birthday:?string,raw:string}
     */
    private static function parseComma(string $raw): array
    {
        $parts = array_map('trim', explode(',', $raw));

        // id,name,course[,semester|birthday...]
        if (self::looksLikeId($parts[0] ?? null)) {
            return [
                'student_no' => $parts[0],
                'full_name' => self::nonEmpty($parts[1] ?? null),
                'course' => self::nonEmpty($parts[2] ?? null),
                'birthday' => self::guessBirthday(array_slice($parts, 3)),
                'raw' => $raw,
            ];
        }

        // NAME,COURSE,,[birthday with commas...]
        $name = self::nonEmpty($parts[0] ?? null);
        $course = self::nonEmpty($parts[1] ?? null);
        $rest = array_slice($parts, 2);
        // Drop empty placeholders between course and birthday.
        while ($rest !== [] && trim((string) $rest[0]) === '') {
            array_shift($rest);
        }

        return [
            'student_no' => null,
            'full_name' => $name,
            'course' => $course,
            'birthday' => self::guessBirthday($rest),
            'raw' => $raw,
        ];
    }

    /**
     * @param  list<string>  $parts
     */
    private static function guessBirthday(array $parts): ?string
    {
        $joined = trim(implode(',', $parts));
        if ($joined === '') {
            return null;
        }

        // Semester strings are not birthdays.
        if (preg_match('/semester|a\.?\s*y\.?/i', $joined)) {
            return null;
        }

        return $joined;
    }

    public static function normalizeName(string $name): string
    {
        $qrName = strtoupper($name);

        // Drop common name suffixes before stripping letters.
        $qrName = preg_replace('/\b(JR|SR|II|III|IV|V)\.?\b/', ' ', $qrName) ?? $qrName;

        $qrName = preg_replace('/[^A-Z\s]/', '', $qrName) ?? '';
        $qrName = preg_replace('/\b[A-Z]\b/', '', $qrName) ?? '';
        $qrName = preg_replace('/\s+/', '', $qrName) ?? '';

        return $qrName;
    }

    public static function normalizeCourse(?string $course): string
    {
        if ($course === null || trim($course) === '') {
            return '';
        }

        $c = strtoupper($course);
        $c = preg_replace('/[^A-Z0-9]/', '', $c) ?? '';

        return $c;
    }

    /**
     * Loose course equality: exact, prefix, or one contains the other
     * (ABPhilo vs ABPhilos, BIT vs BINDTECH, etc.).
     */
    public static function coursesCompatible(?string $a, ?string $b): bool
    {
        $aa = self::normalizeCourse($a);
        $bb = self::normalizeCourse($b);

        if ($aa === '' || $bb === '') {
            return false;
        }

        if ($aa === $bb) {
            return true;
        }

        return str_starts_with($aa, $bb)
            || str_starts_with($bb, $aa)
            || str_contains($aa, $bb)
            || str_contains($bb, $aa);
    }

    public static function looksLikeId(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match('/^\d{2}-\d+(?:-\d+)?$/', $value);
    }

    private static function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
