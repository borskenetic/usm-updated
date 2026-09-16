@php
    $copyFieldSelectors = collect(config('catalog.copy_unique_marc', []))
        ->map(function ($d) {
            return [
                'tag' => $d['tag'],
                'subfield' => ($d['subfield'] ?? null) === null ? '_' : $d['subfield'],
            ];
        })
        ->values()
        ->all();
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('multiple_copies');
    const panel = document.getElementById('catalogCopiesPanel');
    const marcEditor = document.getElementById('marcEditor');
    const container = document.getElementById('copy-rows-container');
    const addBtn = document.getElementById('add-copy-row-btn');
    const template = document.getElementById('copy-row-template');
    const saveBtn = document.querySelector('#addBookForm .btn-save');
    if (!toggle || !panel) return;

    const copyFieldSelectors = @json($copyFieldSelectors);

    function reindexCopyRows() {
        if (!container) return;
        container.querySelectorAll('[data-copy-row]').forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                const field = input.name.includes('[accession_no]') ? 'accession_no' : 'rfid';
                input.name = `copies[${index}][${field}]`;
            });
            const removeBtn = row.querySelector('.remove-copy-row');
            if (removeBtn) {
                removeBtn.disabled = container.querySelectorAll('[data-copy-row]').length <= 1;
            }
        });
    }

    function setMarcCopyFieldsVisible(visible) {
        if (!marcEditor) return;
        copyFieldSelectors.forEach(def => {
            const sub = def.subfield === null ? '_' : def.subfield;
            marcEditor.querySelectorAll(`.marc-field[data-tag="${def.tag}"][data-sub="${sub}"]`).forEach(el => {
                el.classList.toggle('d-none', !visible);
                el.querySelectorAll('input, textarea, select').forEach(input => {
                    input.disabled = !visible;
                });
            });
        });
    }

    function syncMultiCopyMode() {
        const on = toggle.checked;
        panel.classList.toggle('d-none', !on);
        panel.querySelectorAll('input, button.remove-copy-row, #add-copy-row-btn').forEach(el => {
            if (el.id === 'add-copy-row-btn') {
                el.disabled = !on;
            } else if (el.matches('input')) {
                el.disabled = !on;
            } else if (el.matches('.remove-copy-row')) {
                el.disabled = !on || container.querySelectorAll('[data-copy-row]').length <= 1;
            }
        });
        setMarcCopyFieldsVisible(!on);
        if (saveBtn) {
            saveBtn.textContent = on ? 'Save all copies' : 'Save book';
        }
    }

    toggle.addEventListener('change', syncMultiCopyMode);
    syncMultiCopyMode();

    addBtn?.addEventListener('click', () => {
        if (!template || !container) return;
        const index = container.querySelectorAll('[data-copy-row]').length;
        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        container.insertAdjacentHTML('beforeend', html);
        reindexCopyRows();
    });

    container?.addEventListener('click', e => {
        const btn = e.target.closest('.remove-copy-row');
        if (!btn || btn.disabled) return;
        btn.closest('[data-copy-row]')?.remove();
        reindexCopyRows();
    });

    reindexCopyRows();
});
</script>
