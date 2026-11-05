<?php

namespace App\Services;

use App\Models\Clo;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * F18 — ekspor rekap nilai, satu baris per mahasiswa:
 * - toXlsx: rekap penuh (NIM, Nama, Nama Tim, nilai CLO, Total, Grade, Status).
 * - toIgadis: format template iGadis ("Nilai Capstone SI-46-03 - SRL"): COURSEID · SUBJECTCODE · CLASS · STUDENTID ·
 *   OLDSTUDENTID · FULLNAME, lalu satu kolom per bentuk penilaian × CLO berbobot ("Proposal [CLO1] (6%)", baris ke-2
 *   "1 (100%)"), nilainya capaian 0–100, dan kolom terakhir rumus total Σ nilai × bobot. Kolom yang belum ada
 *   datanya di sistem (COURSEID, SUBJECTCODE, CLASS, OLDSTUDENTID, kode penilaian iGadis) dikosongkan.
 */
class RecapExporter
{
    public function __construct(private readonly ScoreCalculator $calculator) {}

    /**
     * @param  Collection  $results  hasil ScoreCalculator::forTeam per tim, dengan key 'team'
     */
    public function toXlsx(Collection $results, bool $partial): string
    {
        $clos = $this->calculator->clos()->pluck('code')->all();
        $header = array_merge(['NIM', 'Nama', 'Nama Tim'], $clos, ['Total', 'Grade', 'Status']);

        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->setTitle('Rekap Nilai');
        $sheet->fromArray($header, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F2EB');

        $line = 2;
        foreach ($results as $result) {
            if (! $partial && ! $result['complete']) {
                continue;
            }
            foreach ($result['rows'] as $row) {
                $values = [
                    $row['student']->nim,
                    $row['student']->name,
                    $result['team']->name,
                ];
                foreach ($clos as $code) {
                    $values[] = $result['complete'] || $row['clos'][$code]['points'] > 0 ? $row['clos'][$code]['percent'] : null;
                }
                $values[] = $result['complete'] ? $row['total'] : null;
                $values[] = $result['complete'] ? $row['grade'] : null;
                $values[] = $result['complete'] ? 'Lengkap' : 'Parsial';
                $sheet->fromArray($values, null, 'A'.$line, true);
                $sheet->setCellValueExplicit('A'.$line, (string) $row['student']->nim, DataType::TYPE_STRING);
                $line++;
            }
        }

        foreach (range(1, count($header)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $this->save($sheet);
    }

    /**
     * Ekspor format template iGadis. Bentuk penilaian yang belum lengkap (atau peer review mahasiswa yang belum lengkap)
     * dibiarkan kosong, sehingga dapat diekspor kapan saja.
     *
     * @param  Collection  $results  hasil ScoreCalculator::forTeam per tim, dengan key 'team'
     */
    public function toIgadis(Collection $results): string
    {
        $columns = $this->igadisColumns();
        $fixed = ['COURSEID', 'SUBJECTCODE', 'CLASS', 'STUDENTID', 'OLDSTUDENTID', 'FULLNAME'];
        $first = count($fixed) + 1;                       // kolom nilai pertama (G)
        $totalCol = $first + count($columns);             // kolom rumus total
        $letter = fn (int $index) => Coordinate::stringFromColumnIndex($index);

        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->setTitle('Nilai Capstone');

        // Baris 1–2: identitas digabung dua baris; kolom nilai = judul (baris 1) + "1 (100%)" (baris 2)
        foreach ($fixed as $i => $title) {
            $col = $letter($i + 1);
            $sheet->setCellValue("{$col}1", $title);
            $sheet->mergeCells("{$col}1:{$col}2");
        }
        foreach ($columns as $i => $column) {
            $col = $letter($first + $i);
            $sheet->setCellValue("{$col}1", $column['title']);
            $sheet->setCellValue("{$col}2", '1 (100%)');
        }
        $sheet->setCellValue($letter($totalCol).'2', 'NA');

        $line = 3;
        foreach ($results as $result) {
            foreach ($result['rows'] as $row) {
                $sheet->setCellValueExplicit("D{$line}", (string) $row['student']->nim, DataType::TYPE_STRING);
                $sheet->setCellValue("F{$line}", $row['student']->name);

                $terms = [];
                foreach ($columns as $i => $column) {
                    $col = $letter($first + $i);
                    $ready = $column['component'] === 'peer_review' ? $row['peer_complete'] : ! in_array($column['component'], $result['missing'], true);
                    if ($ready) {
                        $points = $row['cells'][$column['component']][$column['clo']] ?? 0;
                        $sheet->setCellValue("{$col}{$line}", round($points / $column['weight'] * 100, 2));
                    }
                    $terms[] = "{$col}{$line}*".($column['weight'] / 100);
                }
                if ($terms) {
                    $sheet->setCellValue($letter($totalCol).$line, '=('.implode('+', $terms).')');
                }
                $line++;
            }
        }

        $this->styleIgadis($sheet, $totalCol, $first, $line - 1);

        return $this->save($sheet);
    }

    /**
     * Kolom nilai iGadis: setiap pasangan bentuk penilaian × CLO yang berbobot, urut bentuk penilaian lalu kode CLO.
     *
     * @return list<array{component: string, clo: string, weight: float, title: string}>
     */
    private function igadisColumns(): array
    {
        $columns = [];
        foreach (config('capstone.components') as $component => $meta) {
            foreach ($this->calculator->clos() as $clo) {
                /** @var Clo $clo */
                $weight = $clo->weightFor($component);
                if ($weight > 0) {
                    $percent = rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
                    $columns[] = ['component' => $component, 'clo' => $clo->code, 'weight' => $weight,
                        'title' => "{$meta['label']} [{$clo->code}] ({$percent}%)"];
                }
            }
        }

        return $columns;
    }

    /** Gaya mengikuti template: tebal, rata tengah, judul membungkus, nilai berformat 0.00 dan berlatar hijau. */
    private function styleIgadis(Worksheet $sheet, int $totalCol, int $first, int $lastRow): void
    {
        $last = Coordinate::stringFromColumnIndex($totalCol);
        $lastRow = max($lastRow, 2);

        $all = $sheet->getStyle("A1:{$last}{$lastRow}");
        $all->getFont()->setBold(true)->setName('Calibri')->setSize(11);
        $all->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$last}2")->getAlignment()->setWrapText(true);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($first)."1:{$last}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        if ($lastRow >= 3 && $totalCol > $first) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($first).'3:'.Coordinate::stringFromColumnIndex($totalCol - 1).$lastRow)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFB5F2B0');
        }

        foreach (['A' => 14, 'B' => 13, 'C' => 10.57, 'D' => 14, 'E' => 8.71, 'F' => 42.43] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        foreach (range($first, $totalCol - 1) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setWidth(21.14);
        }
        $sheet->getColumnDimension($last)->setWidth(8.71);
        $sheet->freezePane(Coordinate::stringFromColumnIndex($first).'1');
    }

    private function save(Worksheet $sheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        return $path;
    }
}
