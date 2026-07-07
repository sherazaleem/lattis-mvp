**Lattis One --- ATLAS**

Open Questions --- Full Response

*Direct positions on all 4 DEC items and all 35 client discussion
questions*

To be read alongside: ATLAS Plan (Phase 1) and Plan Addendum

> **PURPOSE** Every open item from the client planning page gets a
> direct position here --- no item left unanswered or implied. Items
> already resolved in the Plan Addendum are restated briefly for
> completeness, then every remaining question is answered for the first
> time.

**The Four DEC Items**

  ------------------------- --------------------------------------- ------------
  **Decision**              **Position**                            **Status**

  DEC 01 --- Content        Hybrid: MariaDB for                     DECIDED ---
  storage: document vs      structured/operational data, MongoDB    in main plan
  relational                for article bodies and author personas, 
                            linked by reference ID.                 

  DEC 02 --- Author agent   MongoDB document per author with a      DECIDED ---
  persistence and           version field and a rolling memory      in main plan
  versioning                object (last 10 article summaries).     
                            Memory updates automatically after      
                            publish.                                

  DEC 03 --- Publishing     WordPress and FTP/HTML adapters built   DECIDED ---
  adapter strategy          in Phase 1. Shopify and Ghost           clarified
  (WP/Shopify/HTML/Ghost)   interfaces defined now, implementations below
                            deferred to Phase 2 --- see explicit    
                            reasoning below.                        

  DEC 04 --- AI Project     Deferred to Phase 2 by design.          DECIDED ---
  Manager: single vs        Single-vs-federated is answered below   in Plan
  federated supervisor      as a Phase 2 question, not a Phase 1    Addendum
                            blocker.                                
  ------------------------- --------------------------------------- ------------

**DEC 03 --- Why Shopify and Ghost Are Deferred, Stated Explicitly**

This is a deliberate scope decision, not an oversight. All 60+ existing
sites run on WordPress or static HTML/FTP. There is no Shopify or Ghost
site in the current network this phase needs to publish to. Building
those adapters now means building and testing infrastructure with zero
live targets to validate against --- that is wasted effort relative to
getting WordPress and FTP publishing solid first.

The PublishingAdapterInterface is designed so this costs us nothing
later: adding ShopifyAdapter or GhostAdapter in Phase 2 is one new class
implementing the existing interface, with zero changes to the publishing
engine, the scheduler, or the queue system. The interface is the
decision made now. The implementation is correctly sequenced for later.

**AI Identity and Agents**

> **Q** *Should an author be allowed to write for more than one site?
> Under what rules?*
>
> **POSITION** Yes, with an explicit conflict rule. An author can be
> assigned to multiple sites via the author_assignments table. When DNA
> conflicts arise --- e.g. one site forbids a topic the author would
> normally write about --- the site-level forbidden_topics list always
> wins over the author\'s general behaviour. The author adapts tone and
> angle per site\'s DNA fragment, but the site\'s content rules are
> never overridden by the author\'s personality. Cross-site writing is
> opt-in per author, not default --- most authors stay assigned to one
> primary site, with cross-site assignment used deliberately for
> clusters that benefit from a consistent voice across related sites.
>
> **Q** *How long does an author need to remember their previous work?*
>
> **POSITION** A rolling window of the last 10 article summaries, stored
> in the memory object. This was already specified in the main plan\'s
> MongoDB author_personas schema. Restated here for completeness: full
> history forever is a cost and noise problem; no memory at all produces
> repetitive content. Ten article summaries is the practical middle
> ground and can be tuned once we see real output.
>
> **Q** *How do we know when an author has drifted off voice and needs
> retraining?*
>
> **POSITION** Two automatic signals, both computable from data we
> already store. First: rolling quality score --- if an author\'s
> average quality_score across their last 30 articles drops more than
> 15% compared to the 30 before that, flag the author for review.
> Second: output similarity --- if cosine similarity between an
> author\'s recent articles trends upward over time (meaning their own
> outputs are converging on each other), that indicates repetition
> fatigue rather than voice drift, and should trigger a memory reset or
> prompt_fragment refresh rather than a full retrain. Both checks run as
> a weekly scheduled job, not in real time --- voice drift is a slow
> signal, not an urgent one.
>
> **Q** *How do we version an author when we change their style without
> breaking past work?*
>
> **POSITION** Already specified in the main plan: every author document
> has a version integer. generated_articles stores which author_version
> produced each article. Updating the author\'s style increments the
> version --- past articles keep their original version reference
> permanently, so we can always answer \"which voice wrote this\" even
> after the author has been updated multiple times. No past article is
> ever silently reattributed to a newer version.

**Site Intelligence**

