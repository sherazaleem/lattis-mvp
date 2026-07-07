**Lattis One --- ATLAS**

Plan Addendum

*Positions on the three open items from the client planning page*

To be read alongside: ATLAS Content Generation & Publishing Plan, Phase
1

> **PURPOSE OF THIS ADDENDUM** The client planning page raises several
> open questions and one stated principle our plan diverges from. This
> document gives a direct, reasoned position on each --- not as an
> afterthought, but as a deliberate engineering decision the other
> developer and the client can review before the meeting.

**1. Where This Plan Diverges From Stated Direction**

**Position: Automated publishing is the default. No AI Project Manager
in Phase 1.**

The client\'s Principle 07 states \"minimal human bottlenecks\" and \"no
required manual review at scale.\" Our updated position aligns with this
directly rather than defaulting to human review as the original plan
proposed.

> **REVISED DEFAULT** auto_publish = true is now the default for every
> site, not the exception. Human review becomes the opt-in exception,
> reserved only for clusters where the engineering risk genuinely
> requires it --- Health (medical claims, FTC disclosure) and Satire
> (legal and brand risk). Every other cluster --- Technology, Marketing,
> Local, Lifestyle, Retail, Travel, Charity, Sports --- publishes
> automatically once an article passes the quality gate with no HOLD
> flags.

**Why this is the right engineering decision, not just compliance with
the client\'s preference**

-   The quality gate already does the job a human reviewer would do for
    routine content. Ten specific checks --- word count, duplicate
    paragraphs, source similarity, forbidden topics, promo density, SEO
    field completeness --- catch the failure modes that matter. A human
    reading every article adds latency and cost without meaningfully
    improving on what the code already checks.

-   The HOLD mechanism already exists in our design and gives us a clean
    way to keep humans in the loop exactly where it matters --- medical
    claims and satire --- without making review the default everywhere
    else. This is not a compromise. It is the correct use of the
    mechanism we already built.

-   Minimal human bottlenecks does not mean zero human oversight. It
    means oversight is targeted at genuine risk, not applied uniformly
    regardless of risk. Our quality gate plus targeted HOLD routing
    achieves exactly that.

**Why no AI Project Manager in Phase 1 is still correct under this new
default**

Removing the human review default does not change the case against
building an AI Project Manager supervisor layer in Phase 1. The
reasoning is different from before, but the conclusion is the same:

-   An AI supervisor needs operational history to make good decisions.
    In Phase 1, the system has zero history. Building a supervisor
    before there is data for it to learn from means it is making
    decisions blind --- which is a worse outcome than the rule-based
    alerts we are already building.

-   The rule-based alert system in this plan (8 specific alerts in
    Section 9 of the main plan) is not a placeholder for the AI Project
    Manager --- it is the data source the AI Project Manager will
    eventually need. Every alert fired, every failure logged, every
    quality score recorded becomes training signal for Phase 2
    supervision.

-   With auto_publish as the default, the alert system becomes more
    important, not less. Since articles are not passing through a human
    gate before publishing, the alerts are the only thing standing
    between a quality problem and 60+ live sites. This is exactly why
    Section 9 specifies CRITICAL priority on the silent-failure alerts.

**Revised flow with auto-publish as default**

  ---------- -------------------------------------------------------------
  **Step**   **What Happens**

  1          Article generated, runs through 10-check quality gate

  2          No HOLD flag, no FAIL → status moves directly to approved, no
             human involved

  3          HOLD flag raised (Health/Satire only) → routes to human
             review queue regardless of cluster default

  4          FAIL → article rejected automatically, logged, never reaches
             review or publish

  5          Approved article scheduled and published automatically

  6          All outcomes logged to system_logs for future AI Project
             Manager training data
  ---------- -------------------------------------------------------------

**2. Domain Structure and Cost Target**

**2.1 --- Domain vs subdomain**

The client\'s question (Monetisation & SEO Q4) asks directly: one main
domain with subdomains, or many separate domains?

> **POSITION** Separate domains. This is already how the 60+ live sites
> are structured, so there is no migration question --- but it is worth
> stating explicitly as the deliberate choice going forward for any
> future site as well.

**2.2 --- Cost per article target**

The client\'s question (Infrastructure Scaling Q4) asks directly: what
is the cost target per article and per site per month? Rather than
picking a number in the abstract, our position is to determine the real
number through a structured test, not a guess.

> **PROPOSED APPROACH** Run the same batch of source articles through
> multiple LLM options --- a mix of free/open-source models and paid
> commercial models --- generate output for each, and compare actual
> quality against actual cost. Set the budget based on what the data
> shows, not before we have it.

