<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SublocationPolicy extends Model
{
    protected $fillable = [
        'section',
        'reserved',
        'student_loan_duration_days',
        'employee_loan_duration_days',
        'student_fine_per_day',
        'employee_fine_per_day',
        'student_max_fine',
        'employee_max_fine',
        'student_grace_period_days',
        'employee_grace_period_days',
        'sort_order',
    ];

    protected $casts = [
        'reserved' => 'boolean',
        'student_loan_duration_days' => 'integer',
        'employee_loan_duration_days' => 'integer',
        'student_fine_per_day' => 'float',
        'employee_fine_per_day' => 'float',
        'student_max_fine' => 'float',
        'employee_max_fine' => 'float',
        'student_grace_period_days' => 'integer',
        'employee_grace_period_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public static function findForSection(?string $section): ?self
    {
        $section = trim((string) $section);
        if ($section === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(section) = ?', [mb_strtolower($section)])
            ->first();
    }

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('section')
            ->get();
    }

    /**
     * Map for catalog JS: section name => policy payload.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogMap(): array
    {
        $map = [];
        foreach (static::ordered() as $policy) {
            $map[$policy->section] = $policy->toCatalogPayload();
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogPayload(): array
    {
        return [
            'section' => $this->section,
            'reserved' => (bool) $this->reserved,
            'student_loan_duration_days' => $this->student_loan_duration_days,
            'employee_loan_duration_days' => $this->employee_loan_duration_days,
            'student_fine_per_day' => $this->student_fine_per_day,
            'employee_fine_per_day' => $this->employee_fine_per_day,
            'student_max_fine' => $this->student_max_fine,
            'employee_max_fine' => $this->employee_max_fine,
            'student_grace_period_days' => $this->student_grace_period_days,
            'employee_grace_period_days' => $this->employee_grace_period_days,
        ];
    }

    /**
     * Merge this sublocation's overrides onto global patron terms.
     *
     * @param  object{fine_per_day: float, max_fine: ?float, grace_period_days: int, loan_duration_days: int}  $terms
     * @return object{fine_per_day: float, max_fine: ?float, grace_period_days: int, loan_duration_days: int}
     */
    public function mergePatronTerms(object $terms, bool $isEmployee): object
    {
        if ($isEmployee) {
            return (object) [
                'fine_per_day' => $this->employee_fine_per_day ?? $terms->fine_per_day,
                'max_fine' => $this->employee_max_fine ?? $terms->max_fine,
                'grace_period_days' => $this->employee_grace_period_days ?? $terms->grace_period_days,
                'loan_duration_days' => $this->employee_loan_duration_days ?? $terms->loan_duration_days,
            ];
        }

        return (object) [
            'fine_per_day' => $this->student_fine_per_day ?? $terms->fine_per_day,
            'max_fine' => $this->student_max_fine ?? $terms->max_fine,
            'grace_period_days' => $this->student_grace_period_days ?? $terms->grace_period_days,
            'loan_duration_days' => $this->student_loan_duration_days ?? $terms->loan_duration_days,
        ];
    }

    public static function resolvePatronTerms(?string $section, ?FineSetting $settings, bool $isEmployee): object
    {
        $base = $settings
            ? $settings->patronTerms($isEmployee)
            : (object) [
                'fine_per_day' => 0.0,
                'max_fine' => null,
                'grace_period_days' => 0,
                'loan_duration_days' => FineSetting::DEFAULT_LOAN_DURATION_DAYS,
            ];

        $policy = static::findForSection($section);

        return $policy ? $policy->mergePatronTerms($base, $isEmployee) : $base;
    }

    public static function syncMarcSelectOptions(): void
    {
        $sections = static::ordered()->pluck('section')->values()->all();
        if ($sections === []) {
            return;
        }

        $marc = MarcField::findForTagSubfield('852', 'c');
        if (! $marc) {
            return;
        }

        $marc->input_type = 'select';
        $marc->options = $sections;
        $marc->save();
    }
}
