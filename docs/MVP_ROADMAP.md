# Lattis One — ATLAS: Solo MVP Roadmap

**Goal:** get real content flowing — RSS in, AI-generated article out, published live on a real website — before building anything else. Everything not required for that loop is deferred.

**Context:** the original plan (attached docs) is a 20-week, 10-sprint build for 2–3 people. You're building solo, part-time (~15–20h/week). This roadmap re-sequences the same architecture around one person's time, and cuts scope to the minimum that proves the pipeline works end to end.

**Estimated timeline:** ~6–8 weeks part-time to first real article published automatically to a live site.

---

## What's IN the MVP (non-negotiable, keep from day one)

These cost almost nothing to do now and are expensive to retrofit, so they stay even though we're cutting scope elsewhere:

- **Two-database split** — MariaDB for structured/operational data, MongoDB for article bodies and author persona documents. Bolting this on later means a painful migration.
- **Three interfaces defined before any implementation** — `AIProviderInterface`, `PublishingAdapterInterface`, `ContentFilterInterface`. Costs one day. Saves a rewrite the moment you add a second AI provider or a second publishing target.
- **SHA-256 duplicate detection** — trivial to add now, prevents the same article being processed twice forever.
- **The HOLD override rule** — if any check flags an article HOLD, it goes to human review *regardless* of a site's auto-publish setting. This is one `if` statement wide but it's the entire safety net for anything sensitive later (Health, Satire, legal claims). Build the mechanism now even if you don't wire up every filter yet.
- **The article state machine** — `queued → generating → generated → review/approved → scheduled → publishing → published` (+ `failed`, `rejected`, `skipped`). This is core plumbing everything else hangs off; changing it later touches every table and job.
- **Credential encryption** — `Crypt::encryptString()`, never store WordPress/FTP secrets plain, from the first migration.

## What's OUT of the MVP (deferred, not abandoned)

