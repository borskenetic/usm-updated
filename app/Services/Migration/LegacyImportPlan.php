<?php

declare(strict_types=1);

namespace App\Services\Migration;

use InvalidArgumentException;

final class LegacyImportPlan
{
    /**
     * Tables are ordered parent-first for import.
     *
     * @return list<array{source: string, target: string, required: bool, overrides?: array<string, string>}>
     */
    public function copies(): array
    {
        return [
            ['source' => 'users', 'target' => 'users', 'required' => true, 'overrides' => ['is_active' => '1']],
            ['source' => 'roles', 'target' => 'library_roles', 'required' => true],
            ['source' => 'programs', 'target' => 'library_programs', 'required' => true],
            ['source' => 'program_years', 'target' => 'library_program_years', 'required' => true],
            ['source' => 'program_courses', 'target' => 'library_program_courses', 'required' => true],
            ['source' => 'programs', 'target' => 'attendance_programs', 'required' => true],
            ['source' => 'program_years', 'target' => 'attendance_program_years', 'required' => true],
            ['source' => 'program_courses', 'target' => 'attendance_program_courses', 'required' => true],
            ['source' => 'catalog_frameworks', 'target' => 'library_catalog_frameworks', 'required' => true],
            ['source' => 'marc_fields', 'target' => 'library_marc_fields', 'required' => true],
            ['source' => 'catalog_framework_fields', 'target' => 'library_catalog_framework_fields', 'required' => true],
            ['source' => 'years', 'target' => 'years', 'required' => false],
            ['source' => 'settings', 'target' => 'settings', 'required' => true],
            ['source' => 'settings', 'target' => 'library_settings', 'required' => true],
            ['source' => 'settings', 'target' => 'library_attendance_settings', 'required' => true],
            ['source' => 'holidays', 'target' => 'library_holidays', 'required' => true, 'overrides' => ['is_active' => '1']],
            ['source' => 'fine_settings', 'target' => 'library_fine_settings', 'required' => true],
            ['source' => 'students', 'target' => 'library_students', 'required' => true],
            ['source' => 'students', 'target' => 'attendance_students', 'required' => true],
            ['source' => 'employees', 'target' => 'library_employees', 'required' => true],
            ['source' => 'employees', 'target' => 'attendance_employees', 'required' => true],
            ['source' => 'pending_students', 'target' => 'library_pending_students', 'required' => true],
            ['source' => 'pending_employees', 'target' => 'library_pending_employees', 'required' => true],
            ['source' => 'books', 'target' => 'library_books', 'required' => true],
            ['source' => 'book_marc_fields', 'target' => 'library_book_marc_fields', 'required' => true],
            ['source' => 'book_program', 'target' => 'library_book_program', 'required' => true],
            ['source' => 'book_logs', 'target' => 'library_book_logs', 'required' => true],
            ['source' => 'book_reservations', 'target' => 'library_book_reservations', 'required' => false],
            ['source' => 'ebooks', 'target' => 'library_ebooks', 'required' => true],
            ['source' => 'rooms', 'target' => 'library_rooms', 'required' => true, 'overrides' => ['is_active' => '1']],
            ['source' => 'room_reservations', 'target' => 'library_room_reservations', 'required' => true],
            ['source' => 'reservation_students', 'target' => 'library_reservation_students', 'required' => true],
            ['source' => 'reservation_logs', 'target' => 'library_reservation_logs', 'required' => true],
            ['source' => 'feedback', 'target' => 'library_feedback', 'required' => true],
            ['source' => 'files', 'target' => 'library_files', 'required' => true],
            ['source' => 'prospectuses', 'target' => 'library_prospectuses', 'required' => true],
            ['source' => 'student_edit_requests', 'target' => 'library_student_edit_requests', 'required' => true],
            ['source' => 'employee_edit_requests', 'target' => 'library_employee_edit_requests', 'required' => false],
            ['source' => 'attendance_feedback', 'target' => 'library_attendance_feedbacks', 'required' => true],
            ['source' => 'attendance_videos', 'target' => 'library_attendance_videos', 'required' => false],
            ['source' => 'zendy_logs', 'target' => 'zendy_logs', 'required' => false],
            ['source' => 'personal_access_tokens', 'target' => 'personal_access_tokens', 'required' => false],
            ['source' => 'admin_activities', 'target' => 'admin_activities', 'required' => false],
        ];
    }

    /** @return array<string, string> */
    public function roleMap(): array
    {
        return [
            'admin' => 'library_admin',
            'staff' => 'library_staff',
            'super_admin' => 'super_admin',
            'library_admin' => 'library_admin',
            'library_staff' => 'library_staff',
            'attendance_admin' => 'attendance_admin',
            'attendance_staff' => 'attendance_staff',
            'developer' => 'developer',
            'student' => 'student',
            'faculty' => 'faculty',
            'faculty/staff' => 'faculty',
            'faculty staff' => 'faculty',
            'faculty_staff' => 'faculty',
            'employee' => 'faculty',
        ];
    }

    public function canonicalRole(?string $legacyRole): ?string
    {
        $normalized = strtolower(trim((string) $legacyRole));

        if ($normalized === '') {
            return null;
        }

        return $this->roleMap()[$normalized]
            ?? throw new InvalidArgumentException("Unmapped legacy user role [{$legacyRole}].");
    }

    public function assertIdentifier(string $identifier): string
    {
        if (! preg_match('/\A[A-Za-z0-9_]+\z/', $identifier)) {
            throw new InvalidArgumentException("Unsafe database identifier [{$identifier}].");
        }

        return $identifier;
    }
}
