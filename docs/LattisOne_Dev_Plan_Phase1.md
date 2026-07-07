**Lattis One --- ATLAS**

Content Generation & Publishing Plan

*Phase 1 --- Getting content flowing to the 60+ existing websites*

Stack: Laravel · Inertia.js · MariaDB · MongoDB · Redis · Laravel
Horizon

> **SCOPE OF THIS DOCUMENT** This plan covers one thing: generating
> content from RSS feeds and publishing it to the 60+ websites that
> already exist. Site creation, templates, SURFACE analytics, and the AI
> Project Manager layer are out of scope here. We build this first, get
> it stable, then expand. *See the companion Plan Addendum for our
> position on auto-publish defaults, AI Project Manager sequencing (DEC
> 04), domain structure, and the cost-per-article comparison test.*

**1. What We Are Building**

The 60+ websites are live. They have Site DNA profiles and Author
Personas configured. They need content. The system we are building in
this phase does one job: take articles from RSS feeds, transform them
using each site\'s identity, and publish the output to the correct
website automatically.

The pipeline runs in the background continuously. No human needs to be
involved for sites that are configured for automated publishing. Sites
that require human review hold articles in a queue until an editor acts
on them.

**The pipeline --- nine stages end to end**

  ----------- --------------- ------------ ---------------------------------------
  **Stage**   **Name**        **Type**     **Description**

  1           RSS Fetcher     Async Job    Fetch articles from configured RSS
                                           feeds on a schedule. Store raw items.

  2           Duplicate       Inline       SHA-256 hash check. Skip anything
              Detector                     already seen. Runs inside Stage 1 job.

  3           Prompt Builder  Inline       Load Site DNA + Author Persona.
                                           Assemble the LLM prompt. No external
                                           calls.

  4           AI Generator    Async Job    Call the LLM via AIProviderInterface.
                                           Store raw output with full metadata.

  5           Output          Inline       Run all quality gate checks. FAIL,
              Validator                    HOLD, or PASS the article. Runs inside
                                           Stage 4 job.

  6           SEO Field       Async Job    Generate title, slug, meta description,
              Generator                    FAQ schema, image alt text.

  7           Promo Injector  Inline       Insert promotional content per active
                                           campaign rules. Soft --- never blocks.

  8           Scheduler       Scheduled    Assign publish datetime to approved
                              Command      articles. Respects site daily limits.

  9           Publisher       Async Job    Push to WordPress REST API or FTP.
                                           Retry on failure. Log everything.
  ----------- --------------- ------------ ---------------------------------------

**2. Database Design**

**Two databases --- what goes where**

> **DECISION** MariaDB stores all structured operational data --- sites,
> jobs, statuses, credentials, logs. MongoDB stores all content data ---
> article bodies, author persona documents, and prompt templates. These
> two never mix. A MariaDB record holds the article ID and status.
> MongoDB holds the actual text.

**MariaDB Tables**

All relational, operational, and credential data lives here. Schema must
be finalised and migrated before Sprint 1 begins.

**sites**

  ------------------- ------------------ ------------------------------------
  **Column**          **Type**           **Purpose**

  id                  BIGINT PK          Primary key

  domain              VARCHAR(255)       The website domain e.g. example.com
                      UNIQUE             

  stack_type          ENUM(wordpress,    Determines which publishing adapter
                      ftp_html)          is used

  cluster_id          BIGINT FK →        Which cluster this site belongs to
                      niche_clusters     

  max_posts_per_day   INT DEFAULT 1      Publishing rate limit per site

  timezone            VARCHAR(64)        Site timezone for scheduled
                                         publishing

  auto_publish        BOOLEAN DEFAULT    Default is automated publishing. Set
                      true               to false only for Health, Satire, or
                                         sites under initial review (per Plan
                                         Addendum).

  cms_api_url         VARCHAR(255)       WordPress REST API base URL

  language            VARCHAR(10)        Content language
                      DEFAULT en         

  deployment_state    JSON               last_published_at,
                                         credential_status,
                                         health_check_status, total_published

  is_active           BOOLEAN DEFAULT    Whether the site is currently
                      true               publishing

  created_at /        TIMESTAMPS         Standard timestamps
  updated_at                             
  ------------------- ------------------ ------------------------------------

**site_dna**

  -------------------- ------------------ ------------------------------------
  **Column**           **Type**           **Purpose**

  id                   BIGINT PK          Primary key

  site_id              BIGINT FK → sites  One DNA per site
                       UNIQUE             

  niche                VARCHAR(255)       The topic domain --- technology,
                                          health, marketing, etc.

  angle                VARCHAR(100)       Editorial angle --- industry_shift,
                                          case_study, customer_pain, faq,
                                          event_guide

  audience             TEXT               Free text description of who the
                                          site writes for

  format_rules         JSON               Array of structural rules ---
                                          paragraph length, subheadings,
                                          bullets

  forbidden_topics     JSON               Array of topics this site never
                                          covers

  cta_style            VARCHAR(100)       soft_suggest, direct_action,
                                          newsletter, product_link

  ai_aggressiveness    TINYINT DEFAULT 3  1=minimal rewrite, 5=fully original.
                                          Controls LLM expansion beyond
                                          source.

  seo_posture          JSON               Keyword density rules, internal
                                          linking preferences

  monetisation_rules   JSON               Affiliate placement, promo density,
                                          ad zones

  prompt_fragment      TEXT               Site-specific section added to every
                                          prompt for this site

  version              INT DEFAULT 1      Increments on every change. Stored
                                          on generated articles for audit.

  created_at /         TIMESTAMPS         Standard timestamps
  updated_at                              
  -------------------- ------------------ ------------------------------------

**author_assignments**

Links authors (stored in MongoDB) to sites. An author can write for
multiple sites. A site can have multiple authors.

  ----------------- ------------------ ------------------------------------
  **Column**        **Type**           **Purpose**

  id                BIGINT PK          Primary key

  site_id           BIGINT FK → sites  Which site

  author_mongo_id   VARCHAR(255)       MongoDB ObjectId of the author
                                       persona document

  is_primary        BOOLEAN DEFAULT    Primary author for this site, or
                    true               guest contributor

  is_active         BOOLEAN DEFAULT    Whether this author is currently
                    true               assigned

  created_at        TIMESTAMP          When the assignment was made
  ----------------- ------------------ ------------------------------------

**niche_clusters**

  --------------------------- ------------------ ------------------------------------
  **Column**                  **Type**           **Purpose**

  id                          BIGINT PK          Primary key

  name                        VARCHAR(100)       Human-readable name --- Technology,
                                                 Health, Marketing, etc.

  slug                        VARCHAR(100)       Machine identifier --- technology,
                              UNIQUE             health, marketing

  review_level                ENUM(standard,     standard=auto possible, high=prefer
                              high, mandatory)   review, mandatory=always review

  default_max_posts_per_day   INT DEFAULT 1      Default publishing rate for new
                                                 sites in this cluster

  content_filter_config       JSON               Cluster-specific quality gate rules
                                                 and thresholds

  created_at / updated_at     TIMESTAMPS         Standard timestamps
  --------------------------- ------------------ ------------------------------------

