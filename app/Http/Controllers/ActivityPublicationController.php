<?php

namespace App\Http\Controllers;

use App\Enums\PublicationPlatform;
use App\Http\Requests\Activity\StorePublicationRequest;
use App\Http\Requests\Activity\UpdatePublicationRequest;
use App\Models\Activity;
use App\Models\Publication;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ActivityPublicationController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function store(StorePublicationRequest $request, Activity $activity): RedirectResponse
    {
        DB::transaction(function () use ($request, $activity) {
            $publication = Publication::query()->create([
                'activity_id' => $activity->id,
                'platform' => $request->enum('platform', PublicationPlatform::class),
                'title' => $request->string('title')->toString(),
                'url' => $request->string('url')->toString(),
                'published_at' => $request->input('published_at'),
                'notes' => $request->input('notes'),
                'created_by' => $request->user()?->id,
            ]);

            $this->auditLog->logModelChange(
                'publication_created',
                $publication,
                null,
                $publication->only(['platform', 'title', 'url', 'published_at']),
                $request,
            );
        });

        return back()->with('success', 'Publikasi berhasil ditambahkan.');
    }

    public function update(UpdatePublicationRequest $request, Activity $activity, Publication $publication): RedirectResponse
    {
        $oldValues = $publication->only(['platform', 'title', 'url', 'published_at', 'notes']);

        $publication->fill([
            'platform' => $request->enum('platform', PublicationPlatform::class),
            'title' => $request->string('title')->toString(),
            'url' => $request->string('url')->toString(),
            'published_at' => $request->input('published_at'),
            'notes' => $request->input('notes'),
        ])->save();

        $this->auditLog->logModelChange(
            'publication_updated',
            $publication,
            $oldValues,
            $publication->only(['platform', 'title', 'url', 'published_at', 'notes']),
            $request,
        );

        return back()->with('success', 'Publikasi berhasil diperbarui.');
    }

    public function destroy(Activity $activity, Publication $publication): RedirectResponse
    {
        $this->authorize('manageDocuments', $activity);

        if ((int) $publication->activity_id !== (int) $activity->id) {
            abort(404);
        }

        DB::transaction(function () use ($publication, $activity) {
            $oldValues = $publication->only(['platform', 'title', 'url']);
            $publication->delete();

            $this->auditLog->log(
                'publication_deleted',
                'publication',
                $publication->id,
                $oldValues,
                null,
                request(),
            );
        });

        return back()->with('success', 'Publikasi berhasil dihapus.');
    }
}
