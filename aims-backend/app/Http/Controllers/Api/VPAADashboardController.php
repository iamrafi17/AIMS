<?php

namespace App\Http\Controllers\Api;

use App\Models\College;
use App\Models\MOA;
use App\Models\Program;
use Illuminate\Http\Request;

class VPAADashboardController extends UniversityDashboardController
{
    public function index(Request $request)
    {
        $response = parent::index($request);
        $dashboard = $response->getData(true);

        $pendingMoas = MOA::where('status', 'pending')->count();
        $approvedMoas = MOA::where('status', 'approved')->count();

        $dashboard['overview'] = array_merge($dashboard['overview'], [
            'colleges' => College::count(),
            'active_programs' => Program::where('is_active', true)->count(),
            'approved_moas' => $approvedMoas,
        ]);

        $dashboard['pending_approvals']['moas'] = $pendingMoas;
        $dashboard['pending_approvals']['total'] += $pendingMoas;

        return response()->json($dashboard);
    }
}
