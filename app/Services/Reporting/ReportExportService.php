<?php

namespace App\Services\Reporting;

use App\Models\ReportExport;
use App\Models\ReportRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Provides report export service behavior within the WorkIntel application. */ class ReportExportService
{
    /** Creates and persists the requested resource. */ public function create(ReportRun $run, string $format, ?int $userId = null): ReportExport
    {
        if ($run->status !== 'completed') throw new RuntimeException('Only completed report runs can be exported.');
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) throw new RuntimeException('Unsupported report export format.');

        $export = ReportExport::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $run->workspace_id, 'report_run_id' => $run->id,
            'created_by' => $userId, 'format' => $format, 'disk' => config('workintel.reports.disk', 'local'), 'status' => 'running',
        ]);

        try {
            $safeName = Str::slug($run->name) ?: 'report';
            $filename = $safeName.'-'.$run->created_at->format('Ymd-His').'.'.$format;
            $path = 'reports/'.$run->workspace_id.'/'.$run->uuid.'/'.$filename;
            $content = match ($format) {
                'csv' => $this->csv($run),
                'xlsx' => $this->xlsx($run),
                'pdf' => $this->pdf($run),
            };
            Storage::disk($export->disk)->put($path, $content);
            $export->update([
                'path' => $path, 'filename' => $filename, 'mime_type' => $this->mime($format), 'size_bytes' => strlen($content),
                'status' => 'completed', 'completed_at' => now(), 'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $export->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 5000)]);
            throw $exception;
        }

        return $export->fresh();
    }

    /** Handles the csv operation for the current WorkIntel workflow. */ private function csv(ReportRun $run): string
    {
        $stream = fopen('php://temp', 'w+b');
        if (! $stream) throw new RuntimeException('Could not create CSV stream.');
        $columns = $run->columns ?? [];
        fputcsv($stream, array_column($columns, 'label'));
        foreach ($run->rows() as $row) fputcsv($stream, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
        rewind($stream); $content = stream_get_contents($stream); fclose($stream);
        return $content === false ? '' : "\xEF\xBB\xBF".$content;
    }

    /** Handles the xlsx operation for the current WorkIntel workflow. */ private function xlsx(ReportRun $run): string
    {
        $columns = $run->columns ?? []; $xmlRows = []; $rowNumber = 1;
        $xmlRows[] = $this->xlsxRow($rowNumber++, array_column($columns, 'label'), array_fill(0, count($columns), 'text'));
        foreach ($run->rows() as $row) {
            $values = []; $types = [];
            foreach ($columns as $column) { $values[] = $row[$column['key']] ?? ''; $types[] = $column['type'] === 'metric' ? 'number' : 'text'; }
            $xmlRows[] = $this->xlsxRow($rowNumber++, $values, $types);
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';
        return $this->zipStore([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    /**
     * Build a small STORE-only ZIP archive without requiring the optional PHP zip extension.
     * XLSX is an OpenXML ZIP container, so this keeps exports portable on shared hosts.
     */
    /** Handles the zip store operation for the current WorkIntel workflow. */ private function zipStore(array $files): string
    {
        $dataSection = ''; $central = ''; $offset = 0; $entries = 0;
        $now = getdate();
        $dosTime = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | ((int) floor($now['seconds'] / 2) & 0x1F);
        $dosDate = ((max(1980, $now['year']) - 1980) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);
        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', (string) $name); $content = (string) $content; $size = strlen($content);
            $crc = (int) sprintf('%u', crc32($content)); $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0).$name.$content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $dataSection .= $local; $offset += strlen($local); $entries++;
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries, strlen($central), strlen($dataSection), 0);
        return $dataSection.$central.$end;
    }

    /** Handles the xlsx row operation for the current WorkIntel workflow. */ private function xlsxRow(int $row, array $values, array $types): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $ref = $this->columnLetter($index + 1).$row;
            if (($types[$index] ?? 'text') === 'number' && is_numeric($value)) $cells .= '<c r="'.$ref.'"><v>'.(float) $value.'</v></c>';
            else $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
        }
        return '<row r="'.$row.'">'.$cells.'</row>';
    }

    /** Handles the pdf operation for the current WorkIntel workflow. */ private function pdf(ReportRun $run): string
    {
        $columns = $run->columns ?? [];
        $lines = [$run->name, 'Generated: '.now()->format('Y-m-d H:i:s'), str_repeat('-', 100)];
        $lines[] = implode(' | ', array_map(fn ($column) => $this->truncate((string) $column['label'], 18), $columns));
        $lines[] = str_repeat('-', 100);
        foreach ($run->rows() as $row) {
            $lines[] = implode(' | ', array_map(fn ($column) => $this->truncate((string) ($row[$column['key']] ?? ''), 18), $columns));
        }
        if (count($lines) > 1500) {
            $lines = array_slice($lines, 0, 1499);
            $lines[] = 'PDF output truncated. Use CSV or XLSX for the complete report dataset.';
        }

        $pages = array_chunk($lines, 48); $objects = []; $pageIds = []; $fontId = 3;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        foreach ($pages as $pageIndex => $pageLines) {
            $pageId = 4 + ($pageIndex * 2); $contentId = $pageId + 1; $pageIds[] = $pageId;
            $stream = "BT\n/F1 9 Tf\n36 806 Td\n";
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) $stream .= "0 -15 Td\n";
                $stream .= '('.$this->pdfEscape($line).") Tj\n";
            }
            $stream .= "ET";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds)).'] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n"; $offsets = [0];
        $maxId = max(array_keys($objects));
        for ($id = 1; $id <= $maxId; $id++) {
            $offsets[$id] = strlen($pdf); $pdf .= $id." 0 obj\n".($objects[$id] ?? '<<>>')."\nendobj\n";
        }
        $xref = strlen($pdf); $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        $pdf .= 'trailer << /Size '.($maxId + 1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    /** Handles the column letter operation for the current WorkIntel workflow. */ private function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) { $index--; $letters = chr(65 + ($index % 26)).$letters; $index = intdiv($index, 26); }
        return $letters;
    }
    /** Handles the xml operation for the current WorkIntel workflow. */ private function xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    /** Handles the pdf escape operation for the current WorkIntel workflow. */ private function pdfEscape(string $value): string { $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value); return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded !== false ? $encoded : $value); }
    /** Handles the truncate operation for the current WorkIntel workflow. */ private function truncate(string $value, int $length): string { return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value; }
    /** Handles the mime operation for the current WorkIntel workflow. */ private function mime(string $format): string { return match ($format) { 'csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf' }; }
}