**credentials**

  ------------------- ------------------ ------------------------------------
  **Column**          **Type**           **Purpose**

  id                  BIGINT PK          Primary key

  site_id             BIGINT FK → sites  Which site these credentials belong
                                         to

  adapter_type        ENUM(wordpress,    Which adapter uses these credentials
                      ftp_html)          

  host                VARCHAR(255)       Server address --- plain text, not
                                         sensitive

  port                INT                Server port --- plain text, not
                                         sensitive

  username            TEXT               Encrypted via Laravel
                                         Crypt::encryptString()

  secret              TEXT               API key or password --- Encrypted
                                         via Laravel Crypt::encryptString()

  credential_status   ENUM(active,       Updated by daily health check
                      failed,            
                      unverified)        

  last_verified_at    TIMESTAMP NULL     When credentials were last confirmed
                                         working

  created_at /        TIMESTAMPS         Standard timestamps
  updated_at                             
  ------------------- ------------------ ------------------------------------

**rss_sources**

  ------------------------- ------------------ ------------------------------------
  **Column**                **Type**           **Purpose**

  id                        BIGINT PK          Primary key

  feed_url                  VARCHAR(500)       The RSS feed URL
                            UNIQUE             

  cluster_id                BIGINT FK →        If cluster-level, all sites in the
                            niche_clusters     cluster use this feed
                            NULL               

  site_id                   BIGINT FK → sites  If site-specific, only this site
                            NULL               uses this feed

  fetch_frequency_minutes   INT DEFAULT 60     How often to fetch this feed

  priority                  TINYINT DEFAULT 5  1=highest, 10=lowest. Controls fetch
                                               order.

  language                  VARCHAR(10)        Feed language
                            DEFAULT en         

  is_active                 BOOLEAN DEFAULT    Whether to fetch this feed
                            true               

  status                    ENUM(active,       Updated when fetch fails repeatedly
                            errored)           

  last_fetched_at           TIMESTAMP NULL     When this feed was last successfully
                                               fetched

  created_at / updated_at   TIMESTAMPS         Standard timestamps
  ------------------------- ------------------ ------------------------------------

**rss_items**

  ------------------- ------------------ ------------------------------------
  **Column**          **Type**           **Purpose**

  id                  BIGINT PK          Primary key

  source_id           BIGINT FK →        Which feed this came from
                      rss_sources        

  url                 VARCHAR(500)       Original article URL

  title               VARCHAR(500)       Article title as fetched

  body_html           LONGTEXT           Raw article body HTML as fetched

  published_at        TIMESTAMP NULL     Original publication date from feed

  fetched_at          TIMESTAMP          When we fetched this item

  content_hash        CHAR(64) UNIQUE    SHA-256 of url+title. Deduplication
                                         key. Non-negotiable.

  source_word_count   INT                Word count of source article. Items
                                         under 300 words are skipped.

  is_processed        BOOLEAN DEFAULT    Has this item been sent to
                      false              generation

  is_duplicate        BOOLEAN DEFAULT    Was this item rejected as a
                      false              duplicate

  created_at          TIMESTAMP          Standard timestamp
  ------------------- ------------------ ------------------------------------

**generated_articles**

MariaDB stores metadata and status. MongoDB stores the actual article
body. The mongo_id column links them.

  ----------------- ---------------------------------------------------------------------------------------------------------- ------------------------------------
  **Column**        **Type**                                                                                                   **Purpose**

  id                BIGINT PK                                                                                                  Primary key

  rss_item_id       BIGINT FK → rss_items                                                                                      Source article this was generated
                                                                                                                               from

  site_id           BIGINT FK → sites                                                                                          Which site this article is for

  author_mongo_id   VARCHAR(255)                                                                                               Which author persona produced this

  mongo_id          VARCHAR(255)                                                                                               MongoDB ObjectId of the full article
                                                                                                                               document

  status            ENUM(queued,generating,generated,review,approved,scheduled,publishing,published,failed,rejected,skipped)   Current state

  quality_score     TINYINT NULL                                                                                               0-100 composite quality score from
                                                                                                                               validation

  quality_flags     JSON NULL                                                                                                  Array of flags --- FAIL reasons and
                                                                                                                               HOLD reasons

  prompt_version    INT                                                                                                        Which DNA version produced this. For
                                                                                                                               quality tracking.

  author_version    INT                                                                                                        Which author persona version
                                                                                                                               produced this

  model_used        VARCHAR(100)                                                                                               e.g. gpt-4.1, claude-sonnet-4-6

  provider          VARCHAR(50)                                                                                                openai, claude, ollama

  tokens_input      INT                                                                                                        Input tokens used. For cost
                                                                                                                               tracking.

  tokens_output     INT                                                                                                        Output tokens used. For cost
                                                                                                                               tracking.

  generation_ms     INT                                                                                                        How long the LLM call took in
                                                                                                                               milliseconds

  reviewed_by       BIGINT FK → users NULL                                                                                     Which editor reviewed this

  reviewed_at       TIMESTAMP NULL                                                                                             When it was reviewed

  scheduled_at      TIMESTAMP NULL                                                                                             When it is scheduled to publish

  published_at      TIMESTAMP NULL                                                                                             When it actually published

  created_at /      TIMESTAMPS                                                                                                 Standard timestamps
  updated_at                                                                                                                   
  ----------------- ---------------------------------------------------------------------------------------------------------- ------------------------------------

**publish_jobs**

  ---------------- -------------------- ------------------------------------
  **Column**       **Type**             **Purpose**

  id               BIGINT PK            Primary key

  article_id       BIGINT FK →          Which article to publish
                   generated_articles   

  site_id          BIGINT FK → sites    Which site to publish to

  adapter_type     ENUM(wordpress,      Which adapter to use
                   ftp_html)            

  status           ENUM(scheduled,      Current job state
                   publishing,          
                   published, failed)   

  attempts         TINYINT DEFAULT 0    Number of publish attempts made

  last_error       TEXT NULL            Most recent error message

  cms_post_id      VARCHAR(255) NULL    ID returned by WordPress or filename
                                        for FTP

  published_url    VARCHAR(500) NULL    Live URL of the published article

  scheduled_at     TIMESTAMP            When this job should run

  published_at     TIMESTAMP NULL       When it actually ran successfully

  created_at /     TIMESTAMPS           Standard timestamps
  updated_at                            
  ---------------- -------------------- ------------------------------------

