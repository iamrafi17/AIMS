<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use Illuminate\Http\Request;

class ProgramHeadDeploymentController extends Controller
{
    public function review(Request $request, ApprovalRecord $approval)
    {
        return app(DeploymentWorkflowController::class)->approve($request, $approval);
    }
}