> **Q** *Should a site learn from its own traffic and change behavior
> over time?*
>
> **POSITION** Not in Phase 1. We have no traffic data yet --- there is
> nothing to learn from. In Phase 2, once SURFACE exists and is
> capturing traffic and engagement signals, site behaviour (publishing
> frequency, topic prioritisation, CTA style) can start responding to
> performance data. Even then, the system should suggest changes for a
> human to approve rather than self-modify automatically --- the same
> reasoning as the AI Project Manager: changes should be data-informed
> but human-confirmed until we have enough operating history to trust
> automated adjustment.
>
> **Q** *How often should a site post? Who decides that, a person or the
> system?*
>
> **POSITION** A person sets max_posts_per_day per site (already a field
> in the schema). The system enforces that limit but does not change it
> autonomously in Phase 1. In Phase 2, once traffic data exists, the
> system can recommend a frequency change based on performance, but a
> person approves the change before it takes effect.
>
> **Q** *How do we handle topics where two sites overlap?*
>
> **POSITION** Differentiate at the DNA level, not at the topic level.
> Two sites can legitimately cover the same RSS source --- the
> difference must come from angle and audience, not from avoiding the
> topic. This is already addressed structurally in the main plan (Site
> DNA angle and audience fields), restated here because the client asked
> it directly: if two sites in the same cluster are producing
> similar-feeling articles on the same topic, that is a DNA
> differentiation problem to fix, not a topic-routing problem to solve
> with rules.
>
> **Q** *How are sites grouped into clusters that share rules?*
>
> **POSITION** Via the niche_clusters table, already specified ---
> review_level, default_max_posts_per_day, and content_filter_config
> live at the cluster level and are inherited by every site in that
> cluster unless overridden at the site level.
>
> **Q** *What does it mean for a site to be performing well or poorly?*
>
> **POSITION** A site is performing well if it is publishing
> consistently against its max_posts_per_day target, its rolling quality
> score is stable or rising, and it generates few or no HOLD/FAIL flags.
> A site is performing poorly if it has publishing gaps, a falling
> quality score, or repeated quality gate failures. This is computable
> entirely from data already in the schema --- no new fields are needed,
> only a dashboard view (already planned in Section 7 of the main plan)
> that surfaces it.

**AI Supervision**

> **Q** *What should the system handle without telling anyone?*
>
> **POSITION** Already specified via retry logic in the main plan:
> standard retries on transient failures, duplicate detection and
> skipping, scheduling within configured limits, and promo injection
> failures (publish without promo silently). Restated as a direct
> answer: anything that is a known, bounded, recoverable failure handles
> itself. Nothing with legal, financial, or irreversible consequence
> handles itself silently.
>
> **Q** *What should the system flag immediately to a human?*
>
> **POSITION** The 8 alerts already defined in Section 9 of the main
> plan: credential failures, sites silent for 3+ days, content policy
> rejections, HOLD flags, queue depth thresholds, and any bulk action
> affecting multiple sites. This list is the complete answer ---
> restated here so it is visible as a direct response to this specific
> question.
>
> **Q** *Who is the human that gets the alerts? One person, a team, a
> rotation?*
>
> **POSITION** The Operator role, not a rotation. At this scale (60+
> sites, Phase 1), a small dedicated team receiving alerts directly is
> more reliable than a rotation --- rotations introduce handoff gaps
> exactly where silent failures live. A rotation becomes worth
> considering in Phase 2 once volume genuinely requires 24/7 coverage.
>
> **Q** *How do we stop the supervisor agent from making bad calls at
> scale? (Phase 2 question)*
>
> **POSITION** This only becomes relevant once the AI Project Manager is
> built in Phase 2. The guardrail commitment now, for when that work
> starts: every autonomous action the supervisor takes must be logged
> with its full reasoning, and every action must have a one-click human
> undo. The supervisor is never given an action type that cannot be
> reversed. Phase 1 does not need to solve this --- it needs to produce
> the operational history Phase 2 will use to design the supervisor
> responsibly.
>
> **Q** *Single supervisor or a federation of supervisors per layer?
> (Phase 2 question, part of DEC 04)*
>
> **POSITION** Not answerable responsibly before Phase 1 operational
> data exists, and that is the honest position rather than a guess. Once
> we have failure pattern data from Phase 1, this becomes a much easier
> decision: if failures cluster by layer (generation vs publishing vs
> ingestion), a federated model per layer makes sense. If failures are
> more holistic and cross-cutting, a single supervisor with full
> visibility is simpler and avoids the coordination overhead of multiple
> supervisors. We commit to deciding this with data in Phase 2, not
> guessing now.
>
> **Q** *What gets logged so we can audit a decision after the fact?*
>
> **POSITION** Everything, already by design. The system_logs table
> specified in the main plan captures job_type, entity_id, status,
> message, and a full JSON payload for every pipeline stage outcome.
> Every quality gate result, every retry, every alert, every review
> decision is logged with enough detail to reconstruct exactly what
> happened and why. This was built as the foundation for Phase 2 AI
> supervision, not just Phase 1 debugging --- it is one system serving
> both purposes.