**system_logs**

  ---------------- ------------------ ------------------------------------
  **Column**       **Type**           **Purpose**

  id               BIGINT PK          Primary key

  job_type         VARCHAR(100)       rss_fetch, ai_generation,
                                      quality_gate, publishing,
                                      credential_check

  entity_id        BIGINT NULL        ID of the related article, job,
                                      feed, or site

  entity_type      VARCHAR(100) NULL  generated_articles, publish_jobs,
                                      rss_sources, sites

  status           ENUM(success,      Outcome of this log entry
                   failed, skipped,   
                   warning)           

  message          TEXT               Human-readable description of what
                                      happened

  payload          JSON NULL          Full technical detail --- error
                                      messages, metrics, prompt excerpt

  created_at       TIMESTAMP          When this log entry was created
  ---------------- ------------------ ------------------------------------

**promo_campaigns**

  ----------------- ------------------ ------------------------------------
  **Column**        **Type**           **Purpose**

  id                BIGINT PK          Primary key

  name              VARCHAR(255)       Campaign name

  product           VARCHAR(255)       Product or service being promoted

  keywords          JSON               Array of keywords to inject

  links             JSON               Array of URLs to insert

  target_clusters   JSON               Array of cluster IDs this campaign
                                       applies to

  target_sites      JSON               Array of specific site IDs,
                                       overrides cluster targeting

  placement_rules   JSON               intro, mid, end, max_per_article,
                                       avoid_consecutive

  start_date        DATE               When campaign becomes active

  end_date          DATE NULL          When campaign ends. NULL = runs
                                       until deactivated.

  is_active         BOOLEAN DEFAULT    Master on/off switch
                    true               

  created_at /      TIMESTAMPS         Standard timestamps
  updated_at                           
  ----------------- ------------------ ------------------------------------

**MongoDB Collections**

Content and identity data lives here. Retrieved as whole documents. No
joins required.

**author_personas collection**

> {
>
> \"\_id\": ObjectId,
>
> \"name\": \"Jordan Reid\",
>
> \"version\": 3,
>
> \"tone\": \"skeptical and direct\",
>
> \"vocabulary_level\": \"technical\",
>
> \"sentence_style\": \"short declarative sentences\",
>
> \"opens_with\": \"challenge to conventional wisdom\",
>
> \"closes_with\": \"one actionable takeaway\",
>
> \"forbidden_phrases\": \[\"it is worth noting\", \"in conclusion\"\],
>
> \"sites\": \[\"site_007\", \"site_012\"\],
>
> \"memory\": {
>
> \"recent_topics\": \[\"AI regulation\", \"startup funding\"\],
>
> \"recent_summaries\": \[\"Argued AI regulation moves too slowly\",
> \...\],
>
> \"running_themes\": \[\"institutional distrust\", \"practical over
> theoretical\"\]
>
> },
>
> \"prompt_fragment\": \"Write as Jordan Reid, a skeptical technology
> journalist\...\",
>
> \"example_output\": \"Full example article demonstrating the
> voice\...\",
>
> \"created_at\": ISODate,
>
> \"updated_at\": ISODate
>
> }
>
> **MEMORY RULE** The memory object is updated automatically after each
> article publishes. The system appends a one-sentence summary to
> recent_summaries (max 10 entries, oldest dropped). recent_topics is
> updated with the article topic. Humans never write to memory directly
> --- they can reset or review it, but the system maintains it.

**articles collection**

> {
>
> \"\_id\": ObjectId,
>
> \"mariadb_id\": 12345,
>
> \"site_id\": 7,
>
> \"author_id\": ObjectId,
>
> \"title\": \"Why AI Regulation Is Failing Everyone\",
>
> \"body_html\": \"\<p\>Full article HTML\...\</p\>\",
>
> \"seo_title\": \"AI Regulation Failures: What Nobody Is Saying\",
>
> \"slug\": \"ai-regulation-failures\",
>
> \"meta_description\": \"160 character meta\...\",
>
> \"focus_keyword\": \"AI regulation\",
>
> \"faq_schema\": \[{\"question\": \"\...\", \"answer\": \"\...\"}\],
>
> \"image_alt_text\": \"Descriptive alt text for featured image\",
>
> \"prompt_used\": \"Full system prompt that produced this
> article\...\",
>
> \"source_url\": \"https://original-rss-source.com/article\",
>
> \"source_summary\": \"One paragraph summary of the source article\",
>
> \"created_at\": ISODate
>
> }

**3. The Three PHP Interfaces**

These must be defined and agreed before any generation or publishing
code is written. They are the contracts that allow the system to grow
without rewrites.

**AIProviderInterface**

> interface AIProviderInterface
>
> {
>
> public function generate(PromptPayload \$prompt): GenerationResult;
>
> public function supportsModel(string \$modelName): bool;
>
> public function getProviderName(): string;
>
> public function getRateLimit(): RateLimitInfo;
>
> }

Phase 1 implementations: OpenAIProvider, ClaudeProvider, OllamaProvider
(open source fallback). Adding a new provider = new class, zero changes
to generation jobs.

**PublishingAdapterInterface**

> interface PublishingAdapterInterface
>
> {
>
> public function publish(ArticlePayload \$article, Site \$site):
> PublishResult;
>
> public function unpublish(string \$externalId, Site \$site): bool;
>
> public function verifyCredentials(Site \$site): CredentialCheckResult;
>
> public function getStatus(string \$externalId, Site \$site):
> PostStatus;
>
> public function getSupportedStackType(): string;
>
> }

Phase 1 implementations: WordPressAdapter, FtpHtmlAdapter. Adding Ghost,
Shopify, or anything else = new class, zero changes to publish engine.

**ContentFilterInterface**

> interface ContentFilterInterface
>
> {
>
> public function check(ArticlePayload \$article, Site \$site):
> FilterResult;
>
> public function getFilterName(): string;
>
> public function appliesTo(string \$clusterSlug): bool;
>
> }

Phase 1 implementations: WordCountFilter, DuplicateParagraphFilter,
ForbiddenTopicFilter, SimilarityFilter, MedicalClaimFilter (Health
cluster), SatireReviewFilter (Satire cluster), PromoDensityFilter,
SeoFieldsFilter. Adding a new rule = new class, zero changes to
OutputValidator.

**4. Pipeline Stage Contracts**

Every stage has a defined input, expected output, and failure behaviour.
These are the contracts between stages.

**Stage 1 --- RSS Fetcher**

  ------------ ----------------------------------------------------------
  **INPUT**    RssSource record: {id, feed_url, fetch_frequency_minutes,
               cluster_id, site_id, priority}

  **OUTPUT**   RssItem records written to MariaDB: {source_id, url,
               title, body_html, fetched_at, content_hash,
               source_word_count, is_processed=false, is_duplicate=false}

  **ON         3 attempts with exponential back-off (30s, 90s, 270s). On
  FAILURE**    final failure: mark rss_sources.status=errored, log to
               system_logs, alert if errored for 24h+, continue other
               feeds normally.
  ------------ ----------------------------------------------------------

**Stage 2 --- Duplicate Detector**

  ------------ ----------------------------------------------------------
  **INPUT**    RssItem with content_hash populated. Hash = SHA-256(url +
               title).

  **OUTPUT**   Unique: is_duplicate=false → eligible for generation.
               Duplicate: is_duplicate=true, is_processed=true → item is
               skipped. Log duplicate count per feed per run.

  **ON         No external calls. On DB error: log, leave item as
  FAILURE**    is_processed=false, retry on next ingestion cycle.
  ------------ ----------------------------------------------------------

