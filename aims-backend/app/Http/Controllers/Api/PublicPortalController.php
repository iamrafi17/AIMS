<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\HTE;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicPortalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'statistics' => [
                'student_interns' => Student::count(),
                'partner_htes' => HTE::where('is_active', true)->count(),
                'completed_internships' => Student::forInternshipStatus('completed')->count(),
                'active_supervisors' => User::forRole('supervisor')->where('is_active', true)->count(),
            ],
            'announcements' => Announcement::query()
                ->where('is_published', true)
                ->whereNull('archived_at')
                ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                ->whereIn('target_audience', ['all', 'students'])
                ->latest('published_at')
                ->limit(3)
                ->get(['id', 'title', 'content', 'published_at']),
        ]);
    }
}
