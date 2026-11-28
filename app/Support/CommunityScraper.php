<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Process\Process;

class CommunityScraper
{
    private const MAX_HTML_BYTES = 2097152;
    private const MAX_IMAGE_BYTES = 5242880;
    private ?int $lastHttpStatus = null;

    public function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || strlen($url) > 2048) {
            throw new RuntimeException('허용되지 않는 주소입니다.');
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) || ! preg_match('/^(?=.{1,253}$)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $host)) {
            throw new RuntimeException('도메인 이름만 허용됩니다.');
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) throw new RuntimeException('80 또는 443 포트의 HTTP(S) 주소만 허용됩니다.');
        $records = array_merge(dns_get_record($host, DNS_A) ?: [], dns_get_record($host, DNS_AAAA) ?: []);
        if ($records === []) throw new RuntimeException('도메인 주소를 확인할 수 없습니다.');
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('내부 네트워크 주소는 허용되지 않습니다.');
            }
        }
    }

    protected function get(string $url, int $limit): string
    {
        $this->lastHttpStatus = null;
        $this->assertPublicUrl($url);
        if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            throw new RuntimeException('PHP cURL 확장이 활성화되지 않아 안전한 HTTP 연결을 시작할 수 없습니다.');
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $records = dns_get_record($host, DNS_A) ?: [];
        $ip = $records[0]['ip'] ?? null;
        if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('공개 IPv4 주소가 필요합니다.');
        }
        // Pin the validated address for this connection; no automatic redirects or proxy are allowed.
        try {
            $response = Http::withOptions([
                'allow_redirects' => false, 'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
            ])->withHeaders(['User-Agent' => 'IdolRankingEditorialBot/1.0', 'Accept' => 'text/html,image/jpeg,image/png,image/gif'])
                ->connectTimeout(3)->timeout(8)->get($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->connectionFailure($exception), 0, $exception);
        } catch (\InvalidArgumentException $exception) {
            $reason = str_contains($exception->getMessage(), 'CURLOPT_RESOLVE')
                ? '현재 HTTP 전송기가 CURLOPT_RESOLVE를 지원하지 않습니다.'
                : '현재 HTTP 전송기와 요청 옵션이 호환되지 않습니다.';
            throw new RuntimeException($reason, 0, $exception);
        }
        $this->lastHttpStatus = $response->status();
        if (! $response->successful() || strlen($response->body()) > $limit) {
            throw new RuntimeException($response->successful()
                ? 'HTTP 응답 크기가 제한을 초과했습니다.'
                : 'HTTP '.$response->status().' 응답으로 접근이 거부되거나 요청이 실패했습니다.');
        }
        return $response->body();
    }

    protected function getPage(object $source, string $url): string
    {
        if (($source->collection_method ?? 'http') !== 'browser') return $this->get($url, self::MAX_HTML_BYTES);
        $this->assertPublicUrl($url);
        $host = strtolower(parse_url($url, PHP_URL_HOST));
        if ($host !== strtolower(parse_url($source->listing_url, PHP_URL_HOST))) {
            throw new RuntimeException('브라우저 수집은 등록된 게시판 도메인에서만 허용됩니다.');
        }
        $records = dns_get_record($host, DNS_A) ?: [];
        $ip = $records[0]['ip'] ?? null;
        if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('브라우저 수집에 사용할 공개 IPv4 주소가 없습니다.');
        }
        $process = new Process([env('SCRAPER_NODE_PATH', 'node'), base_path('scripts/scraping/browser-collect.mjs')], base_path());
        $process->setInput(json_encode(['url' => $url, 'host' => $host, 'ip' => $ip, 'maxBytes' => self::MAX_HTML_BYTES]));
        $process->setTimeout(25);
        try { $process->run(); }
        catch (\Throwable $exception) { throw new RuntimeException('브라우저 수집 실행 시간이 초과되거나 시작에 실패했습니다.', 0, $exception); }
        if (! $process->isSuccessful()) {
            $error = $process->getErrorOutput();
            $reason = match (true) {
                str_contains($error, 'Executable doesn') || str_contains($error, 'Cannot find module') => 'Playwright 또는 Chromium이 설치되지 않았습니다.',
                str_contains($error, 'ERR_CERT') => '브라우저의 SSL 인증서 검증에 실패했습니다.',
                str_contains($error, 'ERR_TIMED_OUT') => '브라우저 연결 시간이 초과되었습니다.',
                str_contains($error, 'ERR_NETWORK_ACCESS_DENIED') => '브라우저 네트워크 접근이 차단되었습니다.',
                default => '브라우저를 실행하거나 페이지에 연결하지 못했습니다. 관리자 로그를 확인해 주세요.',
            };
            throw new RuntimeException($reason);
        }
        $result = json_decode($process->getOutput(), true);
        if (! is_array($result)) throw new RuntimeException('브라우저 응답을 읽지 못했습니다.');
        $this->lastHttpStatus = $result['status'] ?? null;
        if (($result['url'] ?? '') !== $url) throw new RuntimeException('브라우저가 다른 주소로 이동하여 수집을 중단했습니다.');
        if ($this->lastHttpStatus === 401 || $this->lastHttpStatus === 403 || $this->lastHttpStatus === 430) {
            throw new RuntimeException('HTTP '.$this->lastHttpStatus.' 보안 또는 접근 제한 응답으로 수집할 수 없습니다.');
        }
        if ($this->lastHttpStatus < 200 || $this->lastHttpStatus >= 300) {
            throw new RuntimeException('브라우저에서 HTTP '.$this->lastHttpStatus.' 응답을 받았습니다.');
        }
        if (! empty($result['error']) || ! is_string($result['html'] ?? null)) {
            throw new RuntimeException($result['error'] ?? '렌더링된 HTML을 읽지 못했습니다.');
        }
        return $result['html'];
    }

    private function document(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new DOMXPath($dom);
    }

    private function nodes(DOMXPath $xpath, string $selector, ?DOMElement $context = null): array
    {
        if ($selector === '') return [];
        $expression = (new CssSelectorConverter())->toXPath($selector);
        $list = $xpath->query($expression, $context);
        return $list ? iterator_to_array($list) : [];
    }

    private function absoluteUrl(string $base, string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^(?:javascript|data|file):/i', $value)) return null;
        try {
            return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
                new \GuzzleHttp\Psr7\Uri($base), new \GuzzleHttp\Psr7\Uri($value)
            )->withFragment('');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function identifier(string $url): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(utm_|fbclid$|gclid$|ref$|source$)/i', $key)) unset($query[$key]);
        }
        ksort($query);
        return hash('sha256', strtolower($parts['host']).rtrim($parts['path'] ?? '/', '/').'?'.http_build_query($query));
    }

    public function preview(object $source): array
    {
        $xpath = $this->document($this->getPage($source, $source->listing_url));
        $urls = [];
        foreach ($this->nodes($xpath, $source->post_link_selector) as $node) {
            if (! $node instanceof DOMElement || $this->excludedElement($node)
                || preg_match('/공지|광고/i', $node->textContent)) continue;
            $url = $this->absoluteUrl($source->listing_url, $node->getAttribute('href'));
            if ($url === null || parse_url($url, PHP_URL_HOST) !== parse_url($source->listing_url, PHP_URL_HOST)) continue;
            $urls[$this->identifier($url)] = $url;
            if (count($urls) === 10) break;
        }
        $candidates = array_values($urls);
        shuffle($candidates);
        $reason = $candidates ? '최근 후보 10건에서 제목·본문과 유효한 이미지 또는 유튜브 영상을 갖춘 게시물을 찾지 못했습니다.'
            : '게시물 링크 선택자에 맞는 수집 후보가 없습니다.';
        foreach ($candidates as $url) {
            if (DB::table('scrape_imports')->where('normalized_identifier', $this->identifier($url))
                ->where('status', 'imported')->exists()) continue;
            try {
                $post = $this->inspectPost($source, $url);
                if ($post !== null) return compact('candidates') + ['eligible' => $post, 'reason' => null];
            } catch (\Throwable $exception) {
                // An inaccessible candidate must not prevent inspection of the remaining nine.
                $reason = $exception instanceof RuntimeException ? $exception->getMessage() : '상세 페이지 연결 또는 추출에 실패했습니다.';
                continue;
            }
        }
        return compact('candidates', 'reason') + ['eligible' => null];
    }

    public function diagnose(object $source): array
    {
        $result = ['board_url' => $source->listing_url, 'board_status' => null, 'board_bytes' => null,
            'page_title' => null, 'post_link_count' => 0, 'candidates' => [], 'details' => [], 'reason' => null];
        try {
            $html = $this->getPage($source, $source->listing_url);
            $result['board_status'] = $this->lastHttpStatus;
            $result['board_bytes'] = strlen($html);
            $xpath = $this->document($html);
            $result['page_title'] = trim($xpath->query('//title')->item(0)?->textContent ?? '');
            $links = $this->nodes($xpath, $source->post_link_selector);
            $result['post_link_count'] = count($links);
            foreach ($links as $node) {
                if (! $node instanceof DOMElement || $this->excludedElement($node)
                    || preg_match('/공지|광고/i', $node->textContent)) continue;
                $url = $this->absoluteUrl($source->listing_url, $node->getAttribute('href'));
                if ($url === null || parse_url($url, PHP_URL_HOST) !== parse_url($source->listing_url, PHP_URL_HOST)) continue;
                $result['candidates'][$this->identifier($url)] = $url;
                if (count($result['candidates']) === 10) break;
            }
            $result['candidates'] = array_values($result['candidates']);
            foreach ($result['candidates'] as $url) {
                $detail = ['url' => $url, 'status' => null, 'title_count' => 0, 'title' => null,
                    'content_count' => 0, 'image_count' => 0, 'image_urls' => [],
                    'video_count' => 0, 'video_urls' => [], 'reason' => null];
                try {
                    if (DB::table('scrape_imports')->where('normalized_identifier', $this->identifier($url))
                        ->where('status', 'imported')->exists()) {
                        $detail['reason'] = '이미 수집한 게시물입니다.';
                    } else {
                        $body = $this->getPage($source, $url);
                        $detail['status'] = $this->lastHttpStatus;
                        $page = $this->document($body);
                        $titles = $this->nodes($page, $source->title_selector);
                        $contents = $this->nodes($page, $source->content_selector);
                        $images = $this->nodes($page, $source->image_selector ?? '');
                        $videos = $this->nodes($page, $source->video_selector ?? '');
                        $detail['title_count'] = count($titles);
                        $detail['title'] = isset($titles[0]) ? trim($titles[0]->textContent) : null;
                        $detail['content_count'] = count($contents);
                        $detail['image_count'] = count($images);
                        $detail['video_count'] = count($videos);
                        foreach ($images as $image) {
                            if ($image instanceof DOMElement) {
                                $resolved = $this->absoluteUrl($url, $image->getAttribute('src') ?: $image->getAttribute('data-src'));
                                if ($resolved !== null) $detail['image_urls'][] = $resolved;
                            }
                        }
                        foreach ($videos as $video) {
                            if ($video instanceof DOMElement) {
                                $resolved = $this->absoluteUrl($url, $video->getAttribute('href') ?: $video->getAttribute('src'));
                                if ($resolved !== null) $detail['video_urls'][] = $resolved;
                            }
                        }
                        if (! $titles) $detail['reason'] = '제목 선택자에 맞는 요소가 없습니다.';
                        elseif (! $contents) $detail['reason'] = '본문 선택자에 맞는 요소가 없습니다.';
                        elseif (trim($detail['title']) === '' || mb_strlen(trim($detail['title'])) > 90) $detail['reason'] = '제목이 비어 있거나 90자를 초과합니다.';
                        elseif (! $images && ! $videos) $detail['reason'] = '이미지 또는 유튜브 요소가 없습니다.';
                        else $detail['reason'] = '선택자 요소를 찾았습니다. 미디어 URL과 게시물 적합성은 실제 수집 단계에서 검증합니다.';
                    }
                } catch (\Throwable $exception) {
                    $detail['status'] = $this->lastHttpStatus;
                    $detail['reason'] = $this->diagnosticError($exception);
                }
                $result['details'][] = $detail;
            }
            if (! $links) $result['reason'] = '게시물 링크 선택자에 맞는 요소가 없습니다.';
            elseif (! $result['candidates']) $result['reason'] = '공지·광고·외부 링크를 제외하면 후보가 없습니다.';
        } catch (\Throwable $exception) {
            $result['board_status'] = $this->lastHttpStatus;
            $result['reason'] = $this->diagnosticError($exception);
        }
        return $result;
    }

    private function diagnosticError(\Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) return $exception->getMessage();
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) return 'SSL 인증서 검증에 실패했습니다.';
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException || str_contains($message, 'connect')) return '서버 연결에 실패했습니다.';
        return '요청 또는 HTML 검사에 실패했습니다. 관리자 로그를 확인해 주세요.';
    }

    private function connectionFailure(ConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());
        preg_match('/curl error (\d+)/i', $message, $matches);
        $code = isset($matches[1]) ? ' (cURL '.$matches[1].')' : '';
        if (str_contains($message, 'certificate') || str_contains($message, 'ssl')) {
            return 'SSL 인증서 검증 또는 TLS 연결에 실패했습니다'.$code.'.';
        }
        if (str_contains($message, 'resolve host') || str_contains($message, 'could not resolve')) {
            return '도메인 DNS 확인에 실패했습니다'.$code.'.';
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return '서버 연결 시간이 초과되었습니다'.$code.'.';
        }
        if (str_contains($message, 'connect')) {
            return '서버 연결에 실패했습니다'.$code.'.';
        }
        return 'HTTP 전송에 실패했습니다'.$code.'. 관리자 로그를 확인해 주세요.';
    }

    public function assertImportAllowed(object $source): void
    {
        if (! $source->redistribution_permitted) {
            throw new RuntimeException('이 커뮤니티의 콘텐츠 재배포 허용 여부가 확인되지 않았습니다.');
        }
        if ($source->attribution_required) {
            throw new RuntimeException('공개 출처 표기가 필요한 커뮤니티의 게시물은 등록할 수 없습니다.');
        }
    }

    public function previewSavedPost(object $source, string $url, string $html): array
    {
        $this->assertPublicUrl($url);
        if (parse_url($url, PHP_URL_HOST) !== parse_url($source->listing_url, PHP_URL_HOST)) {
            throw new RuntimeException('저장한 게시물 URL은 등록된 커뮤니티 도메인과 같아야 합니다.');
        }
        if ($html === '' || strlen($html) > self::MAX_HTML_BYTES) {
            throw new RuntimeException('저장한 HTML 파일이 비어 있거나 2MB를 초과합니다.');
        }
        $post = $this->inspectPost($source, $url, $html);
        return ['candidates' => [$url], 'eligible' => $post,
            'reason' => $post === null ? '저장한 HTML에서 제목·본문과 유효한 이미지 또는 유튜브 영상을 찾지 못했습니다.' : null];
    }

    private function inspectPost(object $source, string $url, ?string $savedHtml = null): ?array
    {
        $xpath = $this->document($savedHtml ?? $this->getPage($source, $url));
        $titleNode = $this->nodes($xpath, $source->title_selector)[0] ?? null;
        $contentNode = $this->nodes($xpath, $source->content_selector)[0] ?? null;
        if (! $titleNode || ! $contentNode) return null;
        $title = $this->cleanContent($xpath, $titleNode);
        if ($title === '' || mb_strlen($title) > 90) return null;
        foreach ($this->nodes($xpath, $source->video_selector ?? '') as $node) {
            if (! $node instanceof DOMElement) continue;
            $video = $this->absoluteUrl($url, $node->getAttribute('href') ?: $node->getAttribute('src'));
            $id = $video ? YoutubeVideo::id($video) : null;
            if ($id) {
                $content = $this->cleanContent($xpath, $contentNode);
                return compact('url', 'title', 'content') + ['media_type' => 'youtube', 'youtube_video_id' => $id];
            }
        }
        foreach ($this->nodes($xpath, $source->image_selector ?? '') as $node) {
            if (! $node instanceof DOMElement) continue;
            $metadata = strtolower($node->getAttribute('alt'));
            if ($this->excludedElement($node) || preg_match('/logo|avatar|advert|banner|emoji|icon|tracking|pixel|광고/i', $metadata)) continue;
            $imageUrl = $this->absoluteUrl($url, $node->getAttribute('src') ?: $node->getAttribute('data-src'));
            if ($imageUrl === null) continue;
            if (preg_match('~(?:^|[/_.-])(logo|avatar|banner|advert|emoji|icon|tracking|pixel)(?:[/_.-]|$)~i', parse_url($imageUrl, PHP_URL_PATH) ?? '')) continue;
            if (parse_url($imageUrl, PHP_URL_HOST) !== parse_url($source->listing_url, PHP_URL_HOST)) continue;
            try {
                $bytes = $this->get($imageUrl, self::MAX_IMAGE_BYTES);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $info = getimagesizefromstring($bytes);
                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)
                    || ! $info || $info[0] < 200 || $info[1] < 200) continue;
                $content = $this->cleanContent($xpath, $contentNode);
                return compact('url', 'title', 'content', 'bytes', 'mime') + ['media_type' => 'image'];
            } catch (\Throwable $exception) {
                continue;
            }
        }
        return null;
    }

    private function cleanContent(DOMXPath $xpath, \DOMNode $contentNode): string
    {
        foreach (iterator_to_array($xpath->query('.//*', $contentNode)) as $node) {
            if (! $node instanceof DOMElement) continue;
            $metadata = strtolower($node->getAttribute('class').' '.$node->getAttribute('id'));
            if (in_array(strtolower($node->tagName), ['script', 'style', 'noscript', 'iframe', 'form'], true)
                || preg_match('/advert|sponsor|banner|tracking|share|광고/i', $metadata)) {
                $node->parentNode?->removeChild($node);
            }
        }
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $contentNode->textContent)), 0, 2000);
    }

    private function excludedElement(DOMElement $element): bool
    {
        for ($node = $element, $depth = 0; $node instanceof DOMElement && $depth < 4; $node = $node->parentNode, $depth++) {
            $metadata = strtolower($node->getAttribute('class').' '.$node->getAttribute('id').' '.$node->getAttribute('rel'));
            if (preg_match('/notice|pinned|advert|sponsor|promoted|banner|logo|avatar|emoji|tracking|pixel|광고|공지/i', $metadata)) {
                return true;
            }
        }
        return false;
    }
}
