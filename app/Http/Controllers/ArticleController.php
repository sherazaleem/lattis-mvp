<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function published(): Response
    {
        $articles = GeneratedArticle::where('status', 'published')
            ->with('site:id,domain')
            ->orderByDesc('published_at')
            ->get(['id', 'site_id', 'title', 'slug', 'external_url', 'published_at']);

        return Inertia::render('Articles/Published', [
            'articles' => $articles,
        ]);
    }
}
