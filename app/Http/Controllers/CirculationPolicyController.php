<?php

namespace App\Http\Controllers;

use App\Models\FineSetting;
use App\Models\Setting;
use App\Models\AdminActivity;
use App\Models\SublocationPolicy;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CirculationPolicyController extends Controller
{
    public function edit()
    {
        $fineSettings = FineSetting::latest('created_at')->first();
        $termsBase = $fineSettings ?? new FineSetting();
        $studentTerms = $termsBase->patronTerms(false);
        $employeeTerms = $termsBase->patronTerms(true);

        return view('admin.circulation_policy', [
            'studentMax' => Setting::maxLoansForStudents(),
            'employeeUnlimited' => Setting::employeeLoansUnlimited(),
            'employeeMax' => Setting::maxLoansForEmployees(),
            'maxRenewals' => Setting::maxRenewalsPerLoan(),
            'reborrowCooldownDays' => Setting::reborrowCooldownDays(),
            'reservationHoldDays' => Setting::reservationHoldDays(),
            'fineSettings' => $fineSettings,
            'studentTerms' => $studentTerms,
            'employeeTerms' => $employeeTerms,
            'sublocationPolicies' => SublocationPolicy::ordered(),
        ]);
    }

    public function update(Request $request)
    {
        $employeeUnlimited = $request->input('employee_unlimited') === '1';

        $request->validate([
            'student_max' => 'required|integer|min:1|max:100',
            'employee_unlimited' => 'required|in:0,1',
            'employee_max' => $employeeUnlimited ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100',
            'max_renewals' => 'required|integer|min:0|max:50',
            'reborrow_cooldown_days' => 'required|integer|min:0|max:365',
            'reservation_hold_days' => 'required|integer|min:1|max:365',
            'student_fine_per_day' => 'required|numeric|min:0',
            'student_max_fine' => 'nullable|numeric|min:0',
            'student_grace_period_days' => 'required|integer|min:0',
            'student_loan_duration_days' => 'required|integer|min:1|max:365',
            'employee_fine_per_day' => 'required|numeric|min:0',
            'employee_max_fine' => 'nullable|numeric|min:0',
            'employee_grace_period_days' => 'required|integer|min:0',
            'employee_loan_duration_days' => 'required|integer|min:1|max:365',
            'sublocations' => 'nullable|array',
            'sublocations.*.section' => 'nullable|string|max:100',
            'sublocations.*.reserved' => 'nullable|in:0,1',
            'sublocations.*.student_loan_duration_days' => 'nullable|integer|min:1|max:365',
            'sublocations.*.employee_loan_duration_days' => 'nullable|integer|min:1|max:365',
            'sublocations.*.student_fine_per_day' => 'nullable|numeric|min:0',
            'sublocations.*.employee_fine_per_day' => 'nullable|numeric|min:0',
            'sublocations.*.student_max_fine' => 'nullable|numeric|min:0',
            'sublocations.*.employee_max_fine' => 'nullable|numeric|min:0',
            'sublocations.*.student_grace_period_days' => 'nullable|integer|min:0|max:365',
            'sublocations.*.employee_grace_period_days' => 'nullable|integer|min:0|max:365',
            'sublocations.*.sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        DB::transaction(function () use ($request, $employeeUnlimited) {
            Setting::setBorrowLimits(
                (int) $request->input('student_max'),
                $employeeUnlimited,
                (int) ($request->input('employee_max') ?: Setting::DEFAULT_MAX_LOANS_EMPLOYEES)
            );

            Setting::setLoanPolicies(
                (int) $request->input('max_renewals'),
                (int) $request->input('reborrow_cooldown_days')
            );

            Setting::setReservationHoldDays((int) $request->input('reservation_hold_days'));

            FineSetting::create([
                'fine_per_day' => $request->student_fine_per_day,
                'max_fine' => $request->student_max_fine,
                'grace_period_days' => $request->student_grace_period_days,
                'loan_duration_days' => $request->student_loan_duration_days,
                'student_fine_per_day' => $request->student_fine_per_day,
                'student_max_fine' => $request->student_max_fine,
                'student_grace_period_days' => $request->student_grace_period_days,
                'student_loan_duration_days' => $request->student_loan_duration_days,
                'employee_fine_per_day' => $request->employee_fine_per_day,
                'employee_max_fine' => $request->employee_max_fine,
                'employee_grace_period_days' => $request->employee_grace_period_days,
                'employee_loan_duration_days' => $request->employee_loan_duration_days,
                'effective_from' => now(),
            ]);

            $this->syncSublocationPolicies($request->input('sublocations', []));
        });

        AdminActivityLogger::staff(
            AdminActivity::TYPE_SETTINGS,
            'Circulation policy updated',
            'Borrow limits, renewals, fine settings, and sublocation policies changed',
            route('circulation.policy.edit'),
            'circulation',
        );

        return redirect()
            ->route('circulation.policy.edit', [], 303)
            ->with('success', 'Circulation policy updated successfully.')
            ->withFragment('sublocations-pane');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncSublocationPolicies(array $rows): void
    {
        $keptIds = [];
        $seenSections = [];

        foreach (array_values($rows) as $index => $row) {
            $section = trim((string) ($row['section'] ?? ''));
            if ($section === '') {
                continue;
            }

            $sectionKey = mb_strtolower($section);
            if (isset($seenSections[$sectionKey])) {
                continue;
            }
            $seenSections[$sectionKey] = true;

            $payload = [
                'section' => $section,
                'reserved' => ($row['reserved'] ?? '0') === '1',
                'student_loan_duration_days' => $this->nullableInt($row['student_loan_duration_days'] ?? null),
                'employee_loan_duration_days' => $this->nullableInt($row['employee_loan_duration_days'] ?? null),
                'student_fine_per_day' => $this->nullableFloat($row['student_fine_per_day'] ?? null),
                'employee_fine_per_day' => $this->nullableFloat($row['employee_fine_per_day'] ?? null),
                'student_max_fine' => $this->nullableFloat($row['student_max_fine'] ?? null),
                'employee_max_fine' => $this->nullableFloat($row['employee_max_fine'] ?? null),
                'student_grace_period_days' => $this->nullableInt($row['student_grace_period_days'] ?? null),
                'employee_grace_period_days' => $this->nullableInt($row['employee_grace_period_days'] ?? null),
                'sort_order' => $this->nullableInt($row['sort_order'] ?? null) ?? (($index + 1) * 10),
            ];

            $existingId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            if ($existingId) {
                $policy = SublocationPolicy::query()->find($existingId);
                if ($policy) {
                    $policy->fill($payload)->save();
                    $keptIds[] = $policy->id;
                    continue;
                }
            }

            $policy = SublocationPolicy::query()
                ->whereRaw('LOWER(section) = ?', [$sectionKey])
                ->first();

            if ($policy) {
                $policy->fill($payload)->save();
            } else {
                $policy = SublocationPolicy::create($payload);
            }

            $keptIds[] = $policy->id;
        }

        if ($keptIds !== []) {
            SublocationPolicy::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            SublocationPolicy::query()->delete();
        }

        SublocationPolicy::syncMarcSelectOptions();
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