**Stage 3 --- Prompt Builder**

  ------------ ----------------------------------------------------------
  **INPUT**    RssItem (source facts) + SiteDna from MariaDB +
               AuthorPersona document from MongoDB + cluster config.
               Source must be ≥ 300 words or is skipped.

  **OUTPUT**   PromptPayload object assembled in memory: {system_prompt
               (DNA fragment + author fragment combined), user_message
               (source facts), model, max_tokens}. Not sent to LLM here
               --- returned to Stage 4.

  **ON         If SiteDna missing or author persona missing: create
  FAILURE**    GeneratedArticle with status=failed, log DNA or persona
               config error, alert operator. Do not call the LLM.
  ------------ ----------------------------------------------------------

**Stage 4 --- AI Generator**

  ------------ ----------------------------------------------------------
  **INPUT**    PromptPayload from Stage 3 + site_id + rss_item_id +
               author_mongo_id + provider name.

  **OUTPUT**   MongoDB article document created with body_html,
               prompt_used, source_summary. MariaDB GeneratedArticle
               record created with status=generating, model_used,
               provider, tokens_input, tokens_output, generation_ms.

  **ON         Attempt 1: standard call. Timeout or 5xx: wait 30s, retry.
  FAILURE**    Rate limit (429): back off per Retry-After header, requeue
               --- not counted as a retry. Content policy rejection:
               status=failed, quality_flags=\[content_policy\], route to
               human review, do not retry. After 2 failures:
               status=failed, log full prompt and error, alert.
  ------------ ----------------------------------------------------------

**Stage 5 --- Output Validator**

  ------------ ----------------------------------------------------------
  **INPUT**    GeneratedArticle MariaDB record + MongoDB article body +
               SiteDna (forbidden_topics, min_word_count) + cluster
               content_filter_config.

  **OUTPUT**   All checks pass: status=generated, quality_score set,
               quality_flags=\[\]. Any FAIL check: status=failed,
               quality_flags=\[list of failed checks\], logged --- does
               not proceed. Any HOLD check:
               quality_flags=\[hold:reason\], article proceeds to review
               queue with flag visible to editor.

  **ON         Pure computation --- no external calls. On DB write
  FAILURE**    failure: retry 3 times. If DB still unavailable: log
               critical error, halt generation queue workers.
  ------------ ----------------------------------------------------------

**Stage 6 --- SEO Field Generator**

  ------------ ----------------------------------------------------------
  **INPUT**    MongoDB article body (validated text) + site_id + cluster
               tone context.

  **OUTPUT**   MongoDB article document updated with: seo_title, slug,
               meta_description (≤160 chars), focus_keyword, faq_schema
               (JSON array), image_alt_text. All fields must be non-empty
               on success.

  **ON         2 attempts with 15s fixed delay. On failure: add
  FAILURE**    quality_flag seo_generation_failed, leave article in
               review queue so editor can complete SEO fields manually.
               Never reject an article for SEO generation failure.
  ------------ ----------------------------------------------------------

**Stage 7 --- Promo Injector**

  ------------ ----------------------------------------------------------
  **INPUT**    MongoDB article body (validated + SEO fields set) + active
               PromoRules matching site_id and cluster_id and current
               date.

  **OUTPUT**   MongoDB article body updated with promo content inserted
               at configured positions. Log: {article_id, campaign_id,
               placement, injection_success}. If no active campaign
               matches: article passes through unchanged.

  **ON         2 attempts with 10s delay. On any failure: publish article
  FAILURE**    without promo content, log injection_failure. Promo
               failure NEVER rejects or holds an article.
  ------------ ----------------------------------------------------------

**Stage 8 --- Scheduler**

  ------------ ----------------------------------------------------------
  **INPUT**    GeneratedArticle records with status=approved. Site
               config: max_posts_per_day, timezone,
               preferred_publish_times.

  **OUTPUT**   PublishJob record created in MariaDB: {article_id,
               site_id, adapter_type from site.stack_type, scheduled_at,
               status=scheduled}. Respects daily limit --- oldest
               approved article gets next available slot.

  **ON         No external calls. If site is at daily limit: defer to
  FAILURE**    next available day, log deferral. Never drop an approved
               article.
  ------------ ----------------------------------------------------------

**Stage 9 --- Publisher**

  ------------ ----------------------------------------------------------
  **INPUT**    PublishJob record + MariaDB GeneratedArticle (status,
               site, author) + MongoDB article document (body, SEO
               fields). Site credentials decrypted in memory only at
               moment of use.

  **OUTPUT**   Success: PublishJob.status=published, cms_post_id set,
               published_url set. GeneratedArticle.status=published. Site
               deployment_state.last_published_at updated. Author persona
               memory updated with one-sentence article summary.

  **ON         4 attempts with exponential back-off (30s, 90s, 270s,
  FAILURE**    810s). Credential failure (401/403): mark site
               credential_status=failed, halt all publishing for that
               site, alert immediately --- do not retry. After 4
               failures: PublishJob.status=failed, alert if 3+
               consecutive failures for that site.
  ------------ ----------------------------------------------------------

**5. Quality Gate --- All 10 Checks**

These run in sequence inside Stage 5. FAIL stops the article. HOLD lets
it proceed to review with a flag. SKIP marks the source item and stops
processing.

  --------------- ------------------ ---------------- ------------ ----------------
  **Check**       **Method**         **Trigger**      **Result**   **Action**

  Word count      Count words in     Below site DNA   FAIL         Reject article,
                  generated body     min_word_count                mark failed, log
                                     (default 400)                 reason

  Duplicate       Hash each          Any paragraph    FAIL         Reject --- LLM
  paragraph       paragraph, check   appears 2+ times              hallucination
                  for repeats within                               indicator
                  article                                          

  Source          Cosine similarity  Similarity score FAIL         Too close to
  similarity      of article vs      above 0.85                    source --- not
                  source                                           unique enough

  Forbidden topic Keyword scan       Any forbidden    FAIL         Reject, flag
                  against DNA        keyword found                 topic, log for
                  forbidden_topics                                 review
                  list                                             

  Medical claim   Regex scan for     Any match on     HOLD         Route to
                  medical claim      Health cluster                mandatory human
                  patterns           sites                         review

  FTC disclosure  Check for required Missing on       FAIL         Inject standard
                  disclosure text    Health cluster                disclosure or
                                     articles                      reject

  Satire          Cluster check ---  Any article for  HOLD         Route to
  moderation      all Satire         a Satire cluster              mandatory human
                  articles flagged   site                          review
                                                                   permanently

  Source length   Count words in RSS Source under 300 SKIP         Skip this source
                  source article     words                         --- not enough
                                                                   material for
                                                                   generation

  Promo density   Count promo links  More than 3      FAIL         Too promotional
                  in final article   promo links                   --- trim promo
                                                                   or reject

  SEO fields      Verify all SEO     Any field empty  FAIL         SEO generation
                  fields non-empty   after Stage 6                 failed ---
                                                                   editor must
                                                                   complete
  --------------- ------------------ ---------------- ------------ ----------------

