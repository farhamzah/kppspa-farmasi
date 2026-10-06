@props(['action', 'label' => 'Validasi Terpilih'])
<form id="bulk-validation" method="POST" action="{{ $action }}" class="sticky top-36 z-10 grid gap-3 rounded-lg border border-cyan-200 bg-white p-4 shadow-sm md:top-24 lg:grid-cols-[auto_auto_minmax(180px,1fr)_auto] lg:items-center">
    @csrf
    <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold"><input type="checkbox" id="bulk-select-all" class="h-5 w-5 accent-cyan-700"> Pilih semua di halaman ini</label>
    <span id="bulk-count" aria-live="polite" class="text-sm font-semibold tabular-nums text-slate-600">0 dipilih</span>
    <input name="comments" maxlength="1000" aria-label="Catatan bersama" placeholder="Catatan bersama (opsional)" class="h-11 w-full min-w-0 border border-slate-300 bg-white px-3 text-sm">
    <button id="bulk-submit" disabled class="min-h-11 rounded-lg bg-cyan-700 px-4 text-sm font-bold text-white hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">{{ $label }}</button>
</form>
@push('scripts')
<script>
(() => {
    const form = document.getElementById('bulk-validation');
    const boxes = [...document.querySelectorAll('input[name="ids[]"][form="bulk-validation"]')];
    const all = document.getElementById('bulk-select-all');
    const button = document.getElementById('bulk-submit');
    const groups = [...document.querySelectorAll('[data-bulk-group-select]')];
    const update = () => {
        const count = boxes.filter(box => box.checked).length;
        document.getElementById('bulk-count').textContent = count + ' dipilih';
        button.disabled = count === 0;
        all.checked = count > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
        all.disabled = boxes.length === 0;
        groups.forEach(group => {
            const members = boxes.filter(box => box.dataset.bulkGroup === group.dataset.bulkGroupSelect);
            const selected = members.filter(box => box.checked).length;
            group.checked = members.length > 0 && selected === members.length;
            group.indeterminate = selected > 0 && selected < members.length;
        });
    };
    all.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); update(); });
    boxes.forEach(box => box.addEventListener('change', update));
    groups.forEach(group => group.addEventListener('change', () => {
        boxes.filter(box => box.dataset.bulkGroup === group.dataset.bulkGroupSelect).forEach(box => box.checked = group.checked);
        update();
    }));
    update();
    form.addEventListener('submit', event => {
        const count = boxes.filter(box => box.checked).length;
        if (!count || !confirm('Setujui dan kunci ' + count + ' item terpilih?')) event.preventDefault();
        else { button.disabled = true; button.textContent = 'Menyimpan...'; }
    });
})();
</script>
@endpush
