<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class KpTableReportExport implements FromArray, ShouldAutoSize, WithEvents
{
    private int $headingRow;

    private int $lastColumnIndex;

    public function __construct(
        private readonly string $title,
        private readonly array $meta,
        private readonly Collection $rows,
    ) {
        $this->headingRow = 5 + (int) ceil((count($this->meta) + 1) / 2);
        $this->lastColumnIndex = max(1, count($this->headings()));
    }

    public function array(): array
    {
        $sheet = [
            [$this->title],
            ['SI-KP Farmasi UBP'],
            [],
        ];

        $meta = $this->meta + ['Total data' => $this->rows->count()];
        foreach (array_chunk($meta, 2, true) as $chunk) {
            $row = [];
            foreach ($chunk as $label => $value) {
                $row[] = $label;
                $row[] = $value;
            }
            $sheet[] = $row;
        }

        $sheet[] = [];
        $sheet[] = $this->headings();

        foreach ($this->rows as $row) {
            $sheet[] = array_values($row);
        }

        if ($this->rows->isEmpty()) {
            $sheet[] = ['Belum ada data sesuai filter.'];
        }

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->columnName($this->lastColumnIndex);
                $lastRow = $sheet->getHighestRow();
                $headingRange = "A{$this->headingRow}:{$lastColumn}{$this->headingRow}";
                $tableRange = "A{$this->headingRow}:{$lastColumn}{$lastRow}";

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setSize(11)->getColor()->setRGB('475569');

                $sheet->getStyle("A4:{$lastColumn}".($this->headingRow - 2))->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);

                $sheet->getStyle($headingRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '334155']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE3EF']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);

                $sheet->freezePane('A'.($this->headingRow + 1));
                $sheet->setAutoFilter($headingRange);
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()
                    ->setTop(0.35)
                    ->setRight(0.25)
                    ->setBottom(0.35)
                    ->setLeft(0.25);

                for ($row = $this->headingRow + 1; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }
            },
        ];
    }

    private function headings(): array
    {
        return array_keys($this->rows->first() ?? [
            'No' => '',
            'Mahasiswa' => '',
            'NIM' => '',
            'Periode' => '',
            'Tempat KP' => '',
            'Pembimbing Dalam' => '',
            'Preseptor' => '',
            'Status' => '',
        ]);
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
