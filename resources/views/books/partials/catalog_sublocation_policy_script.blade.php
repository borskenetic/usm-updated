@php
    $sublocationPolicyMap = \App\Models\SublocationPolicy::catalogMap();
@endphp
<script>
(function () {
    const policyMap = @json($sublocationPolicyMap);
    const reservedInput = document.getElementById('reserved');
    const policySummary = document.getElementById('sublocationPolicySummary');
    let inlineHint = null;

    function findPolicy(section) {
        if (!section) return null;
        if (policyMap[section]) return policyMap[section];
        const key = Object.keys(policyMap).find(function (name) {
            return name.toLowerCase() === String(section).toLowerCase();
        });
        return key ? policyMap[key] : null;
    }

    function formatDays(value) {
        return value == null || value === '' ? 'global default' : (value + 'd');
    }

    function formatMoney(value) {
        if (value == null || value === '') return 'global default';
        return '₱' + Number(value).toFixed(2) + '/day';
    }

    function ensureInlineHint(select) {
        if (inlineHint || !select) return inlineHint;
        const wrap = select.closest('.marc-select-wrap') || select.parentElement;
        if (!wrap) return null;
        inlineHint = document.createElement('div');
        inlineHint.className = 'form-text mt-1 d-none';
        inlineHint.id = 'sublocationPolicyInlineHint';
        wrap.appendChild(inlineHint);
        return inlineHint;
    }

    function summaryHtml(policy) {
        const mode = policy.reserved
            ? '<strong>Room use only</strong> — cannot be checked out outside the library.'
            : '<strong>Circulating</strong> — normal checkout allowed.';

        return '<div class="small">' +
            '<div class="fw-semibold mb-1">Policy for ' + policy.section + '</div>' +
            '<div class="mb-1">' + mode + '</div>' +
            '<div class="text-muted">Student loan: ' + formatDays(policy.student_loan_duration_days) +
            ' · Staff loan: ' + formatDays(policy.employee_loan_duration_days) + '</div>' +
            '<div class="text-muted">Student fine: ' + formatMoney(policy.student_fine_per_day) +
            ' · Staff fine: ' + formatMoney(policy.employee_fine_per_day) + '</div>' +
            '</div>';
    }

    function renderSummary(policy, select) {
        const html = policy ? summaryHtml(policy) : '';

        if (policySummary) {
            if (!policy) {
                policySummary.classList.add('d-none');
                policySummary.innerHTML = '';
            } else {
                policySummary.innerHTML = html;
                policySummary.classList.remove('d-none');
            }
        }

        const hint = ensureInlineHint(select);
        if (hint) {
            if (!policy) {
                hint.classList.add('d-none');
                hint.innerHTML = '';
            } else {
                hint.innerHTML = html;
                hint.classList.remove('d-none');
            }
        }
    }

    function applyPolicy(section, select, { syncReserved } = { syncReserved: true }) {
        const policy = findPolicy(section);
        renderSummary(policy, select);

        if (syncReserved && reservedInput && policy) {
            reservedInput.checked = !!policy.reserved;
            reservedInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function currentSublocationSelect() {
        return document.querySelector('.marc-field[data-tag="852"][data-sub="c"] select.marc-select');
    }

    function bind() {
        const select = currentSublocationSelect();
        if (!select) return;

        select.addEventListener('change', function () {
            applyPolicy(select.value, select, { syncReserved: true });
        });

        applyPolicy(select.value, select, { syncReserved: false });
        if (select.value && reservedInput && findPolicy(select.value)) {
            const hasOldReserved = @json(old('reserved') !== null);
            if (!hasOldReserved) {
                applyPolicy(select.value, select, { syncReserved: true });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
</script>