**6. Review and Automated Publishing**

**Automated publishing is the default --- configured per site (updated
per Plan Addendum)**

  ---------------- --------------- ------------------------- --------------
  **Mode**         **Config**      **Flow**                  **When to
                                                             Use**

  **Automated      auto_publish =  generated → quality gate  Default for
  Publishing**     true (default   passes with no HOLD →     every cluster:
                   for all         approved automatically →  Technology,
                   clusters)       scheduled → published     Marketing,
                                                             Local,
                                                             Lifestyle,
                                                             Retail,
                                                             Travel,
                                                             Charity,
                                                             Sports

  **Human Review** auto_publish =  generated → review queue  Health cluster
                   false           → editor approves/rejects (mandatory),
                   (exception, not → approved → scheduled →  Satire cluster
                   default)        published                 (mandatory),
                                                             any new site
                                                             (first 10
                                                             articles), any
                                                             article with a
                                                             HOLD flag
  ---------------- --------------- ------------------------- --------------

> **RULE** A HOLD flag always overrides auto_publish. If an article
> receives ANY HOLD flag --- medical claim, satire routing, or any
> future HOLD check --- it goes to human review regardless of the
> site\'s auto_publish setting. This is a hard rule, not a config
> option. Since automated publishing is now the default for all other
> clusters, this HOLD mechanism is the primary safety net protecting
> Health and Satire content.

**Article state machine**

  ------------- ------------------------ ---------------------------------
  **Status**    **Meaning**              **Transitions To**

  queued        In generation queue      generating
                awaiting worker          

  generating    LLM API call in progress generated (success) or failed
                                         (after retries)

  generated     Article produced,        review (any HOLD or human_review
                awaiting quality gate    site) or approved (auto_publish,
                result                   all checks passed)

  review        Waiting for human editor approved (editor approves) or
                decision                 rejected (editor rejects)

  approved      Cleared for publishing   scheduled

  scheduled     Publish datetime         publishing
                assigned, job queued     

  publishing    Publish job running      published (success) or failed
                                         (after retries)

  published     Live on the website ---  ---
                terminal state           

  failed        Unrecoverable failure    queued (manual retry by operator)
                after all retries        

  rejected      Editor rejected ---      ---
                terminal state           

  skipped       Duplicate source ---     ---
                terminal state           
  ------------- ------------------------ ---------------------------------

**7. Queue Architecture**

**Three named channels in Laravel Horizon**

  --------------- ---------------------- ------------- --------------------
  **Channel**     **Jobs**               **Workers**   **Notes**

  ingestion       FetchRssFeedJob,       1-2           High frequency, low
                  DeduplicateItemJob                   cost. Stagger feed
                                                       fetches across the
                                                       hour.

  generation      GenerateArticleJob,    2-4           Rate-limited. LLM
                  GenerateSeoFieldsJob                 calls. Most
                                                       expensive per job.
                                                       Scale workers if
                                                       queue depth rises.

  publishing      PublishArticleJob      2-4           One job per article
                                                       per site.
                                                       Independent
                                                       timeouts. Credential
                                                       failures halt site,
                                                       not queue.
  --------------- ---------------------- ------------- --------------------

> **RULE** Never call the LLM API inside an HTTP request. Every AI call
> goes through the generation queue. One 30-second LLM timeout inside a
> web request makes the application appear to hang. Enforce this at code
> review.

**Two Laravel Scheduled Commands (not queue jobs)**

-   PublishingSchedulerCommand --- runs every 5 minutes, creates
    PublishJob records for all approved articles that are ready to
    publish based on site timezone and daily limits.

-   CredentialHealthCheckCommand --- runs once daily, verifies all
    active site credentials, updates deployment_state, alerts on
    failures.

**8. Sprint Plan**

10 sprints across 20 weeks. Each sprint is 2 weeks. Focus is entirely on
content generation and publishing to existing sites.

**Sprint 0 Decision Lock and Schema** · Weeks 1--2 · Owner: Both
Developers + Product

  ---------- --------------------- ----------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                    **Owner**   **Est.**

  **S0.1**   **Finalise database   All MariaDB tables with       Both Devs   3d
             schema**              column types, indexes, and                
                                   foreign keys. MongoDB                     
                                   collection schemas agreed.                

  **S0.2**   **Define three PHP    AIProviderInterface,          Both Devs   1d
             interfaces**          PublishingAdapterInterface,               
                                   ContentFilterInterface ---                
                                   method signatures signed off              
                                   by both developers.                       

  **S0.3**   **Define Site DNA     Full field list with types,   Product     2d
             field spec**          allowed values, and example               
                                   values for each cluster.                  
                                   Agreed document.                          

  **S0.4**   **Define Author       Full MongoDB document         Both Devs   2d
             Persona field spec**  structure. Memory update                  
                                   rules. Versioning rules.                  
                                   Agreed document.                          

  **S0.5**   **Define quality gate 10 checks with thresholds,    Product +   1d
             rules per cluster**   trigger conditions, and       Dev         
                                   result types for each                     
                                   cluster.                                  

  **S0.6**   **Lock article state  All 11 status values, every   Both Devs   0.5d
             machine**             transition, every failure                 
                                   path. Single agreed source of             
                                   truth.                                    

  **S0.7**   **Select and          Confirm OpenAI, Claude, and   Dev         1d
             configure AI          Ollama (open source) are in               
             providers**           scope. Get API keys. Test                 
                                   basic calls.                              

  **S0.8**   **Lock auto_publish   Which clusters start with     Product     0.5d
             rules per cluster**   auto_publish=true and which               
                                   require mandatory human                   
                                   review.                                   
  ---------- --------------------- ----------------------------- ----------- ----------

Exit criteria: all 8 deliverables above are documents that both
developers have read and agreed. No code is written until Sprint 0 is
complete.

