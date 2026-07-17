<?php

namespace App\Support;

class SimplePdfReport
{
    public static function table(string $title, array $meta, array $headings, array $rows): string
    {
        $layout = self::layout($headings);
        $pages = self::paginate($title, $meta, $headings, $rows, $layout);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pageObjectNumbers = [];
        $contentObjectNumbers = [];

        foreach ($pages as $pageIndex => $pageLines) {
            $pageObjectNumber = count($objects) + 1;
            $contentObjectNumber = $pageObjectNumber + 1;
            $pageObjectNumbers[] = $pageObjectNumber;
            $contentObjectNumbers[] = $contentObjectNumber;

            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
            $objects[] = self::contentObject($title, $meta, $headings, $pageLines, $layout, $pageIndex + 1, count($pages), count($rows));
        }

        $kids = implode(' ', array_map(fn ($number) => "{$number} 0 R", $pageObjectNumbers));
        $objects[1] = "<< /Type /Pages /Kids [{$kids}] /Count ".count($pageObjectNumbers).' >>';

        return self::render($objects);
    }

    private static function layout(array $headings): array
    {
        $availableWidth = 786;
        $weights = collect($headings)->mapWithKeys(function (string $heading) {
            $normalized = strtolower($heading);

            return [$heading => match (true) {
                $normalized === 'no' => 0.45,
                str_contains($normalized, 'nim') => 1.05,
                str_contains($normalized, 'periode') => 0.95,
                str_contains($normalized, 'status') => 0.95,
                str_contains($normalized, 'tanggal') => 1.15,
                str_contains($normalized, 'catatan') => 1.45,
                str_contains($normalized, 'pembimbing') => 1.75,
                str_contains($normalized, 'mahasiswa') => 1.65,
                str_contains($normalized, 'tempat') => 1.35,
                default => 1,
            }];
        });

        $totalWeight = max(1, $weights->sum());

        return [
            'margin' => 28,
            'pageWidth' => 842,
            'pageHeight' => 595,
            'tableWidth' => $availableWidth,
            'widths' => $weights->values()->map(fn ($weight) => round($availableWidth * ($weight / $totalWeight), 2))->all(),
        ];
    }

    private static function paginate(string $title, array $meta, array $headings, array $rows, array $layout): array
    {
        $pages = [];
        $pageRows = [];
        $y = self::tableStartY($meta);
        $bottom = 42;

        foreach ($rows as $row) {
            $prepared = self::prepareRow($row, $headings, $layout['widths']);
            $height = $prepared['height'];

            if ($pageRows !== [] && ($y - $height) < $bottom) {
                $pages[] = $pageRows;
                $pageRows = [];
                $y = self::tableStartY([]);
            }

            $pageRows[] = $prepared;
            $y -= $height;
        }

        if ($pageRows === []) {
            $pageRows[] = self::prepareRow(array_fill(0, count($headings), ''), $headings, $layout['widths'], 'Belum ada data sesuai filter.');
        }

        $pages[] = $pageRows;

        return $pages;
    }

    private static function prepareRow(array $row, array $headings, array $widths, ?string $emptyMessage = null): array
    {
        if ($emptyMessage !== null) {
            return [
                'cells' => [self::wrap($emptyMessage, array_sum($widths) - 12, 8)],
                'height' => 28,
                'empty' => true,
            ];
        }

        $cells = [];
        $maxLines = 1;

        foreach (array_values($row) as $index => $value) {
            $wrapped = self::wrap((string) $value, ($widths[$index] ?? 70) - 10, 7.2);
            $cells[] = $wrapped;
            $maxLines = max($maxLines, count($wrapped));
        }

        return [
            'cells' => $cells,
            'height' => max(24, 11 + ($maxLines * 9)),
            'empty' => false,
        ];
    }

