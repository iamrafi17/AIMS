<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    private const AUDIENCES = [
        'all',
        'students',
        'coordinators',
        'program_heads',
        'vpaa',
        'admin',
        'supervisor',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $audiences = match ($user->role) {
            'student' => ['all', 'students'],
            'coordinator' => ['all', 'coordinators'],
            'program_head' => ['all', 'program_heads'],
            default => ['all', $user->role],
        };

        $query = Announcement::query()
            ->with('author:id,name')
            ->withExists([
                'readers as is_read' => fn ($readerQuery) => $readerQuery->where('users.id', $user->id),
            ])
            ->whereIn('target_audience', $audiences)
            ->whereNull('archived_at')
            ->where('is_published', true)
            ->where(function ($publishQuery) {
                $publishQuery->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        return response()->json($query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(12));
    }

    public function manage(Request $request)
    {
        $query = Announcement::query()
            ->with('author:id,name')
            ->withCount('readers');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'published' => $query->where('is_published', true)->whereNull('archived_at'),
                'draft' => $query->where('is_published', false)->whereNull('archived_at'),
                'scheduled' => $query->whereNotNull('scheduled_at')->where('scheduled_at', '>', now()),
                'archived' => $query->whereNotNull('archived_at'),
                default => null,
            };
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(Request $request, Announcement $announcement)
    {
        $announcement->load('author:id,name');
        $announcement->setAttribute(
            'is_read',
            $announcement->readers()->where('users.id', $request->user()->id)->exists()
        );

        return response()->json($announcement);
    }

    public function markRead(Request $request, Announcement $announcement)
    {
        $announcement->readers()->syncWithoutDetaching([
            $request->user()->id => ['read_at' => now()],
        ]);

        return response()->json(['message' => 'Announcement marked as read.']);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? now() : null;

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('announcements');
            $data['attachment_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $announcement = Announcement::create($data);
        if ($announcement->is_published && (! $announcement->scheduled_at || $announcement->scheduled_at->lte(now()))) {
            $roles = match ($announcement->target_audience) {
                'students' => ['student'],
                'coordinators' => ['coordinator'],
                'program_heads' => ['program_head'],
                'all' => ['student', 'coordinator', 'program_head', 'vpaa', 'admin', 'supervisor'],
                default => [$announcement->target_audience],
            };
            User::whereIn('role', $roles)->where('is_active', true)->get(['id', 'role'])->each(
                fn (User $recipient) => SystemNotification::sendToUser(
                    $recipient->id,
                    $announcement->title,
                    'A new '.str_replace('_', ' ', $announcement->category).' announcement was published.',
                    'announcement',
                    '/'.($recipient->role === 'program_head' ? 'program-head' : $recipient->role).'/announcements',
                )
            );
        }

        return response()->json([
            'message' => $announcement->is_published
                ? 'Announcement published successfully.'
                : 'Announcement saved as a draft.',
            'announcement' => $announcement->load('author:id,name'),
        ], 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && ! $announcement->published_at) {
            $data['published_at'] = now();
        }

        if (! $data['is_published']) {
            $data['published_at'] = null;
        }

        if ($request->hasFile('attachment')) {
            if ($announcement->attachment_path) {
                Storage::delete($announcement->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('announcements');
            $data['attachment_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $announcement->update($data);

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'announcement' => $announcement->fresh()->load('author:id,name'),
        ]);
    }

    public function archive(Announcement $announcement)
    {
        $announcement->update(['archived_at' => now()]);

        return response()->json(['message' => 'Announcement archived successfully.']);
    }

    public function restore(Announcement $announcement)
    {
        $announcement->update(['archived_at' => null]);

        return response()->json(['message' => 'Announcement restored successfully.']);
    }

    public function download(Announcement $announcement)
    {
        abort_unless($announcement->attachment_path && Storage::exists($announcement->attachment_path), 404);

        return Storage::download($announcement->attachment_path, $announcement->attachment_name);
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->attachment_path) {
            Storage::delete($announcement->attachment_path);
        }

        $announcement->delete();

        return response()->json(['message' => 'Announcement permanently deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(['general', 'advisory', 'activity', 'deadline', 'emergency'])],
            'target_audience' => ['required', Rule::in(self::AUDIENCES)],
            'is_published' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);
    }
}