**Sprint 1 Core Platform Setup** · Weeks 3--4 · Owner: Backend Developer

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S1.1**   **Laravel project     New Laravel project with     Backend     1d
             setup**               Inertia.js, MariaDB                      
                                   connection, MongoDB                      
                                   connection via                           
                                   laravel-mongodb package,                 
                                   Redis connection.                        

  **S1.2**   **Laravel Horizon     Install Horizon. Configure   Backend     0.5d
             setup**               three queue channels:                    
                                   ingestion, generation,                   
                                   publishing. Each with its                
                                   own worker pool.                         

  **S1.3**   **Database            All MariaDB table migrations Backend     2d
             migrations**          from the schema in Section               
                                   2. Indexes on status+site_id             
                                   and status+created_at                    
                                   columns.                                 

  **S1.4**   **Credential          Confirm Laravel              Backend     1d
             encryption**          Crypt::encryptString() works             
                                   for credentials table. Write             
                                   CredentialService with                   
                                   encrypt(), decrypt(), and                
                                   verifyCredentials() methods.             
                                   Credentials never stored                 
                                   plain.                                   

  **S1.5**   **Authentication and  Laravel Fortify or Jetstream Backend     1.5d
             roles**               for auth. Four roles:                    
                                   super_admin, operator,                   
                                   editor, analyst. Role                    
                                   middleware on all routes.                

  **S1.6**   **Site management     Inertia.js page for listing, Backend +   2d
             UI**                  creating, and editing sites. Frontend    
                                   Stack type selection.                    
                                   Cluster assignment.                      
                                   max_posts_per_day.                       
                                   auto_publish toggle.                     

  **S1.7**   **Site DNA editor**   Inertia.js form for all 12   Backend +   2d
                                   DNA fields. Version number   Frontend    
                                   auto-increments on save.                 
                                   Previous version shown for               
                                   reference.                               

  **S1.8**   **Author persona      Inertia.js interface to      Backend +   2d
             management**          create, view, and edit       Frontend    
                                   author persona documents in              
                                   MongoDB. Assign authors to               
                                   sites. View and reset                    
                                   memory.                                  
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: sites can be created and configured with DNA and author
assignments. Credentials stored encrypted. Horizon dashboard accessible
and showing 3 queue channels.

**Sprint 2 RSS Ingestion** · Weeks 5--6 · Owner: Backend Developer

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S2.1**   **RSS source          CRUD interface for           Backend +   1.5d
             management UI**       rss_sources. Associate feeds Frontend    
                                   with clusters or specific                
                                   sites. Set frequency and                 
                                   priority.                                

  **S2.2**   **FetchRssFeedJob**   Queue job on ingestion       Backend     2d
                                   channel. Fetch feed, parse               
                                   XML, extract articles. Retry             
                                   3 times with back-off. Log               
                                   result to system_logs.                   

  **S2.3**   **Duplicate           Inside FetchRssFeedJob:      Backend     1d
             detection**           compute SHA-256                          
                                   content_hash. Check against              
                                   rss_items. Mark duplicates.              
                                   Never process the same                   
                                   article twice.                           

  **S2.4**   **Source word count   Inside FetchRssFeedJob:      Backend     0.5d
             filter**              count words in source                    
                                   body_html. Mark items under              
                                   300 words as                             
                                   is_processed=true and skip               
                                   them.                                    

  **S2.5**   **Feed schedule       Laravel Scheduled Command    Backend     1d
             dispatcher**          that runs every minute and               
                                   dispatches FetchRssFeedJob               
                                   for feeds whose                          
                                   fetch_frequency_minutes                  
                                   interval has elapsed.                    

  **S2.6**   **Feed item review    Inertia.js page showing      Frontend    2d
             screen**              recently fetched items by                
                                   site or cluster. Filter by               
                                   duplicates, skipped, and                 
                                   pending. Read-only at this               
                                   stage.                                   

  **S2.7**   **Feed error          If a feed has been in        Backend     0.5d
             alerting**            status=errored for 24+                   
                                   hours, trigger an alert to               
                                   the Operator role.                       
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: RSS feeds are being fetched on schedule, duplicates are
detected and skipped, items under 300 words are filtered, and fetched
items are visible in the review screen.

**Sprint 3 AI Generation Engine + Mini Pilot** · Weeks 7--9 · Owner: AI
Developer + Backend

  ---------- -------------------------- ---------------------------- ----------- ----------
  **\#**     **Task**                   **Detail**                   **Owner**   **Est.**

  **S3.1**   **AIProviderInterface      OpenAIProvider,              AI Dev      3d
             implementations**          ClaudeProvider,                          
                                        OllamaProvider --- all                   
                                        implementing                             
                                        AIProviderInterface.                     
                                        Provider factory that                    
                                        selects provider from site               
                                        or cluster config. No                    
                                        generation code touches                  
                                        provider APIs directly.                  

  **S3.2**   **PromptBuilderService**   Assembles the full LLM       AI Dev      2d
                                        prompt from: Site DNA                    
                                        prompt_fragment, Author                  
                                        Persona prompt_fragment,                 
                                        source article facts, and                
                                        cluster-level context.                   
                                        Returns PromptPayload. No                
                                        external calls.                          

  **S3.3**   **GenerateArticleJob**     Queue job on generation      AI Dev +    3d
                                        channel. Loads Site DNA and  Backend     
                                        Author Persona. Calls                    
                                        PromptBuilderService. Calls              
                                        AIProviderInterface. Stores              
                                        result in MongoDB and                    
                                        MariaDB. Handles all                     
                                        provider error types                     
                                        correctly.                               

  **S3.4**   **OutputValidator          Runs all 10 quality gate     AI Dev +    2d
             service**                  checks in sequence. Returns  Backend     
                                        quality_score and                        
                                        quality_flags. Uses                      
                                        ContentFilterInterface                   
                                        implementations. Updates                 
                                        MariaDB GeneratedArticle                 
                                        status.                                  

  **S3.5**   **GenerateSeoFieldsJob**   Follow-on job after          AI Dev      1.5d
                                        validation passes. Calls LLM             
                                        to generate SEO fields.                  
                                        Updates MongoDB article                  
                                        document. Flags for editor               
                                        completion on failure.                   

  **S3.6**   **Author memory updater**  After an article publishes   AI Dev      1d
                                        successfully, update the                 
                                        author persona MongoDB                   
                                        document: append article                 
                                        summary to                               
                                        memory.recent_summaries,                 
                                        update recent_topics. Max 10             
                                        entries in recent_summaries              
                                        --- drop oldest.                         

  **S3.7**   **Mini pilot --- 2 to 3    Configure 2-3 sites from     Both Devs + 3d
             sites**                    Technology or Lifestyle      Product     
                                        cluster. Run full generation             
                                        pipeline. Review output                  
                                        quality. Tune prompts before             
                                        building further.                        
  ---------- -------------------------- ---------------------------- ----------- ----------

Exit criteria: articles are generated end-to-end for 2-3 pilot sites
with acceptable quality. Author persona memory is updating after each
article. All three AI providers callable via the interface.