    private static function contentObject(string $title, array $meta, array $headings, array $rows, array $layout, int $page, int $totalPages, int $totalRows): string
    {
        $commands = [];
        $margin = $layout['margin'];
        $tableWidth = $layout['tableWidth'];
        $y = 558;

        self::text($commands, $margin, $y, $title, 17, true, [15, 23, 42]);
        self::text($commands, $margin, $y - 18, 'SI-KP Farmasi UBP', 9, false, [71, 85, 105]);

        self::text($commands, 718, 558, 'Halaman '.$page.' dari '.$totalPages, 8, false, [71, 85, 105]);

        if ($page === 1) {
            $y = self::drawMeta($commands, $meta + ['Total data' => $totalRows], $margin, 520, $tableWidth);
        } else {
            $y = 520;
        }

        $y -= 12;
        self::drawHeaderRow($commands, $headings, $layout['widths'], $margin, $y);
        $y -= 26;

        foreach ($rows as $row) {
            if ($row['empty']) {
                self::rect($commands, $margin, $y - $row['height'], $tableWidth, $row['height'], [255, 255, 255], [219, 227, 239]);
                self::cellText($commands, $row['cells'][0], $margin + 8, $y - 13, 8, false, [15, 23, 42]);
                $y -= $row['height'];
                continue;
            }

            $height = $row['height'];
            $x = $margin;
            foreach ($headings as $index => $heading) {
                $width = $layout['widths'][$index];
                self::rect($commands, $x, $y - $height, $width, $height, [255, 255, 255], [219, 227, 239]);
                self::cellText($commands, $row['cells'][$index] ?? ['-'], $x + 5, $y - 11, 7.2, false, [15, 23, 42]);
                $x += $width;
            }
            $y -= $height;
        }

        self::text($commands, $margin, 24, 'Dicetak dari SI-KP Farmasi UBP', 7.5, false, [71, 85, 105]);

        $stream = implode("\n", $commands);

        return "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
    }

    private static function tableStartY(array $meta): int
    {
        if ($meta === []) {
            return 494;
        }

        return 520 - ((int) ceil((count($meta) + 1) / 4) * 42) - 38;
    }

    private static function drawMeta(array &$commands, array $meta, int $x, int $y, int $width): int
    {
        $columns = 4;
        $gap = 6;
        $cellWidth = ($width - ($gap * ($columns - 1))) / $columns;
        $cellHeight = 34;
        $index = 0;

        foreach ($meta as $label => $value) {
            $col = $index % $columns;
            $row = intdiv($index, $columns);
            $cellX = $x + ($col * ($cellWidth + $gap));
            $cellY = $y - ($row * ($cellHeight + 6));

            self::rect($commands, $cellX, $cellY - $cellHeight, $cellWidth, $cellHeight, [255, 255, 255], [226, 232, 240]);
            self::text($commands, $cellX + 6, $cellY - 12, (string) $label, 7.5, true, [51, 65, 85]);
            self::cellText($commands, self::wrap((string) $value, $cellWidth - 12, 8), $cellX + 6, $cellY - 24, 8, false, [15, 23, 42]);
            $index++;
        }

        return $y - ((int) ceil(count($meta) / $columns) * ($cellHeight + 6));
    }

    private static function drawHeaderRow(array &$commands, array $headings, array $widths, int $x, int $y): void
    {
        foreach ($headings as $index => $heading) {
            $width = $widths[$index];
            self::rect($commands, $x, $y - 24, $width, 24, [241, 245, 249], [203, 213, 225]);
            self::cellText($commands, self::wrap((string) $heading, $width - 10, 7), $x + 5, $y - 9, 7, true, [51, 65, 85]);
            $x += $width;
        }
    }

    private static function rect(array &$commands, float $x, float $y, float $width, float $height, array $fill, array $stroke): void
    {
        $commands[] = sprintf(
            'q %.3f %.3f %.3f rg %.3f %.3f %.3f RG %.2f w %.2f %.2f %.2f %.2f re B Q',
            $fill[0] / 255,
            $fill[1] / 255,
            $fill[2] / 255,
            $stroke[0] / 255,
            $stroke[1] / 255,
            $stroke[2] / 255,
            0.6,
            $x,
            $y,
            $width,
            $height
        );
    }

    private static function text(array &$commands, float $x, float $y, string $text, float $size, bool $bold = false, array $color = [0, 0, 0]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $commands[] = sprintf(
            'BT /%s %.2f Tf %.3f %.3f %.3f rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET',
            $font,
            $size,
            $color[0] / 255,
            $color[1] / 255,
            $color[2] / 255,
            $x,
            $y,
            self::escape($text)
        );
    }

    private static function cellText(array &$commands, array $lines, float $x, float $y, float $size, bool $bold = false, array $color = [0, 0, 0]): void
    {
        foreach (array_slice($lines, 0, 4) as $line) {
            self::text($commands, $x, $y, $line, $size, $bold, $color);
            $y -= $size + 2;
        }
    }

    private static function wrap(string $text, float $width, float $fontSize): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text)) ?: '-';
        $maxChars = max(4, (int) floor($width / ($fontSize * 0.48)));
        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            if (mb_strlen($candidate) > $maxChars && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > 4) {
            $lines = array_slice($lines, 0, 4);
            $lines[3] = self::truncate($lines[3], max(4, $maxChars - 3));
        }

        return $lines ?: ['-'];
    }

    private static function render(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private static function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }
}
