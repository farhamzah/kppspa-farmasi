@extends('layouts.app')
@section('title','Mahasiswa Bimbingan - '.config('app.name'))
@section('page_title','Mahasiswa Bimbingan')
@section('content')
<section class="si-table-wrap">
    <table class="si-data-table">
        <colgroup>
            <col class="w-[24%]">
            <col class="w-[18%]">
            <col class="w-[20%]">
            <col class="w-[22%]">
            <col class="w-[10%]">
            <col class="w-[6%]">
        </colgroup>
        <thead>
            <tr>
                <th>Mahasiswa</th>
                <th>Periode</th>
                <th>Tempat</th>
                <th>Preseptor</th>
                <th class="text-center">Status</th>
                <th class="text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $assignment)
                @php($field = $assignment->supervisors->firstWhere('supervisor_type', 'field'))
                <tr>
                    <td>
                        <div class="font-semibold text-slate-950">{{ $assignment->student_name_snapshot }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ $assignment->student_number_snapshot ?: '-' }}</div>
                    </td>
                    <td class="whitespace-nowrap">{{ $assignment->start_date?->format('d M Y') }} - {{ $assignment->end_date?->format('d M Y') }}</td>
                    <td>{{ $assignment->practice_site_name_snapshot }}</td>
                    <td>{{ $field?->display_name ?: '-' }}</td>
                    <td class="text-center">
                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">
                            Aktif di portal
                        </span>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('internal-supervisor.pkpa-students.show', $assignment) }}" class="si-table-action">
                            Detail
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                        Belum ada mahasiswa bimbingan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="border-t border-slate-100 px-4 py-3">
        {{ $assignments->links() }}
    </div>
</section>
@endsection