**Sprint 4 Promo Injection Engine** · Weeks 10--11 · Owner: Backend
Developer

  ---------- -------------------------- ---------------------------- ----------- ----------
  **\#**     **Task**                   **Detail**                   **Owner**   **Est.**

  **S4.1**   **Promo campaign           CRUD for promo_campaigns.    Backend +   2d
             management UI**            Set keywords, links, target  Frontend    
                                        clusters or sites, placement             
                                        rules, date range.                       

  **S4.2**   **PromoInjectorService**   Loads active campaigns       Backend     2d
                                        matching the site and                    
                                        cluster. Injects promotional             
                                        content at configured                    
                                        positions in article body.               
                                        Respects placement rules and             
                                        frequency limits. Returns                
                                        updated body or original if              
                                        no match.                                

  **S4.3**   **Conflict resolution**    If promo injection would     Backend     1d
                                        violate DNA rules (e.g.                  
                                        place a link in a site that              
                                        forbids external links),                 
                                        skip injection and log the               
                                        conflict. Never let promo                
                                        override DNA rules.                      

  **S4.4**   **Promo density check**    Add PromoDensityFilter to    Backend     0.5d
                                        quality gate. If article                 
                                        body contains more than 3                
                                        promo links after injection,             
                                        flag as FAIL.                            

  **S4.5**   **Promo injection          Log every injection attempt  Backend     0.5d
             logging**                  to system_logs: which                    
                                        campaign, which site, which              
                                        position, success or skip                
                                        reason.                                  
  ---------- -------------------------- ---------------------------- ----------- ----------

Exit criteria: promotional content is injected into articles where
active campaigns exist. DNA rule conflicts are handled gracefully. No
article is rejected because of a promo injection failure.

**Sprint 5 Review Workflow** · Weeks 12--13 · Owner: Backend + Frontend
Developer

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S5.1**   **Review queue UI**   Inertia.js page showing      Frontend    3d
                                   articles awaiting review.                
                                   Editor sees: generated                   
                                   article body, SEO fields,                
                                   quality score, quality                   
                                   flags, source article, and               
                                   which site it is for.                    
                                   Editors only see sites                   
                                   assigned to them.                        

  **S5.2**   **Approve action**    Button to approve article.   Backend +   1d
                                   Status moves from review →   Frontend    
                                   approved. Approved_by and                
                                   approved_at recorded.                    
                                   Article enters scheduling                
                                   queue.                                   

  **S5.3**   **Edit and approve    Editor can edit article body Backend +   1.5d
             action**              and SEO fields before        Frontend    
                                   approving. Edits stored in               
                                   MongoDB. Edit recorded in                
                                   system_logs with diff.                   

  **S5.4**   **Reject action**     Button to reject article     Backend +   0.5d
                                   with a required reason.      Frontend    
                                   Status moves to rejected.                
                                   Logged with reason and                   
                                   reviewer ID. Permanent ---               
                                   no auto-retry.                           

  **S5.5**   **HOLD flag display** Articles with HOLD flags     Frontend    1d
                                   (medical_claim,                          
                                   satire_review) show a                    
                                   prominent warning in the                 
                                   review UI explaining why the             
                                   flag was raised. Editor must             
                                   actively acknowledge the                 
                                   flag before approving.                   

  **S5.6**   **Mandatory review    Health and Satire cluster    Backend     1d
             enforcement**         sites have auto_publish                  
                                   permanently forced to false              
                                   regardless of site config. A             
                                   HOLD flag on any site forces             
                                   the article to review                    
                                   regardless of auto_publish               
                                   setting. Enforced at the                 
                                   OutputValidator stage, not               
                                   just in the UI.                          

  **S5.7**   **Review audit        Every approve, edit, and     Backend     0.5d
             trail**               reject action logged to                  
                                   system_logs with                         
                                   reviewer_id, timestamp, and              
                                   article_id. Visible to                   
                                   Operator and Super Admin                 
                                   roles.                                   
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: editors can review, edit, approve, and reject articles.
Health and Satire clusters cannot be auto-published. HOLD flags are
visible and require editor acknowledgement. Full audit trail of every
review decision.

**Sprint 6 Publishing Adapters + Scheduler** · Weeks 14--15 · Owner:
Backend Developer

  ---------- ---------------------------------- ----------------------------- ----------- ----------
  **\#**     **Task**                           **Detail**                    **Owner**   **Est.**

  **S6.1**   **WordPressAdapter**               Implements                    Backend     3d
                                                PublishingAdapterInterface.               
                                                Creates WordPress post via                
                                                REST API with title, content,             
                                                slug, excerpt, categories,                
                                                tags. Handles auth errors,                
                                                network errors, and rate                  
                                                limits as separate error                  
                                                types. verifyCredentials()                
                                                tests auth before first                   
                                                publish.                                  

  **S6.2**   **FtpHtmlAdapter**                 Implements                    Backend     3d
                                                PublishingAdapterInterface.               
                                                Connects via SFTP (preferred)             
                                                or FTP using decrypted                    
                                                credentials. Places HTML file             
                                                at correct path. Handles file             
                                                naming, overwrite, and                    
                                                directory structure.                      
                                                verifyCredentials() lists                 
                                                root directory.                           

  **S6.3**   **Publishing adapter factory**     Reads site.stack_type,        Backend     0.5d
                                                returns correct adapter                   
                                                implementation. No if-else                
                                                chains in the publish engine              
                                                --- the factory handles                   
                                                routing entirely.                         

  **S6.4**   **PublishArticleJob**              Queue job on publishing       Backend     2d
                                                channel. Loads PublishJob,                
                                                decrypts credentials, calls               
                                                adapter, updates MariaDB on               
                                                success or failure.                       
                                                Exponential back-off between              
                                                attempts. Credential failure              
                                                halts site immediately.                   

  **S6.5**   **PublishingSchedulerCommand**     Laravel Scheduled Command     Backend     1d
                                                (every 5 min). Finds approved             
                                                articles, creates PublishJob              
                                                records respecting site daily             
                                                limits and timezone. Oldest               
                                                approved article per site                 
                                                gets next available slot.                 

  **S6.6**   **CredentialHealthCheckCommand**   Laravel Scheduled Command     Backend     1d
                                                (daily). Calls                            
                                                verifyCredentials() on all                
                                                active sites. Updates                     
                                                deployment_state. Alerts on               
                                                failure. Halts publishing for             
                                                failed sites.                             
  ---------- ---------------------------------- ----------------------------- ----------- ----------

Exit criteria: articles are publishing to WordPress sites and FTP sites.
Credentials are verified daily. Failed credentials halt the site
immediately. Scheduler respects daily limits per site.

**Sprint 7 Monitoring and Admin Dashboards** · Week 16 · Owner:
Backend + Frontend Developer

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S7.1**   **Pipeline            Live queue depth per         Frontend    2d
             dashboard**           channel. Job throughput per              
                                   hour. Error rate per stage.              
                                   Worker count. Auto-refreshes             
                                   every 30 seconds.                        

  **S7.2**   **Per-site status     Every site listed with: last Frontend    1.5d
             board**               published, next scheduled,               
                                   credential status, health                
                                   check result, 7-day publish              
                                   count, 7-day failure count.              
                                   Filter by cluster or status.             

  **S7.3**   **Failed jobs log**   All failed publish jobs and  Frontend    1.5d
                                   generation failures.                     
                                   Filterable by site, adapter,             
                                   error type, date. One-click              
                                   manual retry for any failed              
                                   job.                                     

  **S7.4**   **Generation audit    Per-article view: source     Frontend    1.5d
             log**                 URL, author used, model,                 
                                   provider, quality score,                 
                                   quality flags, review                    
                                   decision. Linked to MongoDB              
                                   article body.                            

  **S7.5**   **AI cost tracker**   Daily spend by site and by   Backend +   1.5d
                                   provider. Running monthly    Frontend    
                                   total. Daily budget status.              
                                   Alert if daily spend exceeds             
                                   configured threshold.                    

  **S7.6**   **Silent failure      Eight specific alerts        Backend     1d
             alerts**              defined in Section 8 below.              
                                   Email or webhook                         
                                   notification to Operator                 
                                   role.                                    

  **S7.7**   **End-to-end QA on    Full pipeline test: RSS →    Both Devs   2d
             staging**             generation → quality gate →              
                                   review → schedule → publish.             
                                   Test both WordPress and FTP              
                                   adapters. Confirm all alerts             
                                   fire correctly.                          
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: all dashboards are working on staging. All 8 alerts fire
correctly in test conditions. End-to-end pipeline confirmed working for
both adapter types.

