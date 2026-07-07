<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one review screen required by the HOLD rule (.cursorrules rule 3) —
 * any article sitting in status='review' needs a human to approve or
 * reject it, regardless of why it landed here (HOLD flag or a site without
 * auto_publish).
 */
class ReviewController extends Controller
{
    public function index(): Response
    {
        $articles = GeneratedArticle::where('status', 'review')
            ->with(['site:id,domain', 'rssItem:id,title,url'])
            ->orderBy('created_at')
            ->get([
                'id', 'site_id', 'rss_item_id', 'title', 'slug', 'body',
                'meta_description', 'quality_flags', 'created_at',
            ]);

        return Inertia::render('Review/Index', [
            'articles' => $articles,
        ]);
    }

    public function approve(GeneratedArticle $article): RedirectResponse
    {
        abort_unless($article->status === 'review', 422, 'Article is not awaiting review.');

        $article->transitionTo('approved');
        $article->update([
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('review.index');
    }

    public function reject(Request $request, GeneratedArticle $article): RedirectResponse
    {
        abort_unless($article->status === 'review', 422, 'Article is not awaiting review.');

        $validated = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $article->transitionTo('rejected');
        $article->update([
            'reject_reason' => $validated['reject_reason'] ?? 'Rejected by reviewer.',
        ]);

        return redirect()->route('review.index');
    }
}