| Deferred | Why | Comes back |
|---|---|---|
| Promo injection engine | Orthogonal to proving the core loop works | Post-MVP, Phase 1 completion |
| Full admin dashboards (cost tracker, pipeline dashboard, per-site status board) | You'll watch 2–3 sites manually at MVP scale; a dashboard for that is premature | Post-MVP |
| All 10 quality gate checks | Only build the checks relevant to your first pilot sites (see below) | Add checks as new site types come online |
| Multi-provider cost comparison test | Formal A/B testing across 5 models is a team-scale exercise. Pick **one** provider you already have API access to, build behind the interface, add others later — the interface makes this a config change, not a rewrite | Post-MVP, when spend matters |
| Author memory / voice-drift detection jobs | Needs weeks of production data to mean anything | Phase 2 (matches original plan's own reasoning) |
| Expanded pilot ceremony (10→20→60 sites, retrospectives) | You're validating the pipeline works at all, not scaling it yet | After MVP proves stable on 2–3 sites |
| SEO field generation sophistication (FAQ schema, etc.) | Ship title/slug/meta only first; expand once articles are flowing | Post-MVP |
| Shopify/Ghost adapters | Already deferred in the original plan — no live targets exist | Phase 2, unchanged |

---

## Build Order

### Stage 0 — Decisions (do this before writing code, ~3–4h)
Lock these so Cursor sessions don't drift:
- Which **one** AI provider you're starting with (OpenAI, Claude, or Ollama). Recommendation: whichever you already have API keys and billing set up for — the interface means switching later costs one class, not a rewrite.
- Which **one** publishing target you're starting with — WordPress REST API or FTP/HTML. Pick based on what your first 2–3 pilot sites actually run.
- List your first 2–3 pilot sites and confirm: domain, stack type, RSS feed URL(s), niche/cluster.
- Confirm none of your first pilot sites are Health or Satire content — if they are, you need the HOLD mechanism wired to a real filter before go-live, not after (see Stage 3).

### Stage 1 — Laravel Foundation (~1 week)
- Laravel + Inertia.js project, MariaDB connection, MongoDB connection (`mongodb/laravel-mongodb`), Redis connection.
- Laravel Horizon installed, three queue channels configured: `ingestion`, `generation`, `publishing`.
- Core MariaDB migrations: `sites`, `site_dna`, `niche_clusters`, `credentials`, `rss_sources`, `rss_items`, `generated_articles`, `system_logs`. (Skip `author_assignments` and the full author persona MongoDB collection for MVP — hardcode a single default author persona per site to start; add real author management once the pipeline works.)
- `CredentialService` with `encrypt()`/`decrypt()`/`verifyCredentials()`.
- The three interfaces, empty method signatures only, committed to `app/Interfaces`.
- Minimal auth — one admin user is enough at MVP scale. Skip the four-role system for now.

**Exit check:** you can insert a site record with encrypted credentials via `php artisan tinker` and read it back decrypted.

### Stage 2 — RSS Ingestion (~1 week)
- `FetchRssFeedJob` on the `ingestion` channel: fetch, parse, store `rss_items`, 3 retries with back-off.
- Duplicate detection: SHA-256(url + title), inline in the fetch job.
- Word count filter: skip anything under 300 words (`is_processed = true`, no generation).
- Scheduled command dispatching fetch jobs on each feed's `fetch_frequency_minutes`.
- Skip the feed review UI for MVP — check `rss_items` via `tinker` or a raw DB browser.

**Exit check:** point one real RSS feed at this, watch items land in `rss_items`, confirm duplicates get skipped on a second run.

### Stage 3 — AI Generation + Core Quality Gate (~1.5–2 weeks)
- Implement `AIProviderInterface` for your one chosen provider.
- `PromptBuilderService`: assembles prompt from `site_dna` + a hardcoded author fragment + source article. No external calls.
- `GenerateArticleJob` on the `generation` channel: calls the provider, stores the body in MongoDB, metadata in `generated_articles`.
- `OutputValidator` — build **only these checks** for MVP, using `ContentFilterInterface`:
  - Word count (FAIL below site minimum)
  - Duplicate paragraph (FAIL)
  - Source similarity — cosine similarity above 0.85 (FAIL)
  - Forbidden topic keyword scan (FAIL)
  - SEO fields non-empty (FAIL) — add once Stage 3.5 below exists
  - **If any pilot site is Health/Satire:** also build the relevant HOLD filter now, not later.
- Basic SEO field generation (title, slug, meta description only — skip FAQ schema/image alt for MVP) as a follow-on job.
- Article state machine transitions wired through `generated_articles.status`.

**Exit check:** a real RSS item produces a generated article, passes or fails the gate correctly, and lands in the right status.

### Stage 4 — Publishing (~1–1.5 weeks)
- Implement `PublishingAdapterInterface` for your one chosen target (WordPress or FTP).
- `PublishingSchedulerCommand` (every 5 min): finds approved articles, respects `max_posts_per_day` and site timezone.
- `PublishArticleJob` on the `publishing` channel: decrypts credentials, calls the adapter, updates status, retries with back-off.
- Daily `CredentialHealthCheckCommand`: verifies credentials, halts publishing for a site on failure.

**Exit check:** an approved article actually appears live on a real test site, automatically, with no manual step.

### Stage 5 — Minimal Review Path + Second Adapter (~1 week)
- One simple review screen (Inertia page): list articles with `status = review`, show body + quality flags, approve/reject buttons. This is required because of the HOLD rule — don't skip it even at MVP scale.
- Second publishing adapter (whichever of WordPress/FTP you didn't build in Stage 4), if your pilot sites need both.

**Exit check:** an article with a HOLD flag lands in the review queue regardless of the site's auto-publish setting, and you can approve it through to publish.

### Stage 6 — Smoke Test & Stabilize (~0.5–1 week)
- Run the full loop live on your 2–3 pilot sites for several days.
- Fix whatever breaks. Watch for: silent publish failures, credential expiry, feed errors, quality gate false positives/negatives.
- Write yourself a short runbook: how to retry a failed job, how to disable a site, how to update credentials.

**MVP done when:** 2–3 real sites are receiving auto-generated, quality-gated content automatically, with no manual intervention except reviewing flagged/HOLD articles.

---

## After the MVP — Phase 1 Completion Backlog

Once the core loop is proven, work through the deferred list roughly in this order (matches the original plan's own sequencing logic):

1. Remaining quality gate checks (medical claim, FTC disclosure, satire moderation, promo density) as new site types come online.
2. Promo injection engine.
3. Real author persona management + rolling memory (replace the MVP's hardcoded default author).
4. Full dashboards: pipeline health, per-site status, failed jobs log, generation audit log, AI cost tracker.
5. All 8 alerts (credential failure, silent site, publish failures, queue depth, LLM error rate, daily spend, content policy rejection, stale feed).
6. Formal AI provider cost-comparison test — now with real production data to compare against.
7. Gradual rollout: 5–10 sites → 20 → all 60+, each with a monitoring window before expanding.
8. AI Project Manager (Phase 2, per the original plan — needs months of the operational data this build now produces).

---

## Working in Cursor

- Keep this roadmap and the three original planning docs in `/docs` in the repo — reference them by name in Cursor chats ("per docs/MVP_ROADMAP.md Stage 3...") so the AI has the real spec instead of guessing.
- A `.cursorrules` file is included in this scaffold — it encodes the architectural decisions above (two databases, three interfaces, the HOLD rule, no shortcuts) so Cursor doesn't quietly violate them in a later session when the plan isn't in context.
- Work one stage at a time. Ask Cursor to implement one job/service/migration at a time rather than "build the pipeline" — smaller asks are easier to review and keep correct.
- Commit after every green exit-check. These are your rollback points.
- When you do add a second AI provider or publishing adapter later, that's the moment to verify the interfaces actually paid off — it should be a new class, not touched code elsewhere.
