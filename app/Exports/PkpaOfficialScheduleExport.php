<?php

namespace App\Exports;

use App\Models\PkpaPlacementPublication;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class PkpaOfficialScheduleExport implements WithMultipleSheets
{
    public function __construct(private readonly PkpaPlacementPublication $publication)
    {
    }

    public function sheets(): array
    {
        $this->publication->loadMissing('program', 'assignments.supervisors');

        return [
            new OfficialScheduleSheet('Jadwal Mahasiswa', $this->studentRows()),
            new OfficialScheduleSheet('Matriks Enam Wahana', $this->matrixRows()),
            new OfficialScheduleSheet('Jadwal per Tempat', $this->siteRows()),
            new OfficialScheduleSheet('Jadwal PD', $this->supervisorRows('internal')),
            new OfficialScheduleSheet('Jadwal PL', $this->supervisorRows('field')),
            new OfficialScheduleSheet('Metadata', $this->metadataRows()),
        ];
    }

    private function studentRows(): array
    {
        $rows = [['Jadwal Penempatan PKPA - Dipublikasikan melalui MY PSPA'], []];
        $rows[] = ['NPM', 'Nama', 'Kelompok', 'Wahana', 'Option', 'Tempat', 'Tanggal', 'PD', 'PL', 'Publication'];
        foreach ($this->publication->assignments as $assignment) {
            $rows[] = [
                $assignment->student_number_snapshot,
                $assignment->student_name_snapshot,
                $assignment->student_group_snapshot,
                $assignment->practice_domain_name_snapshot,
                $assignment->practice_domain_option_name_snapshot,
                $assignment->practice_site_name_snapshot,
                $assignment->start_date->format('d M Y').' - '.$assignment->end_date->format('d M Y'),
                $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->name_snapshot,
                $assignment->supervisors->firstWhere('supervisor_type', 'field')?->name_snapshot,
                $this->publication->code,
            ];
        }

        return $rows;
    }

    private function matrixRows(): array
    {
        $domains = ['Apotek', 'Puskesmas', 'Pedagang Besar Farmasi', 'Rumah Sakit', 'Industri', 'Pemerintahan'];
        $rows = [['Jadwal Penempatan PKPA - Dipublikasikan melalui MY PSPA'], []];
        $rows[] = array_merge(['NPM', 'Nama'], $domains);
        foreach ($this->publication->assignments->groupBy('student_core_user_id') as $assignments) {
            $first = $assignments->first();
            $row = [$first->student_number_snapshot, $first->student_name_snapshot];
            foreach ($domains as $domain) {
                $assignment = $assignments->firstWhere('practice_domain_name_snapshot', $domain);
                $row[] = $assignment ? $assignment->practice_site_name_snapshot.' / '.$assignment->start_date->format('d M').' - '.$assignment->end_date->format('d M Y') : '-';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function siteRows(): array
    {
        $rows = [['Jadwal Penempatan PKPA - Dipublikasikan melalui MY PSPA'], []];
        $rows[] = ['Tempat', 'Mahasiswa', 'NPM', 'Tanggal', 'PD', 'PL'];
        foreach ($this->publication->assignments->sortBy('practice_site_name_snapshot') as $assignment) {
            $rows[] = [
                $assignment->practice_site_name_snapshot,
                $assignment->student_name_snapshot,
                $assignment->student_number_snapshot,
                $assignment->start_date->format('d M Y').' - '.$assignment->end_date->format('d M Y'),
                $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->name_snapshot,
                $assignment->supervisors->firstWhere('supervisor_type', 'field')?->name_snapshot,
            ];
        }

        return $rows;
    }

    private function supervisorRows(string $type): array
    {
        $rows = [['Jadwal Penempatan PKPA - Dipublikasikan melalui MY PSPA'], []];
        $rows[] = ['Pembimbing', 'Mahasiswa', 'Wahana', 'Tempat', 'Tanggal'];
        foreach ($this->publication->assignments as $assignment) {
            $supervisor = $assignment->supervisors->firstWhere('supervisor_type', $type);
            if (! $supervisor) {
                continue;
            }
            $rows[] = [
                $supervisor->name_snapshot,
                $assignment->student_name_snapshot,
                $assignment->practice_domain_name_snapshot,
                $assignment->practice_site_name_snapshot,
                $assignment->start_date->format('d M Y').' - '.$assignment->end_date->format('d M Y'),
            ];
        }

        return $rows;
    }

    private function metadataRows(): array
    {
        return [
            ['Jadwal Penempatan PKPA - Dipublikasikan melalui MY PSPA'],
            [],
            ['Program', $this->publication->program?->name],
            ['Publication', $this->publication->code],
            ['Publication number', $this->publication->publication_number],
            ['Revision', $this->publication->revision_number],
            ['Published at', optional($this->publication->published_at)->format('d M Y H:i')],
            ['Published by', $this->publication->published_by_core_user_id],
            ['Status', $this->publication->status],
        ];
    }
}

class OfficialScheduleSheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(private readonly string $title, private readonly array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
