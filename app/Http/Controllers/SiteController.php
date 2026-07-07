<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(): Response
    {
        $sites = Site::where('is_active', true)
            ->with('cluster:id,name,review_level')
            ->withCount(['generatedArticles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('domain')
            ->get()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'domain' => $site->domain,
                'stack_type' => $site->stack_type,
                'cluster' => $site->cluster,
                'auto_publish' => $site->auto_publish,
                'effective_auto_publish' => $site->effectiveAutoPublish(),
                'max_posts_per_day' => $site->max_posts_per_day,
                'timezone' => $site->timezone,
                'published_count' => $site->published_count,
                'credential_status' => $site->credentials()->where('adapter_type', $site->stack_type)->value('credential_status'),
            ]);

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
        ]);
    }
}
