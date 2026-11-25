<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Resolves a scanned USM / library QR payload to a Student row.
 *
 * School QR formats vary by year; many encode only a name (+ course), not an ID.
 * Strategy:
 *  1) exact library qrcode match
 *  2) student number → id_number / qrcode
 *  3) normalized name (prefer course-compatible when several match)
 *  4) mild typo tolerance on name when course also matches uniquely
 */
class PatronQrResolver
{
    /**
     * @return array{student:?Student,parsed:array,reason:?string}
     */
    public function resolve(string $raw): array
    {
        $parsed = PatronQrPayload::parse($raw);
        $token = $parsed['raw'];

        $byQr = Student::where('qrcode', $token)->first();
        if ($byQr) {
            return $this->ok($byQr, $parsed);
        }

        if ($parsed['student_no']) {
            $id = $parsed['student_no'];
            $byId = Student::where('id_number', $id)->first()
                ?? Student::whereRaw('LOWER(TRIM(id_number)) = ?', [strtolower($id)])->first()
                ?? Student::where('qrcode', $id)->first()
                ?? Student::where('qrcode', 'like', $id."\n%")->first();

            if ($byId) {
                return $this->ok($byId, $parsed);
            }
        }

        if ($parsed['full_name']) {
            $byName = $this->matchByName($parsed['full_name'], $parsed['course'] ?? null);
            if ($byName) {
                return $this->ok($byName, $parsed);
            }
        }

        if ($parsed['student_no']) {
            return [
                'student' => null,
                'parsed' => $parsed,
                'reason' => "Student ID {$parsed['student_no']} is not registered.",
            ];
        }

        if ($parsed['full_name']) {
            return [
                'student' => null,
                'parsed' => $parsed,
                'reason' => 'No registered student matched “'.$parsed['full_name'].'”.',
            ];
        }

        return [
            'student' => null,
            'parsed' => $parsed,
            'reason' => 'RFID not recognized.',
        ];
    }

    private function ok(Student $student, array $parsed): array
    {
        return ['student' => $student, 'parsed' => $parsed, 'reason' => null];
    }

    private function matchByName(string $fullName, ?string $course): ?Student
    {
        $normalized = PatronQrPayload::normalizeName($fullName);
        if ($normalized === '') {
            return null;
        }

        $exact = Student::where('normalized_name', $normalized)->get();
        $picked = $this->pickBest($exact, $course);
        if ($picked) {
            return $picked;
        }

        // Typo tolerance (e.g. FROTEZ vs FORTEZ) — only when course narrows to one person.
        if ($course) {
            $fuzzy = $this->fuzzyByCourse($fullName, $normalized, $course);
            if ($fuzzy) {
                return $fuzzy;
            }
        }

        return null;
    }

    private function probableLastName(string $fullName): string
    {
        $name = strtoupper($fullName);
        $name = preg_replace('/\b(JR|SR|II|III|IV|V)\.?\b/', ' ', $name) ?? $name;
        $name = preg_replace('/[^A-Z\s]/', '', $name) ?? '';
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => strlen($p) > 1));

        return $parts === [] ? '' : (string) end($parts);
    }

    /**
     * @param  Collection<int, Student>  $candidates
     */
    private function pickBest(Collection $candidates, ?string $course): ?Student
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($course) {
            $courseMatches = $candidates->filter(
                fn (Student $s) => PatronQrPayload::coursesCompatible($course, $s->course)
            );

            if ($courseMatches->count() === 1) {
                return $courseMatches->first();
            }

            if ($courseMatches->isNotEmpty()) {
                return $courseMatches->first();
            }
        }

        // Ambiguous names with no usable course signal — refuse rather than guess.
        return null;
    }

    private function fuzzyByCourse(string $fullName, string $normalized, string $course): ?Student
    {
        $last = $this->probableLastName($fullName);
        if ($last === '' || strlen($last) < 3) {
            return null;
        }

        // SOUNDEX catches common letter swaps/typos without scanning the whole table.
        $pool = Student::query()
            ->whereNotNull('normalized_name')
            ->where('normalized_name', '!=', '')
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->whereRaw('SOUNDEX(lastname) = SOUNDEX(?)', [$last])
            ->get(['id', 'normalized_name', 'course', 'lastname']);

        $hits = $pool->filter(function (Student $s) use ($normalized, $course) {
            if (! PatronQrPayload::coursesCompatible($course, $s->course)) {
                return false;
            }

            $other = (string) $s->normalized_name;
            $len = max(strlen($normalized), strlen($other));
            if ($len < 8) {
                return false;
            }

            $distance = levenshtein($normalized, $other);
            $max = $len >= 14 ? 2 : 1;

            return $distance > 0 && $distance <= $max;
        });

        if ($hits->count() !== 1) {
            return null;
        }

        return Student::query()->find($hits->first()->id);
    }
}