**Sprint 8 Expanded Pilot** · Weeks 17--18 · Owner: Both Developers +
Product

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S8.1**   **Expand pilot to     Technology + Marketing +     Product +   ---
             5--10 sites**         Lifestyle clusters. Mix of   Both Devs   
                                   auto_publish and human                   
                                   review sites. Run for 2                  
                                   weeks.                                   

  **S8.2**   **Author persona      Review quality scores across AI Dev +    3d
             quality review**      the pilot sites. Identify    Product     
                                   authors producing                        
                                   below-average scores. Tune               
                                   prompt_fragment and                      
                                   example_output. Update                   
                                   memory after 10+ articles                
                                   per author.                              

  **S8.3**   **Prompt tuning from  Apply improvements found in  AI Dev      2d
             mini-pilot            Sprint 3 mini-pilot. Compare             
             learnings**           quality scores before and                
                                   after using prompt_version               
                                   tracking.                                

  **S8.4**   **Publishing          Review publish job success   Backend     2d
             stability review**    rates. Identify sites with               
                                   frequent failures. Fix                   
                                   credential issues or adapter             
                                   edge cases.                              

  **S8.5**   **Cost review**       Review actual AI spend vs    Both Devs + 1d
                                   estimate. Identify high-cost Product     
                                   sites or models. Adjust                  
                                   provider routing if needed.              

  **S8.6**   **Pilot               Document what worked, what   Both Devs + 0.5d
             retrospective**       did not, what needs fixing   Product     
                                   before full rollout.                     
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: 5--10 sites publishing consistently with acceptable
quality. Cost within budget. No recurring silent failures. Retrospective
document complete.

**Sprint 9 Fixes and Full Rollout** · Weeks 19--20 · Owner: Both
Developers + Product

  ---------- --------------------- ---------------------------- ----------- ----------
  **\#**     **Task**              **Detail**                   **Owner**   **Est.**

  **S9.1**   **Fix issues from     Address all items from the   Both Devs   3d
             expanded pilot**      Sprint 8 retrospective                   
                                   before expanding further.                

  **S9.2**   **Gradual rollout --- Expand to 20 sites. Monitor  Both Devs   2d
             20 sites**            for 3 days before expanding              
                                   further. Health and Satire               
                                   clusters last.                           

  **S9.3**   **Expand to all 60+   Full network rollout.        Both Devs   2d
             sites**               Monitor all 8 alerts. Ready              
                                   to respond to failures same              
                                   day.                                     

  **S9.4**   **Runbook             Document operational         Both Devs   1d
             documentation**       procedures: how to retry a               
                                   failed job, how to update                
                                   credentials, how to disable              
                                   a site, how to reset an                  
                                   author\'s memory, how to                 
                                   roll back a prompt change.               

  **S9.5**   **Handover to         Brief whoever will manage    Both Devs   0.5d
             operations**          day-to-day operations.                   
                                   Confirm they can use all 6               
                                   dashboards and respond to                
                                   all 8 alerts.                            
  ---------- --------------------- ---------------------------- ----------- ----------

Exit criteria: all 60+ sites publishing. No sites silent for more than 1
day. Operations team briefed. Runbook complete.

**9. Alerts --- All Eight**

All eight alerts must be implemented in Sprint 7. Each alert sends a
notification to the Operator role.

  ------------------ ----------------------------------------- -------------- --------------------
  **Alert**          **Trigger Condition**                     **Priority**   **Response**

  Site not published site.deployment_state.last_published_at   CRITICAL       Operator checks
                     older than 3 days                                        credential status,
                                                                              publish job log, and
                                                                              site health
                                                                              immediately

  Credential         Daily health check returns auth failure   CRITICAL       Halt publishing for
  verification       for any site                                             that site. Operator
  failed                                                                      updates credentials
                                                                              before re-enabling.

  3 consecutive      3 failed publish_jobs in sequence for the HIGH           Operator reviews
  publish failures   same site                                                last_error on failed
                                                                              jobs. Check if site
                                                                              is reachable.

  Queue depth        Any single queue channel goes above 500   HIGH           Engineering checks
  exceeds 500        pending jobs                                             worker count and
                                                                              scales if needed

  LLM error rate     More than 10% of generation jobs fail in  HIGH           Engineering checks
  above 10%          any 1-hour window                                        provider status
                                                                              pages and API key
                                                                              validity

  Daily AI spend     Total LLM API cost exceeds configured     MEDIUM         Product and
  threshold          daily budget                                             engineering review.
                                                                              Adjust provider
                                                                              routing or volume
                                                                              limits.

  Content policy     Any article receives a content_policy     MEDIUM         Route article to
  rejection          rejection from the LLM                                   review queue with
                                                                              flag. Operator
                                                                              reviews prompt for
                                                                              that site.

  RSS feed errored   A feed has been in status=errored for     LOW            Operator checks feed
  24h+               more than 24 hours                                       URL, tests it
                                                                              manually, updates or
                                                                              deactivates feed.
  ------------------ ----------------------------------------- -------------- --------------------

**10. Out of Scope for This Plan**

These items are deliberately excluded. They come after this phase is
stable and proven.

  ------------------------ ----------------------------------------------
  **Item**                 **When It Gets Built**

  New site creation and    Phase 2 --- after content pipeline is stable
  deployment               and proven

  SURFACE analytics and    Phase 2 --- needs content data to be
  reporting layer          meaningful

  Partner / client login   Phase 2
  to view site stats       

  Shopify and Ghost        Phase 2 --- interfaces defined now,
  publishing adapters      implementations later

  AI Project Manager       Phase 2 --- needs operational data from Phase
  supervisor layer         1 to train on

  Template and site shell  Phase 2 --- existing sites already have
  generation               templates

  White-label and          Phase 3
  multi-tenant features    

  Physical separation into Phase 3
  ATLAS / REACH / SURFACE  
  services                 
  ------------------------ ----------------------------------------------

*Lattis One --- ATLAS \| Content Generation & Publishing Plan \| Phase
1*