**Infrastructure Scaling**

> **Q** *What number of sites are we designing the first build to
> handle?*
>
> **POSITION** 60+ for Phase 1, architected to handle 500+ without a
> rewrite. Named queue channels and the interface-based adapter pattern
> get us there. We are not pre-optimising for 500+ now --- that would be
> over-engineering for a network we do not have yet --- but nothing in
> the Phase 1 design needs to be torn up to get there.
>
> **Q** *Where do we expect things to break first, compute or
> publishing?*
>
> **POSITION** Publishing, specifically credential failures across 60+
> WordPress and FTP destinations --- and this is the more dangerous
> failure mode because it is silent. Compute (LLM rate limits,
> generation queue depth) is the other developer\'s primary concern, and
> it is real, but compute failures are visible immediately --- a failed
> job shows up in the queue and alerts. A site that silently stops
> publishing because of an expired credential looks completely normal
> until someone checks. That asymmetry, not raw likelihood, is why
> publishing failure gets our primary attention in the alert design.
>
> **Q** *When do we move to self-hosted models versus paid APIs?*
>
> **POSITION** Decided by the Sprint 0 cost-comparison test specified in
> the Plan Addendum, not by assumption. The test runs free/self-hosted
> models (Llama, Mistral via Ollama) against paid models (GPT-4.1-mini,
> GPT-4.1, Claude Sonnet) on the same source articles, scored on the
> same quality gate. The likely outcome based on general LLM behaviour:
> self-hosted is viable now for high-volume, lower-stakes clusters,
> while paid models stay justified for Health and Satire where quality
> carries real risk. But this gets confirmed by the test, not asserted
> in this document.
>
> **Q** *What is the cost target per article and per site per month?*
>
> **POSITION** Set from the Sprint 0 comparison test result, with a
> safety margin, rather than picked in advance. Full method specified in
> the Plan Addendum. We are deliberately not putting a placeholder
> number here --- a number chosen before the test exists would just be a
> guess dressed up as a target.
>
> **Q** *How fast do we want a single article to move from request to
> published?*
>
> **POSITION** Under 10 minutes from RSS fetch to live, for
> automated-publishing sites --- which, per the addendum, is now most
> sites. The pipeline itself (fetch → generate → validate → schedule →
> publish) should complete in under 5 minutes of actual processing time;
> the rest of the window accounts for scheduling against daily publish
> limits, not pipeline latency. For sites still on human review (Health,
> Satire, new sites), the pipeline portion is the same --- review time
> is a separate, human-paced variable that we do not put a target on,
> since artificially rushing review undermines the reason those clusters
> require it.

**Reporting and Intelligence**

> **Q** *What is the one number that tells us the network is healthy?*
>
> **POSITION** Publish success rate across all sites in the last 24
> hours. If 95%+ of scheduled articles published successfully, the
> network is healthy. Below 90%, something needs attention. This single
> number is chosen deliberately because it is a lagging indicator of
> every upstream failure mode --- credential failures, generation
> failures, and quality gate failures all eventually show up here. It
> will be the single number on the pipeline dashboard (Section 7 of the
> main plan) given top placement.
>
> **Q** *What goes into the founders report versus the internal
> dashboard?*
>
> **POSITION** Founders report (weekly): total articles published,
> network-wide publish success rate, AI spend vs budget, top-performing
> sites by quality score. Internal dashboard (real-time): everything ---
> queue depth, failed jobs, per-site status, generation costs, quality
> trends, credential health. The founders report is a deliberately
> small, stable set of numbers; the internal dashboard is comprehensive
> and operational. This distinction matters because a founders report
> cluttered with operational detail gets ignored, and an internal
> dashboard stripped down to founder-level summary is useless for
> actually running the system.
>
> **Q** *How do we measure whether an author or a site is improving?*
>
> **POSITION** Rolling 30-article quality score trend, compared against
> the prior 30 --- already specified as the author drift detection
> mechanism above, and the same logic applies at the site level. A
> secondary signal: reduction in HOLD/FAIL flag rate over time. A site
> or author generating fewer flags month over month has a better-tuned
> DNA or persona, independent of raw quality score movement.
>
> **Q** *How fast do reports need to refresh? Real time, daily,
> monthly?*
>
> **POSITION** Three different cadences for three different audiences,
> not one answer. Operational dashboards (queue depth, failed jobs,
> per-site status): near real-time, under 1 minute, because these drive
> immediate action. Quality trends and cost reports: daily refresh,
> because these inform decisions made once a day at most. Founders
> report: weekly, because that is the cadence at which the
> founders-level numbers actually change meaningfully.
>
> **Q** *What insights should the system surface without being asked?*
>
> **POSITION** Five proactive signals, each already computable from data
> the schema captures: sites silent for 3+ days, sites with a 15%+
> quality score drop over 30 articles, clusters with a rising HOLD flag
> rate, authors or DNA profiles producing consistently below-average
> quality, and AI spend trending above the daily budget. None of these
> require a person to go looking --- they should appear on the dashboard
> unprompted, which is the actual point of building a dashboard rather
> than just a query tool.

