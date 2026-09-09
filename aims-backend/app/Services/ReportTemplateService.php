<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\ReportTemplate;
use App\Models\User;
use DOMDocument;
use DOMNode;
use Dompdf\Dompdf;
use Dompdf\Options;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ReportTemplateService
{
    public const SHARED_REPORT_TYPE = 'all_reports';

    public const REPORT_TYPES = [
        'student_internship' => 'Student Internship Report',
        'hte_deployment' => 'Host Training Establishment Report',
        'travel_monitoring' => 'Travel Monitoring Report',
        'program_summary' => 'Program Internship Summary Report',
        'hte_deployment_review' => 'HTE Deployment Review Report',
        'travel_monitoring_review' => 'Travel Monitoring Review Report',
        'overall_summary' => 'Overall Internship Summary Report',
        'hte_deployment_validation' => 'HTE Deployment Validation Report',
        'travel_monitoring_validation' => 'Travel Monitoring Validation Report',
    ];

    private const ROLE_TYPES = [
        'coordinator' => ['student_internship', 'hte_deployment', 'travel_monitoring'],
        'program_head' => ['program_summary', 'hte_deployment_review', 'travel_monitoring_review'],
        'vpaa' => ['overall_summary', 'hte_deployment_validation', 'travel_monitoring_validation'],
    ];

    public static function reportTypesForRole(string $role): array
    {
        if ($role === 'admin') {
            return self::REPORT_TYPES;
        }

        return collect(self::ROLE_TYPES[$role] ?? [])
            ->mapWithKeys(fn (string $key) => [$key => self::REPORT_TYPES[$key]])
            ->all();
    }

    public static function label(string $reportType): string
    {
        if ($reportType === self::SHARED_REPORT_TYPE) {
            return 'Official Report Template';
        }

        return self::REPORT_TYPES[$reportType] ?? Str::headline($reportType);
    }

    public function inspect(string $absolutePath, string $reportType): array
    {
        $errors = [];
        $warnings = [];
        $placeholders = [];
        $fatal = false;
        $zip = new ZipArchive;

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            $errors[] = 'The uploaded template file is empty or unreadable.';
            $fatal = true;
        } elseif ($zip->open($absolutePath) !== true) {
            $errors[] = 'The uploaded file is corrupted or is not a readable DOCX document.';
            $fatal = true;
        } else {
            $contentTypes = $zip->getFromName('[Content_Types].xml');
            $documentXml = $zip->getFromName('word/document.xml');

            if ($contentTypes === false || $documentXml === false) {
                $errors[] = 'The file does not contain the required DOCX document structure.';
                $fatal = true;
            }

            $uncompressedSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = strtolower((string) ($stat['name'] ?? ''));
                $uncompressedSize += (int) ($stat['size'] ?? 0);
                if (str_contains($name, 'vbaproject.bin') || str_starts_with($name, 'word/embeddings/')) {
                    $errors[] = 'Embedded executables, macros, and embedded package objects are not allowed.';
                    $fatal = true;
                    break;
                }
            }
            if ($uncompressedSize > 50 * 1024 * 1024) {
                $errors[] = 'The expanded DOCX content exceeds the 50 MB safety limit.';
                $fatal = true;
            }

            foreach ($this->xmlParts($zip) as $xml) {
                $placeholders = array_merge($placeholders, $this->placeholdersFromXml($xml));
            }
            $zip->close();
        }

        $placeholders = array_values(array_unique($placeholders));
        sort($placeholders);

        return [
            'is_valid' => $errors === [],
            'fatal' => $fatal,
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'required_placeholders' => [],
            'missing_required' => [],
            'detected_placeholders' => $placeholders,
            'unsupported_placeholders' => [],
        ];
    }

    public function sampleDataset(string $reportType): array
    {
        $title = self::label($reportType);
        $headers = ['School Number', 'Student', 'Program', 'HTE', 'Internship Status'];

        return [
            'title' => $title,
            'subtitle' => 'Template validation preview using sample information only',
            'scope' => 'BSIS - Bachelor of Science in Information Systems',
            'period' => 'Academic Year 2026-2027 · First Semester',
            'generated_at' => now()->timezone('Asia/Manila')->format('F j, Y g:i A'),
            'generated_by' => 'AIMS Template Preview',
            'summary' => [
                'Total students' => 2,
                'Attendance rate' => '96%',
                'Required hours' => 486,
                'Rendered hours' => 350,
                'Remaining hours' => 136,
                'Progress percentage' => '72%',
            ],
            'headers' => $headers,
            'rows' => [
                ['2026-0001', 'Juan Dela Cruz', 'BSIS', 'Sample HTE', 'Active'],
                ['2026-0002', 'Maria Santos', 'BSIS', 'Partner HTE', 'Active'],
            ],
        ];
    }

    public function renderHtml(string $absolutePath, array $bindings, array $rows, array $headers, array $summary = []): string
    {
        $stationery = $this->stationery($absolutePath);
        $header = $this->stationeryBlock('header', $stationery['header_image'], $stationery['header_text']);
        $footer = $this->stationeryBlock('footer', $stationery['footer_image'], $stationery['footer_text']);
        $wide = count($headers) > 7 ? ' wide' : '';
        $title = $this->escape($bindings['report_title'] ?? 'Internship Report');
        $subtitle = $this->escape($bindings['report_subtitle'] ?? 'Official AIMS generated report');
        $scope = $this->escape($bindings['program'] ?? 'Authorized scope');
        $period = $this->escape($bindings['report_period'] ?? 'All available records');
        $generated = $this->escape($bindings['date_generated'] ?? now()->format('F j, Y'));
        $preparedBy = $this->escape($bindings['generated_by'] ?? 'Authorized AIMS user');

        $summaryHtml = '';
        if ($summary !== []) {
            $summaryHtml = '<table class="summary"><tbody><tr>';
            $column = 0;
            foreach ($summary as $label => $value) {
                if ($column > 0 && $column % 4 === 0) {
                    $summaryHtml .= '</tr><tr>';
                }
                $summaryHtml .= '<td><span>'.$this->escape((string) $label).'</span><strong>'.$this->escape($this->stringValue($value)).'</strong></td>';
                $column++;
            }
            while ($column % 4 !== 0) {
                $summaryHtml .= '<td></td>';
                $column++;
            }
            $summaryHtml .= '</tr></tbody></table>';
        }

        $records = '<table class="records'.$wide.'"><thead><tr>';
        foreach ($headers as $headerLabel) {
            $records .= '<th>'.$this->escape((string) $headerLabel).'</th>';
        }
        $records .= '</tr></thead><tbody>';
        if ($rows === []) {
            $records .= '<tr><td class="empty" colspan="'.max(count($headers), 1).'">No records match the selected report filters.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $records .= '<tr>';
                foreach ($headers as $headerLabel) {
                    $key = Str::snake(Str::ascii((string) $headerLabel));
                    $records .= '<td>'.$this->escape($this->stringValue($row[$key] ?? '')).'</td>';
                }
                $records .= '</tr>';
            }
        }
        $records .= '</tbody></table>';

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            .'@page{margin:35mm 12mm 30mm}*{box-sizing:border-box}body{margin:0;font-family:DejaVu Sans,Arial,sans-serif;color:#111827;font-size:9px;line-height:1.4}'
            .'.letterhead{position:fixed;left:-12mm;right:-12mm;text-align:center}.letterhead.header{top:-34mm}.letterhead.footer{bottom:-29mm}.letterhead img{display:block;width:100%;height:auto}.letterhead .text{font-size:9px;white-space:pre-line}'
            .'.report-title{text-align:center;margin:0 0 3mm}.report-title h1{margin:0;color:#690016;font-size:17px;text-transform:uppercase}.report-title p{margin:1mm 0 0;color:#64748b;font-size:9px}'
            .'.metadata{width:100%;margin:0 0 4mm;border-collapse:collapse}.metadata td{width:50%;padding:1.2mm 2mm;border:1px solid #cbd5e1}.metadata b{color:#690016}'
            .'.section-label{margin:4mm 0 1.5mm;color:#690016;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}'
            .'table{border-collapse:collapse;width:100%;page-break-inside:auto}tr{page-break-inside:avoid;page-break-after:auto}.summary{table-layout:fixed;margin-bottom:3mm}.summary td{padding:2mm;border:1px solid #e2e8f0;background:#f8fafc}.summary span{display:block;color:#64748b;font-size:7px;text-transform:uppercase}.summary strong{display:block;margin-top:.7mm;color:#690016;font-size:11px}'
            .'.records th,.records td{border:1px solid #cbd5e1;padding:1.3mm;vertical-align:top;overflow-wrap:anywhere}.records th{background:#7D001D;color:#fff;font-size:7px}.records td{font-size:7px}.records.wide th,.records.wide td{padding:.9mm;font-size:5.8px}.records .empty{padding:10mm;text-align:center;color:#64748b}'
            .'@media screen{body{background:#e2e8f0;padding:16px}.report-sheet{position:relative;margin:0 auto;max-width:210mm;min-height:297mm;padding:35mm 12mm 30mm;background:#fff;box-shadow:0 8px 30px rgba(15,23,42,.18)}.letterhead{position:absolute;left:0;right:0}.letterhead.header{top:0}.letterhead.footer{bottom:0}}'
            .'</style></head><body><div class="report-sheet">'.$header.$footer
            .'<div class="report-title"><h1>'.$title.'</h1><p>'.$subtitle.'</p></div>'
            .'<table class="metadata"><tr><td><b>Scope:</b> '.$scope.'</td><td><b>Period:</b> '.$period.'</td></tr><tr><td><b>Generated:</b> '.$generated.'</td><td><b>Prepared by:</b> '.$preparedBy.'</td></tr></table>'
            .($summaryHtml !== '' ? '<div class="section-label">Report Summary</div>'.$summaryHtml : '')
            .'<div class="section-label">Report Records</div>'.$records.'</div></body></html>';
    }

    public function renderDailyJournalHtml(string $absolutePath, array $values): string
    {
        $stationery = $this->stationery($absolutePath);
        $header = $this->stationeryBlock('header', $stationery['header_image'], $stationery['header_text']);
        $footer = $this->stationeryBlock('footer', $stationery['footer_image'], $stationery['footer_text']);
        [$templateBody, $matchedFields] = $this->dailyJournalTemplateBody($absolutePath, $values);

        if (count(array_intersect($matchedFields, ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'am_accomplishments', 'pm_accomplishments'])) < 4) {
            $templateBody .= $this->dailyJournalDataTable($values);
        }

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            .'@page{size:A4;margin:30mm 14mm 25mm}*{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#172033;font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;line-height:1.5}'
            .'.sheet{position:relative;margin:16px auto;max-width:210mm;padding:30mm 14mm 25mm;background:#fff;box-shadow:0 8px 30px rgba(15,23,42,.18)}'
            .'.letterhead{position:absolute;left:0;right:0;text-align:center}.letterhead.header{top:0}.letterhead.footer{bottom:0}.letterhead img{display:block;width:100%;height:auto}.letterhead .text{font-size:9px;white-space:pre-line}'
            .'.docx-body p{min-height:1em;margin:0 0 7px;white-space:pre-wrap}.docx-body p.center{text-align:center}.docx-body p.right{text-align:right}'
            .'.docx-table{width:100%;margin:7px 0 12px;border-collapse:collapse}.docx-table td{height:28px;padding:7px;border:1px solid #334155;vertical-align:top;white-space:pre-wrap}'
            .'.auto-journal{margin-top:14px}.auto-journal h1{margin:0 0 4px;color:#690016;text-align:center;font-size:17px;text-transform:uppercase}.auto-journal .meta{margin:0 0 12px;text-align:center;color:#64748b}'
            .'.auto-journal table{width:100%;border-collapse:collapse}.auto-journal th,.auto-journal td{border:1px solid #334155;padding:8px;vertical-align:top}.auto-journal th{width:26%;background:#f8fafc;color:#690016;text-align:left;font-size:9px;text-transform:uppercase}.auto-journal .activity{min-height:75px;white-space:pre-wrap}'
            .'@media screen{html,body{height:100%;overflow:hidden}body{background:#e2e8f0;font-size:9px}.sheet{height:100vh;min-height:0;margin:0 auto;padding:23mm 10mm 6mm;overflow:hidden;box-shadow:none}.auto-journal{margin-top:8px}.auto-journal h1{font-size:14px}.auto-journal .meta{margin-bottom:7px}.auto-journal th,.auto-journal td{padding:5px}.auto-journal .activity{min-height:42px}}'
            .'@media print{body{background:#fff}.sheet{min-height:297mm;margin:0;box-shadow:none}}'
            .'</style></head><body><main class="sheet">'.$header.$footer.'<section class="docx-body">'.$templateBody.'</section></main></body></html>';
    }

    public function generatePdf(ReportTemplate $template, array $dataset, array $bindingSet, User $user): array
    {
        $html = $this->renderHtml(
            Storage::disk('local')->path($template->file_path),
            $bindingSet['values'],
            $bindingSet['rows'],
            $bindingSet['headers'],
            $bindingSet['summary'] ?? [],
        );
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', count($bindingSet['headers']) > 7 ? 'landscape' : 'portrait');
        $dompdf->render();
        $bytes = $dompdf->output();

        $filename = Str::slug($dataset['title'] ?? self::label($template->report_type)).'-'.now()->format('Ymd-His').'.pdf';
        $path = 'generated-reports/'.now()->format('Y/m').'/'.Str::uuid().'.pdf';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('The generated PDF could not be stored.');
        }

        $record = GeneratedReport::create([
            'report_template_id' => $template->id,
            'program_id' => $template->program_id,
            'report_type' => $template->report_type,
            'file_path' => $path,
            'filename' => $filename,
            'format' => 'pdf',
            'generated_by' => $user->id,
            'data_snapshot' => [
                'title' => $dataset['title'] ?? null,
                'scope' => $dataset['scope'] ?? null,
                'period' => $dataset['period'] ?? null,
                'summary' => $dataset['summary'] ?? [],
                'template_name' => $template->name,
                'template_version' => $template->version,
            ],
        ]);

        return ['record' => $record, 'bytes' => $bytes, 'filename' => $filename];
    }

    private function stationery(string $absolutePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('The DOCX template can no longer be opened.');
        }

        try {
            $header = $this->stationeryPart($zip, 'header');
            $footer = $this->stationeryPart($zip, 'footer');

            return [
                'header_image' => $header['image'],
                'header_text' => $header['text'],
                'footer_image' => $footer['image'],
                'footer_text' => $footer['text'],
            ];
        } finally {
            $zip->close();
        }
    }

    private function stationeryPart(ZipArchive $zip, string $type): array
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $partName = (string) $zip->getNameIndex($index);
            if (! preg_match('#^word/'.$type.'\d+\.xml$#', $partName)) {
                continue;
            }

            $xml = $zip->getFromIndex($index);
            if (! is_string($xml)) {
                continue;
            }

            $text = '';
            $dom = new DOMDocument;
            if (@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $text = trim($this->nodeText($dom->documentElement, $xpath));
            }

            preg_match('/\br:embed="([^"]+)"/', $xml, $match);
            $image = isset($match[1]) ? $this->relatedImage($zip, $partName, $match[1]) : null;

            return ['image' => $image, 'text' => $text];
        }

        return ['image' => null, 'text' => ''];
    }

    private function relatedImage(ZipArchive $zip, string $partName, string $relationshipId): ?string
    {
        $relsName = dirname($partName).'/_rels/'.basename($partName).'.rels';
        $relsXml = $zip->getFromName($relsName);
        if (! is_string($relsXml)) {
            return null;
        }

        $rels = new DOMDocument;
        if (! @$rels->loadXML($relsXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }

        $target = null;
        foreach ($rels->getElementsByTagName('Relationship') as $relationship) {
            if ($relationship->getAttribute('Id') === $relationshipId
                && str_ends_with($relationship->getAttribute('Type'), '/image')) {
                $target = $relationship->getAttribute('Target');
                break;
            }
        }
        if (! $target || preg_match('#^(?:[a-z]+:|//)#i', $target)) {
            return null;
        }

        $imagePath = $this->normalizeZipPath(dirname($partName).'/'.$target);
        if (! str_starts_with($imagePath, 'word/media/')) {
            return null;
        }
        $bytes = $zip->getFromName($imagePath);
        if (! is_string($bytes)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($imagePath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function normalizeZipPath(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', ltrim($path, '/'))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    private function stationeryBlock(string $type, ?string $image, string $text): string
    {
        if ($image) {
            return '<div class="letterhead '.$type.'"><img src="'.$image.'" alt=""></div>';
        }
        if ($text !== '') {
            return '<div class="letterhead '.$type.'"><div class="text">'.nl2br($this->escape($text), false).'</div></div>';
        }

        return '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_scalar($value) || $value === null ? (string) $value : json_encode($value);
    }

    private function dailyJournalTemplateBody(string $absolutePath, array $values): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('The Daily Journal DOCX template can no longer be opened.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (! is_string($xml)) {
                throw new RuntimeException('The Daily Journal template has no readable document body.');
            }
        } finally {
            $zip->close();
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('The Daily Journal template document body is invalid.');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xpath->query('//w:body')->item(0);
        if (! $body) {
            return ['', []];
        }

        $html = '';
        $matched = [];
        foreach ($body->childNodes as $block) {
            if ($block->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            if ($block->localName === 'p') {
                $text = $this->replaceDailyJournalTokens($this->nodeText($block, $xpath), $values, $matched);
                $alignment = $xpath->query('./w:pPr/w:jc', $block)->item(0)?->attributes?->getNamedItem('w:val')?->nodeValue;
                $class = in_array($alignment, ['center', 'right'], true) ? ' class="'.$alignment.'"' : '';
                $html .= '<p'.$class.'>'.($text === '' ? '&nbsp;' : nl2br($this->escape($text), false)).'</p>';
            } elseif ($block->localName === 'tbl') {
                $html .= $this->dailyJournalTableHtml($block, $xpath, $values, $matched);
            }
        }

        return [$html, array_values(array_unique($matched))];
    }

    private function dailyJournalTableHtml(DOMNode $table, DOMXPath $xpath, array $values, array &$matched): string
    {
        $html = '<table class="docx-table"><tbody>';
        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = iterator_to_array($xpath->query('./w:tc', $row));
            $cellTexts = array_map(fn (DOMNode $cell) => trim($this->nodeText($cell, $xpath)), $cells);
            $replacements = [];

            foreach ($cellTexts as $index => $text) {
                $field = $this->dailyJournalFieldForLabel($text);
                if ($field === null) {
                    continue;
                }
                $matched[] = $field;
                if (isset($cells[$index + 1]) && trim($cellTexts[$index + 1]) === '') {
                    $replacements[$index + 1] = $this->stringValue($values[$field] ?? '');
                } elseif (! str_contains($text, '{{')) {
                    $replacements[$index] = trim($text)."\n".$this->stringValue($values[$field] ?? '');
                }
            }

            $html .= '<tr>';
            foreach ($cells as $index => $cell) {
                $text = $replacements[$index] ?? $this->replaceDailyJournalTokens($cellTexts[$index], $values, $matched);
                $gridSpan = (int) ($xpath->query('./w:tcPr/w:gridSpan', $cell)->item(0)?->attributes?->getNamedItem('w:val')?->nodeValue ?? 1);
                $colspan = $gridSpan > 1 ? ' colspan="'.$gridSpan.'"' : '';
                $html .= '<td'.$colspan.'>'.($text === '' ? '&nbsp;' : nl2br($this->escape($text), false)).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function replaceDailyJournalTokens(string $text, array $values, array &$matched): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $match) use ($values, &$matched) {
            $key = strtolower($match[1]);
            if (! array_key_exists($key, $values)) {
                return $match[0];
            }
            $matched[] = $key;

            return $this->stringValue($values[$key]);
        }, $text) ?? $text;
    }

    private function dailyJournalFieldForLabel(string $label): ?string
    {
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $label) ?? ''));
        $labels = [
            'student_name' => ['student name', 'name of student', 'intern name'],
            'student_id' => ['student id', 'student number', 'school number', 'school no'],
            'date' => ['date'],
            'work_mode' => ['work mode', 'mode of work'],
            'session_type' => ['session type', 'schedule'],
            'am_time_in' => ['am time in', 'morning time in', 'am in'],
            'am_time_out' => ['am time out', 'morning time out', 'am out'],
            'pm_time_in' => ['pm time in', 'afternoon time in', 'pm in'],
            'pm_time_out' => ['pm time out', 'afternoon time out', 'pm out'],
            'am_accomplishments' => ['am accomplishments', 'am accomplishment', 'am activities', 'morning accomplishments', 'morning activities'],
            'pm_accomplishments' => ['pm accomplishments', 'pm accomplishment', 'pm activities', 'afternoon accomplishments', 'afternoon activities'],
            'total_hours' => ['total hours', 'rendered hours'],
        ];

        foreach ($labels as $field => $candidates) {
            if (in_array($normalized, $candidates, true)) {
                return $field;
            }
        }

        return null;
    }

    private function dailyJournalDataTable(array $values): string
    {
        $value = fn (string $key) => $this->escape($this->stringValue($values[$key] ?? '—'));

        return '<section class="auto-journal"><h1>Daily Accomplishment Journal</h1>'
            .'<p class="meta">'.$value('student_name').' · '.$value('student_id').' · '.$value('date').'</p>'
            .'<table><tbody>'
            .'<tr><th>Work arrangement</th><td>'.$value('work_mode').' · '.$value('session_type').'</td></tr>'
            .'<tr><th>AM Time</th><td>'.$value('am_time_in').' – '.$value('am_time_out').'</td></tr>'
            .'<tr><th>AM Accomplishments</th><td class="activity">'.nl2br($value('am_accomplishments'), false).'</td></tr>'
            .'<tr><th>PM Time</th><td>'.$value('pm_time_in').' – '.$value('pm_time_out').'</td></tr>'
            .'<tr><th>PM Accomplishments</th><td class="activity">'.nl2br($value('pm_accomplishments'), false).'</td></tr>'
            .'<tr><th>Total Hours</th><td>'.$value('total_hours').'</td></tr>'
            .'</tbody></table></section>';
    }

    private function xmlParts(ZipArchive $zip): array
    {
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) {
                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    $parts[] = $xml;
                }
            }
        }

        return $parts;
    }

    private function placeholdersFromXml(string $xml): array
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $text = '';
        foreach ($xpath->query('//w:p') as $paragraph) {
            $text .= ' '.$this->nodeText($paragraph, $xpath);
        }
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);

        return array_map('strtolower', $matches[1] ?? []);
    }

    private function nodeText(DOMNode $node, DOMXPath $xpath): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $node) as $part) {
            $text .= $part->localName === 't' ? $part->textContent : ($part->localName === 'tab' ? "\t" : "\n");
        }

        return $text;
    }
}
