<?php

namespace App\Services\Publishing;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\CredentialCheckResult;
use App\DataTransferObjects\PostStatus;
use App\DataTransferObjects\PublishResult;
use App\Interfaces\PublishingAdapterInterface;
use App\Models\Credential;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * FTP/HTML publishing target: renders the article as a static HTML file and
 * uploads it via FTP. Uses curl's built-in FTP support rather than PHP's
 * ext-ftp — that extension isn't enabled in this environment (bundled DLL,
 * disabled by default) and curl is already a dependency used elsewhere.
 *
 * Assumes the FTP account's root already points at the site's public HTML
 * directory (typical for shared hosting) — there's no separate "remote path"
 * column, uploads go to "/{slug}.html" at FTP root. site.cms_api_url is
 * reused here as the public base URL for constructing the live link (it's
 * the CMS API URL for the WordPress adapter; for this adapter it's simply
 * the site's public base URL, e.g. https://example.com/).
 */
class FtpHtmlAdapter implements PublishingAdapterInterface
{
    public function publish(ArticlePayload $article, Site $site): PublishResult
    {
        $credential = $this->resolveCredential($site);
        $filename = $this->filenameFor($article);
        $html = $this->renderHtml($article);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $html);
        rewind($stream);

        $upload = $this->ftpRequest($credential, function ($ch) use ($credential, $filename, $html, $stream) {
            curl_setopt($ch, CURLOPT_URL, $this->ftpUrl($credential, $filename));
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $stream);
            curl_setopt($ch, CURLOPT_INFILESIZE, strlen($html));
            curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
        });

        fclose($stream);

        if (!$upload['success']) {
            return new PublishResult(
                success: false,
                errorType: $this->classifyError($upload['errno'], $upload['error']),
                errorMessage: $upload['error'],
            );
        }

        return new PublishResult(
            success: true,
            externalId: $filename,
            externalUrl: $this->publicUrlFor($site, $filename),
        );
    }

    public function unpublish(string $externalId, Site $site): bool
    {
        $credential = $this->resolveCredential($site);

        $result = $this->ftpRequest($credential, function ($ch) use ($credential, $externalId) {
            curl_setopt($ch, CURLOPT_URL, $this->ftpUrl($credential, ''));
            curl_setopt($ch, CURLOPT_QUOTE, ["DELE {$externalId}"]);
            curl_setopt($ch, CURLOPT_NOBODY, true);
        });

        return $result['success'];
    }

    public function verifyCredentials(Site $site): CredentialCheckResult
    {
        try {
            $credential = $this->resolveCredential($site);
        } catch (RuntimeException $e) {
            return new CredentialCheckResult(valid: false, errorMessage: $e->getMessage(), checkedAt: now());
        }

        $result = $this->ftpRequest($credential, function ($ch) use ($credential) {
            curl_setopt($ch, CURLOPT_URL, $this->ftpUrl($credential, ''));
            curl_setopt($ch, CURLOPT_FTPLISTONLY, true);
        });

        return new CredentialCheckResult(
            valid: $result['success'],
            errorMessage: $result['success'] ? null : $result['error'],
            checkedAt: now(),
        );
    }

    public function getStatus(string $externalId, Site $site): PostStatus
    {
        $url = $this->publicUrlFor($site, $externalId);

        try {
            $response = Http::timeout(10)->head($url);
        } catch (\Throwable) {
            return new PostStatus(status: 'unknown', externalUrl: $url);
        }

        return new PostStatus(
            status: match (true) {
                $response->successful() => 'live',
                $response->status() === 404 => 'not_found',
                default => 'unknown',
            },
            externalUrl: $url,
        );
    }

    public function getSupportedStackType(): string
    {
        return 'ftp_html';
    }

    private function resolveCredential(Site $site): Credential
    {
        $credential = $site->credentials()->where('adapter_type', 'ftp_html')->first();

        if (!$credential) {
            throw new RuntimeException("Site [{$site->id}] has no ftp_html credential configured.");
        }

        return $credential;
    }

    private function filenameFor(ArticlePayload $article): string
    {
        return ($article->slug ?: "article-{$article->generatedArticleId}").'.html';
    }

    private function renderHtml(ArticlePayload $article): string
    {
        $title = e($article->title);
        $meta = e($article->metaDescription ?? '');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <title>{$title}</title>
                <meta name="description" content="{$meta}">
            </head>
            <body>
                <article>
                    <h1>{$title}</h1>
                    {$article->bodyHtml}
                </article>
            </body>
            </html>
            HTML;
    }

    private function publicUrlFor(Site $site, string $filename): string
    {
        return rtrim((string) $site->cms_api_url, '/').'/'.ltrim($filename, '/');
    }

    private function ftpUrl(Credential $credential, string $path): string
    {
        $port = $credential->port ?: 21;

        return "ftp://{$credential->host}:{$port}/".ltrim($path, '/');
    }

    /** @return array{success: bool, error: ?string, errno: int} */
    private function ftpRequest(Credential $credential, callable $configure): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_USERPWD, "{$credential->username}:{$credential->secret}");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $configure($ch);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => $result !== false && $errno === 0,
            'error' => $errno !== 0 ? $error : null,
            'errno' => $errno,
        ];
    }

    private function classifyError(int $errno, ?string $message): string
    {
        // CURLE_LOGIN_DENIED (67) isn't exposed as a PHP constant in every
        // curl build, but the libcurl error code is stable across versions.
        return match ($errno) {
            67 => 'auth',
            CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_OPERATION_TIMEOUTED => 'network',
            default => 'unknown',
        };
    }
}
