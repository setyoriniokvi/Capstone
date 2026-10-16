<?php

namespace App\Services\Import;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/** Membaca CSV/XLSX menjadi array baris dengan header snake_case. */
class SpreadsheetReader
{
    /**
     * @return list<array{line: int, data: array<string, string>}>
     */
    public function read(string $path, string $extension): array
    {
        $reader = IOFactory::createReader(strtolower($extension) === 'csv' ? 'Csv' : 'Xlsx');
        if ($reader instanceof Csv) {
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter($this->guessDelimiter($path));
        }
        $reader->setReadDataOnly(true);

        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($h) => Str::snake(trim((string) $h)), array_shift($rows) ?? []);

        $result = [];
        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $data = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $data[$key] = trim((string) ($row[$i] ?? ''));
                }
            }
            $result[] = ['line' => $index + 2, 'data' => $data];
        }

        return $result;
    }

    /** @param list<string> $required */
    public function missingColumns(array $rows, array $required): array
    {
        $present = array_keys($rows[0]['data'] ?? []);

        return array_values(array_diff($required, $present));
    }

    private function guessDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r'));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
