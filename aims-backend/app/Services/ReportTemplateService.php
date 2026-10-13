<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\ReportTemplate;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use Dompdf\Dompdf;
use Dompdf\Options;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
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
        $stationery = $this->stationeryLayout($absolutePath);
        $header = $this->reportHeaderBlock($stationery['header_paragraphs'], $stationery['header_images']);
        $footer = $this->dailyJournalStationeryBlock('footer', $stationery['footer_paragraphs'], $stationery['footer_images']);
        $wide = count($headers) > 7 ? ' wide' : '';
        $title = $this->escape($bindings['report_title'] ?? 'Internship Report');
        $subtitle = $this->escape($bindings['report_subtitle'] ?? 'Official AIMS generated report');
        $scope = $this->escape($bindings['program'] ?? 'Authorized scope');
        $period = $this->escape($bindings['report_period'] ?? 'All available records');
        $generated = $this->escape($bindings['date_generated'] ?? now()->format('F j, Y'));
        $preparedBy = $this->escape($bindings['generated_by'] ?? 'Authorized AIMS user');

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
            .'.letterhead{position:fixed;left:0;right:0;overflow-wrap:anywhere}.letterhead.header{top:-31mm;border-bottom:1px solid #555;padding-bottom:2mm}.letterhead.footer{bottom:-27mm;border-top:1px solid #555;padding-top:2mm}.report-letterhead-main{width:100%;table-layout:fixed;border-collapse:collapse}.report-letterhead-main td{padding:0;border:0;vertical-align:top}.report-letterhead-main .logos-left{width:24%;text-align:center}.report-letterhead-main .header-copy{width:56%}.report-letterhead-main .logos-right{width:20%;text-align:center}.report-letterhead-main .logos-left img{display:inline-block;width:29%;max-height:12mm;height:auto;vertical-align:top}.report-letterhead-main .logos-right img{display:inline-block;width:43%;max-height:12mm;height:auto;vertical-align:top}.header-copy p,.footer-copy p{margin:0}.header-copy p{font-size:6px;line-height:1.08}.header-copy p:nth-child(2){color:#b0444e;font-weight:700;font-size:8px}.header-copy p:nth-child(3){color:#558568;font-weight:700;font-size:7px}.header-copy p:nth-child(n+4){font-size:5px}.footer-copy{font-size:6px;line-height:1.15;padding:0 4mm}.footer-copy p:last-child{text-align:center;font-style:italic}.single-logo{display:block;max-width:16mm;max-height:16mm;margin:0 auto}'
            .'.report-title{text-align:center;margin:0 0 3mm}.report-title h1{margin:0;color:#690016;font-size:17px;text-transform:uppercase}.report-title p{margin:1mm 0 0;color:#64748b;font-size:9px}'
            .'.metadata{width:100%;margin:0 0 4mm;border-collapse:collapse}.metadata td{width:50%;padding:1.2mm 2mm;border:1px solid #cbd5e1}.metadata b{color:#690016}'
            .'.section-label{margin:4mm 0 1.5mm;color:#690016;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}'
            .'table{border-collapse:collapse;width:100%;page-break-inside:auto}tr{page-break-inside:avoid;page-break-after:auto}'
            .'.records th,.records td{border:1px solid #cbd5e1;padding:1.3mm;vertical-align:top;overflow-wrap:anywhere}.records th{background:#7D001D;color:#fff;font-size:7px}.records td{font-size:7px}.records.wide th,.records.wide td{padding:.9mm;font-size:5.8px}.records .empty{padding:10mm;text-align:center;color:#64748b}'
            .'@media screen{body{background:#e2e8f0;padding:16px}.report-sheet{position:relative;margin:0 auto;max-width:210mm;min-height:297mm;padding:35mm 12mm 30mm;background:#fff;box-shadow:0 8px 30px rgba(15,23,42,.18)}.letterhead{position:absolute;left:12mm;right:12mm}.letterhead.header{top:5mm}.letterhead.footer{bottom:5mm}}'
            .'</style></head><body><div class="report-sheet">'.$header.$footer
            .'<div class="report-title"><h1>'.$title.'</h1><p>'.$subtitle.'</p></div>'
            .'<table class="metadata"><tr><td><b>Scope:</b> '.$scope.'</td><td><b>Period:</b> '.$period.'</td></tr><tr><td><b>Generated:</b> '.$generated.'</td><td><b>Prepared by:</b> '.$preparedBy.'</td></tr></table>'
            .'<div class="section-label">Report Records</div>'.$records.'</div></body></html>';
    }

    public function renderDailyJournalHtml(string $absolutePath, array $values): string
    {
        $stationery = $this->stationeryLayout($absolutePath);
        $header = $this->dailyJournalStationeryBlock('header', $stationery['header_paragraphs'], $stationery['header_images']);
        $footer = $this->dailyJournalStationeryBlock('footer', $stationery['footer_paragraphs'], $stationery['footer_images']);
        [$templateBody, $matchedFields] = $this->dailyJournalTemplateBody($absolutePath, $values);

        if (count(array_intersect($matchedFields, ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'am_accomplishments', 'pm_accomplishments'])) < 4) {
            $templateBody .= $this->dailyJournalDataTable($values);
        }

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            .'@page{size:A4;margin:14mm}*{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#172033;font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;line-height:1.5}'
            .'.sheet{display:flex;flex-direction:column;width:100%;margin:16px auto;max-width:210mm;min-height:269mm;padding:10mm 14mm;background:#fff;box-shadow:0 8px 30px rgba(15,23,42,.18)}'
            .'.letterhead{min-width:0;overflow-wrap:anywhere}.letterhead.header{margin-bottom:5mm;border-bottom:1px solid #555;padding-bottom:2mm}.letterhead.footer{margin-top:auto;border-top:1px solid #555;padding-top:2mm}.header-main{position:relative;width:100%;min-height:12mm;padding:0 20% 0 24%}.header-main.text-only{padding:0}.header-logos{position:absolute;top:0;left:0;display:flex;width:24%;align-items:flex-start;justify-content:center;gap:1mm}.header-logos:last-child{left:auto;right:0;width:20%}.header-logos img{flex:1 1 0;min-width:0;max-width:100%;max-height:12mm;width:0;height:auto;object-fit:contain}.header-copy{min-width:0;overflow-wrap:anywhere;text-align:left}.header-copy p,.footer-copy p{margin:0}.header-copy p{font-size:6px;line-height:1.08}.header-copy p:nth-child(2){color:#b0444e;font-weight:700;font-size:8px}.header-copy p:nth-child(3){color:#558568;font-weight:700;font-size:7px}.header-copy p:nth-child(n+4){font-size:5px}.footer-copy{font-size:6px;line-height:1.15;padding:0 4mm}.footer-copy p:last-child{text-align:center;font-style:italic}.single-logo{max-width:16mm;max-height:16mm;margin:0 auto;display:block}'
            .'.docx-body p{min-height:1em;margin:0 0 7px;white-space:pre-wrap}.docx-body p.center{text-align:center}.docx-body p.right{text-align:right}'
            .'.docx-table{width:100%;margin:7px 0 12px;border-collapse:collapse}.docx-table td{height:28px;padding:7px;border:1px solid #334155;vertical-align:top;white-space:pre-wrap}'
            .'.auto-journal{margin-top:14px}.auto-journal h1{margin:0 0 4px;color:#690016;text-align:center;font-size:17px;text-transform:uppercase}.auto-journal .meta{margin:0 0 12px;text-align:center;color:#64748b}'
            .'.auto-journal table{width:100%;border-collapse:collapse}.auto-journal th,.auto-journal td{border:1px solid #334155;padding:8px;vertical-align:top}.auto-journal th{width:26%;background:#f8fafc;color:#690016;text-align:left;font-size:9px;text-transform:uppercase}.auto-journal .activity{min-height:75px;white-space:pre-wrap}'
            .'@media screen{body{background:#e2e8f0;font-size:9px}.sheet{margin:0 auto;padding:10mm;box-shadow:none}.auto-journal{margin-top:8px}.auto-journal h1{font-size:14px}.auto-journal .meta{margin-bottom:7px}.auto-journal th,.auto-journal td{padding:5px}.auto-journal .activity{min-height:42px}}'
            .'@media print{body{background:#fff}.sheet{margin:0;padding:0;box-shadow:none}}'
            .'</style></head><body><main class="sheet">'.$header.'<section class="docx-body">'.$templateBody.'</section>'.$footer.'</main></body></html>';
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
        $options->set('defaultMediaType', 'print');
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

    private function stationeryLayout(string $absolutePath): array
    {
        $cacheKey = 'official-stationery-v2:'.sha1($absolutePath.'|'.filemtime($absolutePath));

        return Cache::remember($cacheKey, now()->addDays(7), fn () => $this->stationery($absolutePath, true));
    }

    private function stationery(string $absolutePath, bool $includeLayout = false): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('The DOCX template can no longer be opened.');
        }

        try {
            $header = $this->stationeryPart($zip, 'header', $includeLayout);
            $footer = $this->stationeryPart($zip, 'footer', $includeLayout);

            return [
                'header_image' => $header['image'],
                'header_text' => $header['text'],
                'header_images' => $header['images'],
                'header_paragraphs' => $header['paragraphs'],
                'footer_image' => $footer['image'],
                'footer_text' => $footer['text'],
                'footer_images' => $footer['images'],
                'footer_paragraphs' => $footer['paragraphs'],
            ];
        } finally {
            $zip->close();
        }
    }

    private function stationeryPart(ZipArchive $zip, string $type, bool $includeLayout): array
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
            $paragraphs = [];
            $images = [];
            $dom = new DOMDocument;
            if (@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
                $text = trim($this->nodeText($dom->documentElement, $xpath));
                foreach ($xpath->query('./w:p', $dom->documentElement) as $paragraph) {
                    $paragraphText = trim($this->nodeText($paragraph, $xpath));
                    if ($paragraphText !== '') {
                        $paragraphs[] = $paragraphText;
                    }
                }
                foreach ($includeLayout ? $xpath->query('.//a:blip[@r:embed]', $dom->documentElement) : [] as $blip) {
                    if (! $blip instanceof DOMElement) {
                        continue;
                    }
                    $relationshipId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                    $image = $this->relatedImage($zip, $partName, $relationshipId, 240);
                    if ($image === null) {
                        continue;
                    }
                    $anchor = $xpath->query('ancestor::wp:anchor', $blip)->item(0);
                    $offset = $anchor ? $xpath->query('./wp:positionH/wp:posOffset', $anchor)->item(0)?->textContent : null;
                    $images[] = ['src' => $image, 'position' => is_numeric($offset) ? (int) $offset : count($images) * 1000000];
                }
            }

            preg_match('/\br:embed="([^"]+)"/', $xml, $match);
            $image = ! $includeLayout && isset($match[1]) ? $this->relatedImage($zip, $partName, $match[1]) : null;

            return ['image' => $image, 'text' => $text, 'images' => $images, 'paragraphs' => $paragraphs];
        }

        return ['image' => null, 'text' => '', 'images' => [], 'paragraphs' => []];
    }

    private function relatedImage(ZipArchive $zip, string $partName, string $relationshipId, int $maxDimension = 0): ?string
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

        if ($maxDimension > 0 && function_exists('imagecreatefromstring') && in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            $source = @imagecreatefromstring($bytes);
            if ($source !== false) {
                $width = imagesx($source);
                $height = imagesy($source);
                if (max($width, $height) > $maxDimension) {
                    $scale = $maxDimension / max($width, $height);
                    $resized = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagecopyresampled($resized, $source, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
                    ob_start();
                    imagepng($resized);
                    $bytes = (string) ob_get_clean();
                    $mime = 'image/png';
                    imagedestroy($resized);
                }
                imagedestroy($source);
            }
        }

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

    private function reportHeaderBlock(array $paragraphs, array $images): string
    {
        if ($paragraphs === [] && $images === []) {
            return '';
        }

        usort($images, fn (array $a, array $b) => $a['position'] <=> $b['position']);
        $leftCount = (int) ceil(count($images) / 2);
        $logos = fn (array $group) => implode('', array_map(
            fn (array $image) => '<img src="'.$image['src'].'" alt="">',
            $group,
        ));
        $copy = implode('', array_map(
            fn (string $paragraph) => '<p>'.nl2br($this->escape($paragraph), false).'</p>',
            $paragraphs,
        ));

        return '<header class="letterhead header"><table class="report-letterhead-main"><tr>'
            .'<td class="logos-left">'.$logos(array_slice($images, 0, $leftCount)).'</td>'
            .'<td class="header-copy">'.$copy.'</td>'
            .'<td class="logos-right">'.$logos(array_slice($images, $leftCount)).'</td>'
            .'</tr></table></header>';
    }

    private function dailyJournalStationeryBlock(string $type, array $paragraphs, array $images): string
    {
        if ($paragraphs === [] && $images === []) {
            return '';
        }

        $copy = implode('', array_map(
            fn (string $paragraph) => '<p>'.nl2br($this->escape($paragraph), false).'</p>',
            $paragraphs,
        ));
        if ($type === 'footer') {
            $footerImages = implode('', array_map(
                fn (array $image) => '<img class="single-logo" src="'.$image['src'].'" alt="">',
                $images,
            ));

            return '<footer class="letterhead footer">'.$footerImages.'<div class="footer-copy">'.$copy.'</div></footer>';
        }

        usort($images, fn (array $a, array $b) => $a['position'] <=> $b['position']);
        $leftCount = (int) ceil(count($images) / 2);
        $logos = fn (array $group) => implode('', array_map(
            fn (array $image) => '<img src="'.$image['src'].'" alt="">',
            $group,
        ));
        $left = $logos(array_slice($images, 0, $leftCount));
        $right = $logos(array_slice($images, $leftCount));
        $layoutClass = $images === [] ? ' text-only' : '';

        return '<header class="letterhead header"><div class="header-main'.$layoutClass.'">'
            .($images === [] ? '' : '<div class="header-logos">'.$left.'</div>')
            .'<div class="header-copy">'.$copy.'</div>'
            .($images === [] ? '' : '<div class="header-logos">'.$right.'</div>')
            .'</div></header>';
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
                if ($html === '' && trim($text) === '') {
                    continue;
                }
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
