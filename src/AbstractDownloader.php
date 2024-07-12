<?php

namespace Procket\Downloader;

use GuzzleHttp\Cookie\CookieJarInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

abstract class AbstractDownloader implements IDownloader
{
    /**
     * HTTP request
     * @var PendingRequest|null
     */
    protected ?PendingRequest $request = null;

    /**
     * Download link
     * @var string|null
     */
    protected ?string $url = null;

    /**
     * Message
     * @var mixed
     */
    protected mixed $msg = null;

    /**
     * File download directory
     * @var string|null
     */
    protected ?string $downloadDir = null;

    /**
     * Filename to save
     * @var string|null
     */
    protected ?string $saveFilename = null;

    /**
     * @inheritDoc
     */
    public function __construct(string $url = null, mixed $msg = null)
    {
        if (!is_null($url)) {
            $this->setUrl($url);
        }
        if (!is_null($msg)) {
            $this->setMsg($msg);
        }
    }

    /**
     * @inheritDoc
     */
    public function setRequest(PendingRequest $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): PendingRequest
    {
        if (is_null($this->request)) {
            $this->setRequest(new PendingRequest());
        }

        return $this->request;
    }

    /**
     * @inheritDoc
     */
    public function setUrl(string $url = null): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function setMsg(mixed $msg): static
    {
        $this->msg = $msg;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getMsg(): mixed
    {
        return $this->msg;
    }

    /**
     * @inheritDoc
     */
    public function setDownloadDir(string $dir): static
    {
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($dir);
        if (!$filesystem->isWritable($dir)) {
            throw new RuntimeException(sprintf(
                "Directory '%s' is not writable",
                $dir
            ));
        }

        $this->downloadDir = $dir;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getDownloadDir(): string
    {
        if (is_null($this->downloadDir)) {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->setDownloadDir('C:\\Downloads');
            } else {
                $this->setDownloadDir(DIRECTORY_SEPARATOR . 'Downloads');
            }
        }

        return $this->downloadDir;
    }

    /**
     * @inheritDoc
     */
    public function setSaveFilename(string $filename): static
    {
        $this->saveFilename = $filename;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSaveFilename(): string
    {
        if (is_null($this->saveFilename)) {
            $path = parse_url($this->getUrl(), PHP_URL_PATH);
            $this->saveFilename = (string) pathinfo($path, PATHINFO_BASENAME);
            if (!$this->saveFilename) {
                $this->saveFilename = md5($this->getUrl());
            }
        }

        return $this->saveFilename;
    }

    /**
     * @inheritDoc
     */
    public function setSaveFilePath(string $filePath): static
    {
        if ($filePath) {
            if (Str::contains($filePath, ['%(dir)', '%(filename)', '%(name)', '%(ext)'])) {
                $saveFilename = $this->getSaveFilename();
                $name = pathinfo($saveFilename, PATHINFO_FILENAME);
                $ext = pathinfo($saveFilename, PATHINFO_EXTENSION);
                $filePath = strtr($filePath, [
                    '%(dir)' => $this->getDownloadDir(),
                    '%(filename)' => $saveFilename,
                    '%(name)' => $name,
                    '%(ext)' => $ext
                ]);
            }
            if (Str::endsWith($filePath, '.')) {
                $filePath = Str::substr($filePath, 0, -1);
            }

            if ($saveDir = pathinfo($filePath, PATHINFO_DIRNAME)) {
                $this->setDownloadDir((string)$saveDir);
            }
            if ($filename = pathinfo($filePath, PATHINFO_BASENAME)) {
                $this->setSaveFilename((string)$filename);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSaveFilePath(): ?string
    {
        if (!($downloadDir = $this->getDownloadDir()) || !($saveFilename = $this->getSaveFilename())) {
            return null;
        }

        return $downloadDir . DIRECTORY_SEPARATOR . $saveFilename;
    }

    /**
     * @inheritDoc
     */
    public function getFileSizeToDownload(): false|int
    {
        try {
            $contentLength = $this->getRequest()->send('HEAD', $this->getUrl(), [
                'stream' => true
            ])->header('Content-Length');
        } catch (Throwable $e) {
            return false;
        }

        return $contentLength === '' ? false : (int)$contentLength;
    }

    /**
     * @inheritDoc
     */
    public function getDownloadedFileSize(): false|int
    {
        $filePath = $this->getSaveFilePath();

        if (!file_exists($filePath)) {
            return false;
        }

        clearstatcache(true, $filePath);

        return @filesize($filePath);
    }

    /**
     * @inheritDoc
     */
    public function withNewRequest(): static
    {
        $this->request = new PendingRequest();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withCookieJar(CookieJarInterface $cookieJar): static
    {
        $this->getRequest()->withOptions([
            'cookies' => $cookieJar
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withCookies(array $cookies, string $domain): static
    {
        $this->getRequest()->withCookies($cookies, $domain);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withHeaders(array $headers): static
    {
        $this->getRequest()->withHeaders($headers);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withUserAgent(string $userAgent): static
    {
        $this->getRequest()->withUserAgent($userAgent);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withProxy(string $proxy): static
    {
        $this->getRequest()->withOptions([
            'proxy' => $proxy
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function supportsResumable(): bool
    {
        try {
            $acceptRanges = $this->getRequest()->send('HEAD', $this->getUrl(), [
                'stream' => true
            ])->header('Accept-Ranges');
        } catch (Throwable $e) {
            return false;
        }

        return $acceptRanges === 'bytes';
    }

    /**
     * @inheritDoc
     */
    public function resumable(): static
    {
        if (!$this->supportsResumable()) {
            return $this;
        }

        $downloadedSize = (int) $this->getDownloadedFileSize();
        $fullFileSize = (int) $this->getFileSizeToDownload();
        $fromOffset = max($downloadedSize, 0);
        $maxOffset = $fullFileSize > 0 ? ($fullFileSize - 1) : 0;
        if ($fromOffset > $maxOffset) {
            $fromOffset = $maxOffset;
        }
        $this->getRequest()->withHeaders([
            'Range' => "bytes={$fromOffset}-"
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function percentage(): float
    {
        if (0 >= ($downloadedSize = $this->getDownloadedFileSize())) {
            return 0.00;
        }
        if (0 >= ($fullFileSize = $this->getFileSizeToDownload())) {
            return 0.00;
        }

        return round($downloadedSize / $fullFileSize, 4) * 100;
    }

    /**
     * @inheritDoc
     */
    public function finished(): bool
    {
        if (!file_exists($this->getSaveFilePath())) {
            return false;
        }
        if (false === ($downloadedSize = $this->getDownloadedFileSize())) {
            return false;
        }
        if (false === ($fullFileSize = $this->getFileSizeToDownload())) {
            return false;
        }

        return $downloadedSize === $fullFileSize;
    }
}