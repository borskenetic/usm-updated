@php
    $index = $index ?? 0;
    $policy = $policy ?? null;
    $prefix = "sublocations[{$index}]";
@endphp
<tr class="sublocation-policy-row">
    <td>
        <input type="hidden" name="{{ $prefix }}[id]" value="{{ $policy->id ?? '' }}">
        <input type="hidden" name="{{ $prefix }}[sort_order]" value="{{ old($prefix.'.sort_order', $policy->sort_order ?? (($index + 1) * 10)) }}">
        <input type="text" name="{{ $prefix }}[section]" class="form-control form-control-sm"
               value="{{ old($prefix.'.section', $policy->section ?? '') }}"
               placeholder="e.g. Circulation">
    </td>
    <td>
        <input type="hidden" name="{{ $prefix }}[reserved]" value="0">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   name="{{ $prefix }}[reserved]" value="1"
                   {{ old($prefix.'.reserved', ($policy->reserved ?? false) ? '1' : '0') === '1' ? 'checked' : '' }}>
        </div>
    </td>
    <td>
        <input type="number" name="{{ $prefix }}[student_loan_duration_days]" class="form-control form-control-sm"
               min="1" max="365" placeholder="Global"
               value="{{ old($prefix.'.student_loan_duration_days', $policy->student_loan_duration_days ?? '') }}">
    </td>
    <td>
        <input type="number" name="{{ $prefix }}[employee_loan_duration_days]" class="form-control form-control-sm"
               min="1" max="365" placeholder="Global"
               value="{{ old($prefix.'.employee_loan_duration_days', $policy->employee_loan_duration_days ?? '') }}">
    </td>
    <td>
        <input type="number" step="0.01" name="{{ $prefix }}[student_fine_per_day]" class="form-control form-control-sm"
               min="0" placeholder="Global"
               value="{{ old($prefix.'.student_fine_per_day', $policy->student_fine_per_day ?? '') }}">
    </td>
    <td>
        <input type="number" step="0.01" name="{{ $prefix }}[employee_fine_per_day]" class="form-control form-control-sm"
               min="0" placeholder="Global"
               value="{{ old($prefix.'.employee_fine_per_day', $policy->employee_fine_per_day ?? '') }}">
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-outline-danger btn-sm remove-sublocation-row" title="Remove">&times;</button>
        <input type="hidden" name="{{ $prefix }}[student_max_fine]"
               value="{{ old($prefix.'.student_max_fine', $policy->student_max_fine ?? '') }}">
        <input type="hidden" name="{{ $prefix }}[employee_max_fine]"
               value="{{ old($prefix.'.employee_max_fine', $policy->employee_max_fine ?? '') }}">
        <input type="hidden" name="{{ $prefix }}[student_grace_period_days]"
               value="{{ old($prefix.'.student_grace_period_days', $policy->student_grace_period_days ?? '') }}">
        <input type="hidden" name="{{ $prefix }}[employee_grace_period_days]"
               value="{{ old($prefix.'.employee_grace_period_days', $policy->employee_grace_period_days ?? '') }}">
    </td>
</tr>
