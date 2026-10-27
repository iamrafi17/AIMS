<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EvaluationCsvImport
{
    public function parse(string $path, array $mapping): array
    {
        $handle = fopen($path, 'rb');
        $rows = [];
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($rows) >= 10000 || count($row) > 200) {
                    throw ValidationException::withMessages(['file' => 'CSV limit: 10,000 rows and 200 columns.']);
                }
                foreach ($row as $cell) {
                    if (! mb_check_encoding((string) $cell, 'UTF-8') || str_contains((string) $cell, "\0")) {
                        throw ValidationException::withMessages(['file' => 'Use a UTF-8 CSV without binary content.']);
                    }
                }
                $rows[] = array_map(fn ($v) => trim(ltrim((string) $v, "\xEF\xBB\xBF")), $row);
            }
        } finally {
            fclose($handle);
        }
        $layout = $mapping['layout'];
        $categoryCol = (int) $mapping['category_column'];
        $criterionCol = (int) $mapping['criterion_column'];
        $subgroupCol = isset($mapping['subgroup_column']) ? (int) $mapping['subgroup_column'] : null;
        $mappedCols = array_filter([$categoryCol, $criterionCol, $subgroupCol], fn ($v) => $v !== null);
        if (count(array_unique($mappedCols)) !== count($mappedCols)) {
            throw ValidationException::withMessages(['mapping' => 'Choose different columns for category, subgroup and criterion.']);
        }
        $categories = [];
        $categoryIndex = null;
        $subgroupIndex = null;
        $errors = [];
        $skipped = [];
        $stopped = false;
        $count = 0;
        $start = (int) ($mapping['start_row'] ?? 1);
        $end = (int) ($mapping['end_row'] ?? count($rows));
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 1;
            if ($rowNumber < $start || $rowNumber > $end) {
                continue;
            }
            $a = $row[$categoryCol] ?? '';
            $b = $row[$criterionCol] ?? '';
            $s = $subgroupCol === null ? '' : ($row[$subgroupCol] ?? '');
            if (preg_match('/SUBMITTED DOCUMENTS CHECKLIST/i', $a.' '.$b)) {
                $stopped = true;
            }
            if ($stopped) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Document checklist'];

                continue;
            }
            if ($a === '' && $b === '' && $s === '') {
                continue;
            }
            $skipPattern = '/^(AVERAGE|OVERALL AVERAGE|FINAL GRADE|PERFORMANCE FACTORS|NAME OF OJT STUDENT\b.*|NOTE:.*|category|criterion|subgroup)$/is';
            if (preg_match($skipPattern, $a) || preg_match($skipPattern, $b)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Header, instruction, or result label'];

                continue;
            }
            if ($a !== '' && ($layout === 'report' || $categoryIndex === null || $categories[$categoryIndex]['title'] !== $a)) {
                $categories[] = ['title' => $a, 'criteria' => [], 'subgroups' => []];
                $categoryIndex = count($categories) - 1;
                $subgroupIndex = null;
            }
            if ($categoryIndex === null) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Criterion has no category. Correct the mapping or CSV.'];

                continue;
            }
            if ($layout === 'report') {
                // A numbered row followed by lettered items is a subgroup. Never treat every numbered criterion as a heading.
                $next = '';
                for ($j = $i + 1; $j < min(count($rows), $end); $j++) {
                    if (($rows[$j][$categoryCol] ?? '') !== '') {
                        break;
                    }
                    $next = $rows[$j][$criterionCol] ?? '';
                    if ($next !== '') {
                        break;
                    }
                }
                if (preg_match('/^\d+[.)]\s*/', $b) && preg_match('/^[a-z][.)]+\s*/i', $next)) {
                    $categories[$categoryIndex]['subgroups'][] = ['title' => $b, 'criteria' => []];
                    $subgroupIndex = count($categories[$categoryIndex]['subgroups']) - 1;

                    continue;
                }
                if ($b !== '' && ! preg_match('/^(\d+|[a-z])[.)]+\s*/i', $b)) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Unrecognized report row. Use normalized columns or correct the row.'];

                    continue;
                }
            } else {
                if ($s === '') {
                    $subgroupIndex = null;
                } elseif ($subgroupIndex === null || $categories[$categoryIndex]['subgroups'][$subgroupIndex]['title'] !== $s) {
                    $categories[$categoryIndex]['subgroups'][] = ['title' => $s, 'criteria' => []];
                    $subgroupIndex = count($categories[$categoryIndex]['subgroups']) - 1;
                }
            }
            if ($b === '') {
                if ($layout === 'normalized') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Criterion is blank.'];
                }

                continue;
            }
            $extra = str_contains($b, '___') ? (preg_match('/program in/i', $b) ? 'Programming language' : 'Software used') : null;
            $criterion = ['label' => $b, 'extra_input_label' => $extra];
            if ($subgroupIndex === null) {
                $categories[$categoryIndex]['criteria'][] = $criterion;
            } else {
                $categories[$categoryIndex]['subgroups'][$subgroupIndex]['criteria'][] = $criterion;
            }
            $count++;
        }
        if (! $count) {
            $errors[] = ['row' => null, 'message' => 'No criteria found. Check the selected columns and rows.'];
        }
        if (! $errors) {
            app(EvaluationFormService::class)->validate(['categories' => $categories]);
        }

        return [
            'source_rows' => count($rows), 'selected_rows' => [$start, $end],
            'categories' => $categories, 'errors' => $errors, 'skipped' => $skipped,
            'category_count' => count($categories), 'subgroup_count' => array_sum(array_map(fn ($c) => count($c['subgroups']), $categories)),
            'criteria_count' => $count,
        ];
    }
}
