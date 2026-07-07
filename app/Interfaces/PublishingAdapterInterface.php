<?php

namespace App\Interfaces;

use App\Models\Site;
use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\PublishResult;
use App\DataTransferObjects\CredentialCheckResult;
use App\DataTransferObjects\PostStatus;

/**
 * Every publishing target (WordPress, FTP/HTML, later Shopify/Ghost) implements this.
 * The publish engine and scheduler must NEVER talk to a CMS API or FTP client
 * directly — only through an implementation of this interface, selected by
 * a factory keyed on Site::$stack_type. Adding a new target = one new class,
 * zero changes to PublishArticleJob or PublishingSchedulerCommand.
 */
interface PublishingAdapterInterface
{
    public function publish(ArticlePayload $article, Site $site): PublishResult;

    public function unpublish(string $externalId, Site $site): bool;

    public function verifyCredentials(Site $site): CredentialCheckResult;

    public function getStatus(string $externalId, Site $site): PostStatus;

    public function getSupportedStackType(): string;
}
