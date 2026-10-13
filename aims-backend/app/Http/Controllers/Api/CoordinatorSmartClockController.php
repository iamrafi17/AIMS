<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;

class CoordinatorSmartClockController extends Controller
{
    public function show(Request $request)
    {
        $program = Program::findOrFail(ProgramAccess::programId($request->user()));

        return response()->json($this->payload($program));
    }

    public function update(Request $request)
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $program = Program::findOrFail(ProgramAccess::programId($request->user()));
        $program->update(['smart_clock_enabled' => $validated['enabled']]);

        return response()->json($this->payload($program));
    }

    private function payload(Program $program): array
    {
        return [
            'program_id' => $program->id,
            'program_code' => $program->code,
            'enabled' => $program->smart_clock_enabled,
        ];
    }
}
