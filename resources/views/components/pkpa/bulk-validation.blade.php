@props(['action', 'label' => 'Validasi Terpilih'])
<form id="bulk-validation" method="POST" action="{{ $action }}" class="sticky top-36 z-10 flex flex-wrap items-center gap-3 rounded-lg border border-cyan-200 bg-white p-4 shadow-sm md:top-24">
    @csrf
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" id="bulk-select-all"> Pilih semua di halaman ini</label>
    <span id="bulk-count" aria-live="polite" class="text-sm text-slate-500">0 dipilih</span>
    <input name="comments" maxlength="1000" aria-label="Catatan bersama" placeholder="Catatan bersama (opsional)" class="min-w-[180px] flex-1 rounded-lg border-slate-200 text-sm">
    <button id="bulk-submit" disabled class="min-h-11 rounded-lg bg-cyan-700 px-4 text-sm font-bold text-white disabled:opacity-40">{{ $label }}</button>
</form>
@push('scripts')
<script>
(() => {
    const form = document.getElementById('bulk-validation');
    const boxes = [...document.querySelectorAll('input[name="ids[]"][form="bulk-validation"]')];
    const all = document.getElementById('bulk-select-all');
    const button = document.getElementById('bulk-submit');
    const update = () => {
        const count = boxes.filter(box => box.checked).length;
        document.getElementById('bulk-count').textContent = count + ' dipilih';
        button.disabled = count === 0;
        all.checked = count > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    };
    all.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); update(); });
    boxes.forEach(box => box.addEventListener('change', update));
    form.addEventListener('submit', event => {
        const count = boxes.filter(box => box.checked).length;
        if (!count || !confirm('Setujui dan kunci ' + count + ' item terpilih?')) event.preventDefault();
        else { button.disabled = true; button.textContent = 'Menyimpan...'; }
    });
})();
</script>
@endpush
