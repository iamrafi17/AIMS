<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelLog;
use Illuminate\Http\Request;

class StudentTravelController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');

        $sessions = TravelLog::query()
            ->with([
                'creator:id,name',
                'hte:id,name,address',
                'participants:id,student_id,first_name,last_name',
                'checkpoints' => fn ($query) => $query->orderBy('sequence'),
            ])
            ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
            ->latest('start_time')
            ->get();

        return response()->json($sessions);
    }
}