**The comparison test --- what we run in Sprint 0 or early Sprint 3**

  ---------------- --------------- ------------------ ----------------------
  **Model**        **Type**        **Approx. Cost per **What We Are
                                   Article**          Testing**

  Llama 3.1 / 3.3  Free ---        \$0 per call,      Baseline quality with
  (self-hosted via hosting cost    hosting cost       zero per-token cost.
  Ollama)          only            amortised          Viable for
                                                      high-volume,
                                                      lower-risk clusters if
                                                      quality holds up.

  Mistral          Free ---        \$0 per call,      Second free option for
  (self-hosted via hosting cost    hosting cost       comparison ---
  Ollama)          only            amortised          different model
                                                      family, different
                                                      strengths.

  GPT-4.1-mini     Paid --- low    \~\$0.003--0.006   Cheapest paid tier.
                   cost            per article        Tests whether a budget
                                                      model is good enough
                                                      for high-volume
                                                      clusters.

  GPT-4.1          Paid --- mid    \~\$0.015--0.022   Mid-tier paid quality
                   cost            per article        benchmark, used in
                                                      original cost model.

  Claude Sonnet    Paid --- higher \~\$0.03--0.04 per Top-tier quality
  4.6              cost            article            benchmark --- useful
                                                      for sensitive clusters
                                                      like Health and Satire
                                                      where quality matters
                                                      more than cost.
  ---------------- --------------- ------------------ ----------------------

**How we run the test**

-   Select 5 representative source articles spanning at least 3 clusters
    (e.g. Technology, Health, Lifestyle).

-   Generate one article per model per source article using identical
    Site DNA and Author Persona inputs.

-   Score every output against the same 10-point quality gate, plus a
    manual read-through for tone consistency and obvious AI artifacts.

-   Record actual token usage and actual cost per article per model.

-   Compare quality score against cost per article model by model ---
    find the point where paying more stops improving quality
    meaningfully.

**What this gives us**

A real cost-per-article number backed by actual output quality, not an
assumption. Likely outcome based on general LLM behaviour:
free/self-hosted models are usable for high-volume, lower-stakes
clusters (Sports, Local, Retail) where consistency matters more than
peak quality, while paid models remain justified for Health and Satire
where output quality carries real legal and brand risk. The
AIProviderInterface we have already designed makes this a config
decision per cluster, not a rebuild.

**Setting the budget**

-   Run the comparison test in Sprint 0, before Sprint 3 generation work
    begins, so the result informs which providers get implemented first.

-   Set an initial daily AI spend threshold (Alert 6 in Section 9 of the
    main plan) based on the test result, multiplied by 60+ sites at 1
    article per day, with a safety margin.

-   Revisit the budget after the Sprint 3 mini-pilot and the Sprint 8
    expanded pilot, once we have real production cost data rather than
    test data.

**3. DEC 04 --- AI Project Manager: Deferred by Design**

> **POSITION FOR DEC 04** Deferred to Phase 2 by design, not omitted by
> oversight. Rules-based alerting and full audit logging are built in
> Phase 1 specifically because they are the prerequisite for a useful AI
> Project Manager later --- not because the concept is rejected.

The one-paragraph case: an AI Project Manager is a supervisory agent
that needs signal to supervise. In Phase 1, the network has no
publishing history, no quality score trends, no failure patterns, and no
baseline for what \"normal\" looks like across 60+ sites. Asking an AI
supervisor to detect anomalies and make workload decisions with zero
historical data means it is either doing nothing useful or making
decisions on guesswork --- neither of which is better than the
rule-based alert system we are already building. So Phase 1 builds the
foundation the AI Project Manager will eventually stand on: every job
logged, every quality score recorded, every alert condition tracked in
system_logs with full payload detail. Phase 2 introduces the AI Project
Manager once there are months of real operational data for it to reason
over, at which point it can meaningfully do what Principle 05 describes
--- detect anomalies, balance workload, and escalate --- because it has
something real to learn from. This is not a rejection of the client\'s
direction. It is sequencing the same destination correctly.

**What this means concretely for DEC 04 in the planning document**

  ------------------ ----------------------------------------------------
  **Field**          **Value**

  Priority           P2 --- decide before Sprint 3 (not P0, since Phase 1
                     does not require it)

  Estimated Effort   XL (sprint or more) --- once started in Phase 2

  Dependencies       Requires Phase 1 operational data: system_logs
                     history, quality score trends, alert frequency data

  Risk if deferred   None for Phase 1. Risk is acceptable because alert
                     system in Section 9 covers the same failure modes
                     manually.

  Risk if built now  High --- building a supervisor with no data to learn
                     from likely produces unreliable decisions,
                     undermining trust in AI supervision before it has a
                     chance to prove itself.

  Notes from         Single vs federated supervisor question becomes
  discussion         meaningfully answerable only once we know actual
                     failure patterns across clusters --- premature to
                     decide now.
  ------------------ ----------------------------------------------------

*Lattis One --- ATLAS \| Plan Addendum \| Phase 1*
