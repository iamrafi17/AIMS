<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\HTE;
use Illuminate\Http\Request;

class CollegeController extends Controller
{
    public function index()
    {
        $colleges = College::where('is_active', true)->get();

        return response()->json($colleges);
    }

    public function show(College $college)
    {
        return response()->json($college);
    }

    public function programs(College $college)
    {
        $programs = $college->programs()->where('is_active', true)->get();

        return response()->json($programs);
    }

    public function htes(Request $request, College $college)
    {
        $htes = HTE::where('is_active', true)
            ->where('college_id', $college->id)
            ->when($request->integer('program_id'), fn ($query, int $programId) => $query->where('program_id', $programId))
            ->get();

        return response()->json($htes);
    }
}
