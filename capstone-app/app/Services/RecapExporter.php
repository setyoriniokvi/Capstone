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
 * - toIgadis: format template iGadis (sheet "Indikator Draft -> Igadis"): COURSEID · SUBJECTCODE · CLASS · STUDENTID ·
 *   OLDSTUDENTID · FULLNAME (CLASS = kelas mahasiswa; digabung 4 baris), lalu satu kolom per bentuk penilaian × CLO
 *   berbobot dengan judul 4 baris: bentuk penilaian (digabung) · CLO capstone · CLO iGadis (PLOxx-CLOyy) · bobot (%).
 *   Isi = nilai 0–100 (Σ nilai indikator ÷ bobot kolom × 100), dikali skala Penyesuaian Nilai bila ada; kolom terakhir
 *   NA = rumus Σ nilai × bobot (baris bobot = 100). Kolom tanpa data di sistem (COURSEID, SUBJECTCODE, OLDSTUDENTID) dikosongkan.
 */
class RecapExporter
{
    public function __construct(private readonly ScoreCalculator $calculator) {}

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
        $totalCol = $first + count($columns);             // kolom NA (rumus total)
        $letter = fn (int $index) => Coordinate::stringFromColumnIndex($index);
        $head = self::IGADIS_HEADER_ROWS;

        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->setTitle('Nilai Capstone');

        // Baris 1–4: identitas digabung 4 baris; kolom nilai = bentuk penilaian (digabung) · CLO · CLO iGadis · bobot
        foreach ($fixed as $i => $title) {
            $col = $letter($i + 1);
            $sheet->setCellValue("{$col}1", $title);
            $sheet->mergeCells("{$col}1:{$col}{$head}");
        }
        $start = $first;
        foreach (collect($columns)->groupBy('component') as $group) {
            $sheet->setCellValue($letter($start).'1', $group->first()['label']);
            if ($group->count() > 1) {
                $sheet->mergeCells($letter($start).'1:'.$letter($start + $group->count() - 1).'1');
            }
            $start += $group->count();
        }
        foreach ($columns as $i => $column) {
            $col = $letter($first + $i);
            $sheet->setCellValue("{$col}2", $column['clo']);
            $sheet->setCellValue("{$col}3", $column['igadis'] ?? '');
            $sheet->setCellValue("{$col}{$head}", $column['weight']);
        }
        $na = $letter($totalCol);
        $sheet->setCellValue("{$na}1", 'NA');
        $sheet->mergeCells("{$na}1:{$na}".($head - 1));
        $sheet->setCellValue("{$na}{$head}", array_sum(array_column($columns, 'weight')));

        $line = $head + 1;
        foreach ($results as $result) {
            foreach ($result['rows'] as $row) {
                $sheet->setCellValue("C{$line}", $row['student']->class_name);
                $sheet->setCellValueExplicit("D{$line}", (string) $row['student']->nim, DataType::TYPE_STRING);
                $sheet->setCellValue("F{$line}", $row['student']->name);

                $terms = [];
                foreach ($columns as $i => $column) {
                    $col = $letter($first + $i);
                    $value = $this->cellValue($result, $row, $column);
                    if ($value !== null) {
                        $sheet->setCellValue("{$col}{$line}", $value['value']);
                    }
                    $terms[] = "{$col}{$line}*".($column['weight'] / 100);
                }
                if ($terms) {
                    $sheet->setCellValue("{$na}{$line}", '=('.implode('+', $terms).')');
                }
                $line++;
            }
        }

        $this->styleIgadis($sheet, $totalCol, $first, $line - 1);

        return $this->save($sheet);
    }

    /** Baris judul ekspor iGadis: bentuk penilaian · CLO capstone · CLO iGadis · bobot; data mulai baris berikutnya. */
    public const IGADIS_HEADER_ROWS = 4;

    /**
     * Nilai satu kolom iGadis (0–100) untuk satu mahasiswa, atau null bila bentuk penilaian itu belum lengkap
     * (peer review: belum lengkap untuk mahasiswa ini). Penyesuaian Nilai → dikali skala (nilai disesuaikan ÷ sistem).
     *
     * @return array{value: float, adjusted: bool}|null
     */
    public function cellValue(array $result, array $row, array $column): ?array
    {
        $ready = $column['component'] === 'peer_review' ? $row['peer_complete'] : ! in_array($column['component'], $result['missing'], true);
        if (! $ready || $column['weight'] <= 0) {
            return null;
        }
        $points = $row['cells'][$column['component']][$column['clo']] ?? 0;
        $scale = $row['scale'] ?? 1.0;

        return ['value' => round($points / $column['weight'] * 100 * $scale, 2), 'adjusted' => abs($scale - 1) > 0.00001];
    }

    /**
     * Kolom nilai iGadis: setiap pasangan bentuk penilaian × CLO yang berbobot, urut bentuk penilaian lalu kode CLO
     * (dipakai ekspor iGadis dan tabel Rekap Penilaian).
     *
     * @return list<array{component: string, label: string, clo: string, igadis: ?string, weight: float, title: string}>
     */
    public function igadisColumns(): array
    {
        $columns = [];
        foreach (config('capstone.components') as $component => $meta) {
            foreach ($this->calculator->clos() as $clo) {
                /** @var Clo $clo */
                $weight = $clo->weightFor($component);
                if ($weight > 0) {
                    $percent = rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
                    $columns[] = ['component' => $component, 'label' => $meta['label'], 'clo' => $clo->code, 'igadis' => $clo->igadis_code,
                        'weight' => $weight, 'title' => "{$meta['label']} [{$clo->code}".($clo->igadis_code ? " · {$clo->igadis_code}" : '')."] ({$percent}%)"];
                }
            }
        }

        return $columns;
    }

    /** Gaya mengikuti sheet: tebal, rata tengah, judul hijau bertulisan putih, baris bobot kuning muda, nilai 0.00 berlatar hijau muda. */
    private function styleIgadis(Worksheet $sheet, int $totalCol, int $first, int $lastRow): void
    {
        $head = self::IGADIS_HEADER_ROWS;
        $last = Coordinate::stringFromColumnIndex($totalCol);
        $firstLetter = Coordinate::stringFromColumnIndex($first);
        $lastRow = max($lastRow, $head);

        $all = $sheet->getStyle("A1:{$last}{$lastRow}");
        $all->getFont()->setBold(true)->setName('Calibri')->setSize(11);
        $all->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$last}{$head}")->getAlignment()->setWrapText(true);
        $titles = $sheet->getStyle("{$firstLetter}1:{$last}".($head - 1));
        $titles->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF458452');
        $titles->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("{$firstLetter}3:{$last}3")->getFont()->setSize(9);
        $sheet->getStyle("{$firstLetter}{$head}:{$last}{$head}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
        $sheet->getStyle("{$firstLetter}".($head + 1).":{$last}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        if ($lastRow > $head && $totalCol > $first) {
            $sheet->getStyle("{$firstLetter}".($head + 1).':'.Coordinate::stringFromColumnIndex($totalCol - 1).$lastRow)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFB5F2B0');
        }

        foreach (['A' => 14, 'B' => 13, 'C' => 10.57, 'D' => 14, 'E' => 8.71, 'F' => 42.43] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        foreach (range($first, $totalCol - 1) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setWidth(13.5);
        }
        $sheet->getColumnDimension($last)->setWidth(9);
        $sheet->freezePane($firstLetter.($head + 1));
    }

    private function save(Worksheet $sheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        return $path;
    }
}
