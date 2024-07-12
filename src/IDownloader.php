<?php

namespace Procket\Downloader;

use GuzzleHttp\Cookie\CookieJarInterface;
use Illuminate\Http\Client\PendingRequest;

interface IDownloader
{
    /**
     * Constructor
     *
     * @param string|null $url Download link
     * @param mixed|null $msg Message
     */
    public function __construct(string $url = null, mixed $msg = null);

    /**
     * Set HTTP request
     *
     * @param PendingRequest $request
     * @return $this
     */
    public function setRequest(PendingRequest $request): static;

    /**
     * Get HTTP request
     *
     * @return PendingRequest
     */
    public function getRequest(): PendingRequest;

    /**
     * Set download link
     *
     * @param string|null $url Download link
     * @return $this
     */
    public function setUrl(string $url = null): static;

    /**
     * Get download link
     *
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * Set message
     *
     * @param mixed $msg Message
     * @return $this
     */
    public function setMsg(mixed $msg): static;

    /**
     * Get message
     *
     * @return mixed
     */
    public function getMsg(): mixed;

    /**
     * Set file download directory
     *
     * @param string $dir Download directory
     * @return $this
     */
    public function setDownloadDir(string $dir): static;

    /**
     * Get file download directory
     *
     * @return string
     */
    public function getDownloadDir(): string;

    /**
     * Set save filename (without path and with suffix)
     *
     * @param string $filename Filename
     * @return $this
     */
    public function setSaveFilename(string $filename): static;

    /**
     * Get save filename (without path and with suffix)
     *
     * @return string
     */
    public function getSaveFilename(): string;

    /**
     * Set the full path to save the file
     *
     * ```
     * The parameter filePath supports the following placeholders:
     * 1. %(dir) indicates the set download directory
     * 2. %(filename) indicates the set filename (with suffix)
     * 3. %(name) indicates the set filename (without suffix)
     * 4. %(ext) indicates the set file suffix
     * You may need to set proxy before this method to be able to access the url.
     * ```
     *
     * @param string $filePath file full path
     * @return $this
     */
    public function setSaveFilePath(string $filePath): static;

    /**
     * Get the full path to save the file
     *
     * @return string|null
     */
    public function getSaveFilePath(): ?string;

    /**
     * Get the size of the file to be downloaded
     *
     * @return int|false file size in bytes, false on error
     */
    public function getFileSizeToDownload(): false|int;

    /**
     * Get the downloaded file size
     *
     * @return int|false file size in bytes, false on error
     */
    public function getDownloadedFileSize(): false|int;

    /**
     * With a new HTTP request
     *
     * @return $this
     */
    public function withNewRequest(): static;

    /**
     * Set request cookies via CookieJar
     *
     * @param CookieJarInterface $cookieJar
     * @return IDownloader
     */
    public function withCookieJar(CookieJarInterface $cookieJar): static;

    /**
     * Set request cookies via an array
     *
     * @param array $cookies Cookies array
     * @param string $domain Cookies domain
     * @return $this
     */
    public function withCookies(array $cookies, string $domain): static;

    /**
     * Set request header
     *
     * @param array $headers An array of request headers
     * @return $this
     */
    public function withHeaders(array $headers): static;

    /**
     * Set request header User-Agent
     *
     * @param string $userAgent Request header User-Agent
     * @return $this
     */
    public function withUserAgent(string $userAgent): static;

    /**
     * Set request proxy
     *
     * @param string $proxy Request proxy
     * @return $this
     */
    public function withProxy(string $proxy): static;

    /**
     * Whether the file supports resumable download
     *
     * @return bool
     */
    public function supportsResumable(): bool;

    /**
     * Enable resumable downloads (do nothing if resumable download is not supported)
     *
     * @return $this
     */
    public function resumable(): static;

    /**
     * Execute download
     *
     * @return bool
     */
    public function download(): bool;

    /**
     * Get downloaded percentage
     *
     * @return float Returns a value from 0.00 to 100.00
     */
    public function percentage(): float;

    /**
     * Whether the download is complete
     *
     * @return bool
     */
    public function finished(): bool;
}