<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportExportController extends Controller
{
    public function __construct(private readonly ReportExportService $exports) {}

    public function coordinator(Request $request, string $format)
    {
        return $this->exports->download($this->coordinatorDataset($request), $format);
    }

    public function coordinatorPreview(Request $request)
    {
        return response()->json($this->coordinatorDataset($request));
    }

    public function programHead(Request $request, string $format)
    {
        return $this->exports->download($this->programHeadDataset($request), $format);
    }

    public function programHeadPreview(Request $request)
    {
        return response()->json($this->programHeadDataset($request));
    }

    public function vpaa(Request $request, string $format)
    {
        return $this->exports->download($this->vpaaDataset($request), $format);
    }

    public function vpaaPreview(Request $request)
    {
        return response()->json($this->vpaaDataset($request));
    }

    private function coordinatorDataset(Request $request): array
    {
        $validated = $request->validate([
            'report' => ['required', Rule::in(['student_internship', 'hte_deployment', 'travel_monitoring'])],
        ]);
        $response = app(CoordinatorReportController::class)->index($request);

        return $this->withPreparer($this->exports->coordinator($response->getData(true), $validated['report']), $request);
    }

    private function programHeadDataset(Request $request): array
    {
        $validated = $request->validate([
            'report' => ['required', Rule::in(['program_summary', 'hte_deployment_review', 'travel_monitoring_review'])],
        ]);
        $response = app(ProgramHeadDashboardController::class)->index($request);

        return $this->withPreparer($this->exports->programHead($response->getData(true), $validated['report']), $request);
    }

    private function vpaaDataset(Request $request): array
    {
        $validated = $request->validate([
            'report' => ['required', Rule::in(['overall_summary', 'hte_deployment_validation', 'travel_monitoring_validation'])],
        ]);
        $response = app(VPAADashboardController::class)->index($request);

        return $this->withPreparer($this->exports->vpaa($response->getData(true), $validated['report']), $request);
    }

    private function withPreparer(array $dataset, Request $request): array
    {
        $dataset['generated_by'] = $request->user()->name;

        return $dataset;
    }
}
