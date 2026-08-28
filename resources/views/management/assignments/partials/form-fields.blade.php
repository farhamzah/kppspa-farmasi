@if(! $assignment)
<div>
    <label class="text-sm font-semibold">Selection Aktif</label>
    <select name="kp_place_selection_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Pilih mahasiswa yang sudah memilih tempat</option>
        @foreach($selections as $selection)
            @php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($selection->student))
            <option value="{{ $selection->id }}" @selected(old('kp_place_selection_id') == $selection->id)>
                {{ $studentDisplay->label() }} - {{ $selection->place->name }} - {{ $selection->period->name }}
            </option>
        @endforeach
    </select>
</div>
@endif
<div>
    <label class="text-sm font-semibold">Pembimbing Dalam</label>
    <select name="internal_supervisor_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Belum ditentukan</option>
        @foreach($lecturers as $lecturer)
            @php($lecturerDisplay = app(\App\Services\KpMasterDataReadService::class)->getLecturerDisplayData($lecturer))
            <option value="{{ $lecturer->id }}" @selected(old('internal_supervisor_id',$assignment?->internal_supervisor_id) == $lecturer->id)>
                {{ $lecturerDisplay->label() }}
            </option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-semibold">Preseptor</label>
    <select name="field_supervisor_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Belum ditentukan</option>
        @foreach($fieldSupervisors as $supervisor)
            <option value="{{ $supervisor->id }}" @selected(old('field_supervisor_id',$assignment?->field_supervisor_id) == $supervisor->id)>{{ field_supervisor_display_label($supervisor) }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="text-sm font-semibold">Catatan</label>
    <textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('note',$assignment?->note) }}</textarea>
</div>