**Monetisation and SEO**

> **Q** *How is each site supposed to make money?*
>
> **POSITION** Primarily affiliate links and promotional content
> injection via the promo_campaigns system already specified in the main
> plan. Ads are possible per site but configured, not hardcoded --- a
> site\'s monetisation approach is a DNA-level and campaign-level
> configuration choice, not a platform-wide assumption.
>
> **Q** *Can the system run monetisation experiments on its own?*
>
> **POSITION** Not in Phase 1. The promo engine runs date-based
> campaigns automatically once a human has defined them, but defining a
> new campaign or experiment requires a human. Automated A/B testing of
> promotional placement is a reasonable Phase 2 feature once we have
> enough traffic data (from SURFACE) to measure experiment results
> meaningfully --- running experiments with no measurement system
> attached is not useful.
>
> **Q** *What are the SEO guardrails we never want a site to cross?*
>
> **POSITION** Five hard rules, all already enforced structurally by the
> quality gate in the main plan, restated here as a direct standalone
> answer to this question: no keyword stuffing (similarity check), no
> duplicate content across the network (content_hash dedup), no thin
> content under 400 words (word count check), no publishing at a
> spam-like rate (max_posts_per_day enforcement), and no medical claims
> without disclosure (Health cluster filter). These are code-level
> checks, not prompt instructions, because SEO penalties are exactly the
> kind of consequence that justifies a hard rule over a soft suggestion.
>
> **Q** *How do we handle topics where two sites overlap?*
>
> **POSITION** (Answered above under Site Intelligence --- differentiate
> at the DNA level via angle and audience, not by avoiding topic
> overlap.)

**Deployment and Templates**

> **Q** *How many site templates do we want to launch with?*
>
> **POSITION** Not applicable to this phase. All 60+ sites already exist
> with their own templates and visual identity --- this phase does not
> create new sites or new templates. The template question becomes
> relevant in Phase 2 when site creation is back in scope. Flagging this
> explicitly rather than leaving it silent: this is correctly out of
> scope, not an oversight.
>
> **Q** *Should two sites ever look exactly the same, or always
> different?*
>
> **POSITION** Not applicable to this phase for the same reason ---
> visual identity for the existing 60+ sites is already set. When site
> creation returns in Phase 2, our position carries over from the
> original recommendation: shared base themes within a cluster are fine,
> but content and voice should always differ via Site DNA regardless of
> visual similarity.
>
> **Q** *How are credentials stored for dozens or hundreds of
> destinations?*
>
> **POSITION** Already fully specified in the main plan: the credentials
> table with Laravel Crypt encryption for usernames and secrets, plain
> text for host and port, and a daily verification check across all
> active credentials. Restated as a direct answer since the client asked
> it explicitly: this scales to hundreds of destinations without any
> structural change --- it is one row per credential regardless of
> count.
>
> **Q** *What happens when a destination is offline or broken?*
>
> **POSITION** Already specified per-adapter retry logic in the main
> plan: WordPress retries 4 times with exponential back-off, FTP retries
> 3 times. After final failure, the job is marked failed, the operator
> is alerted, and publishing for that site halts until the credential or
> connectivity issue is confirmed resolved. We do not keep retrying
> indefinitely --- that wastes queue capacity and delays detection of a
> real problem.
>
> **Q** *Who can spin up a new site, only the L1 team or also the
> customer?*
>
> **POSITION** L1 team only in Phase 1, and this phase does not include
> site creation at all --- so the question is moot for the current scope
> but worth answering for the roadmap. Partner login (clients viewing
> their own site stats) is Phase 2. Giving partners the ability to
> create sites themselves is Phase 3 at the earliest --- the system
> needs to be proven stable on L1-managed sites before any external
> party gets write access to it.

*Lattis One --- ATLAS \| Open Questions --- Full Response \| Phase 1*
