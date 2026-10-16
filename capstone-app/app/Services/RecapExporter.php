<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * F18 — ekspor rekap nilai, satu baris per mahasiswa.
 * Kolom mengikuti format pada dokumen Alur §6.6. Template resmi iGadis belum
 * dilampirkan; bila kolomnya berbeda, cukup ubah susunan $header dan $values di bawah.
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
        $header = array_merge(['NIM', 'Nama', 'Kode Kelompok', 'Prodi'], $clos, ['Total', 'Grade', 'Status']);

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
                    $result['team']->code,
                    $result['team']->prodi_code,
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

        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        return $path;
    }
}
