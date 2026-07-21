<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role;

        $announcements = Announcement::where('is_published', true)
            ->where(function ($query) use ($role) {
                $query->where('target_audience', 'all')
                      ->orWhere('target_audience', $role . 's');
            })
            ->latest()
            ->paginate(10);

        return response()->json($announcements);
    }

    public function show(Announcement $announcement)
    {
        return response()->json($announcement);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_audience' => 'required|in:all,students,coordinators,program_heads,vpaa,admin,supervisor',
            'is_published' => 'boolean',
        ]);

        $announcement = Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'author_id' => $request->user()->id,
            'target_audience' => $request->target_audience,
            'is_published' => $request->is_published ?? false,
            'published_at' => $request->is_published ? now() : null,
        ]);

        return response()->json([
            'message' => 'Announcement created successfully',
            'announcement' => $announcement,
        ], 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_audience' => 'required|in:all,students,coordinators,program_heads,vpaa,admin,supervisor',
            'is_published' => 'boolean',
        ]);

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'target_audience' => $request->target_audience,
            'is_published' => $request->is_published,
            'published_at' => $request->is_published && !$announcement->published_at ? now() : $announcement->published_at,
        ]);

        return response()->json([
            'message' => 'Announcement updated successfully',
            'announcement' => $announcement,
        ]);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->json(['message' => 'Announcement deleted successfully']);
    }
}
