@extends('layouts.app')
@section('title','Penempatan Manual - '.config('app.name'))
@section('page_title','Penempatan Manual')
@section('content')
<section class="max-w-4xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-950">Tetapkan Tempat PKPA</h2>
            <p class="mt-1 text-sm text-slate-500">Gunakan untuk mahasiswa terverifikasi yang ditetapkan langsung oleh koordinator atau admin.</p>
        </div>
        <a href="{{ route('management.place-selections.index') }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Kembali</a>
    </div>

    <form method="POST" action="{{ route('management.place-selections.manual.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-semibold text-slate-700">Mahasiswa Terverifikasi</label>
            <select name="kp_registration_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih mahasiswa</option>
                @foreach($registrations as $registration)
                    @php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($registration->student))
                    <option value="{{ $registration->id }}" @selected(old('kp_registration_id') == $registration->id)>
                        {{ $studentDisplay->label() }} - {{ $registration->period->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Hanya menampilkan mahasiswa terverifikasi, belum memilih tempat, dan belum punya penempatan aktif.</p>
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Tempat dan Kuota</label>
            <select name="kp_place_quota_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih tempat</option>
                @foreach($quotas as $quota)
                    <option value="{{ $quota->id }}" @selected(old('kp_place_quota_id') == $quota->id)>
                        {{ $quota->period->name }} - {{ $quota->place->name }} ({{ $quota->place->typeLabel() }}) - sisa {{ $quota->remainingQuota() }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Kuota tetap dihitung sebagai terisi setelah penempatan manual disimpan.</p>
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Alasan / Catatan Koordinator</label>
            <textarea name="reason" rows="4" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: Mahasiswa ditetapkan langsung ke tempat PKPA ini sesuai arahan koordinator.">{{ old('reason') }}</textarea>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Penetapan manual tidak menunggu mekanisme pilihan mandiri, tetapi tetap membutuhkan pendaftaran yang sudah terverifikasi dan kapasitas tempat yang masih tersedia.
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('management.place-selections.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</a>
            <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white" onclick="return confirm('Tetapkan tempat PKPA ini untuk mahasiswa?')">Simpan Penetapan Manual</button>
        </div>
    </form>
</section>
@endsection
