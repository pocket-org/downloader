<?php

namespace Procket\Downloader;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Psr7\Utils;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use YoutubeDl\Entity\Video;
use YoutubeDl\Options;
use YoutubeDl\Process\ArgvBuilder;
use YoutubeDl\Process\DefaultProcessBuilder;
use YoutubeDl\YoutubeDl;

class VideoDownloader extends AbstractDownloader
{
    /**
     * YoutubeDl class instance
     *
     * @var YoutubeDl|null
     */
    protected ?YoutubeDl $youtubeDl = null;

    /**
     * YoutubeDl download options
     *
     * @var Options|null
     */
    protected ?Options $youtubeDlOptions = null;

    /**
     * Get YoutubeDl class instance
     *
     * @return YoutubeDl
     */
    public function getYoutubeDl(): YoutubeDl
    {
        if (is_null($this->youtubeDl)) {
            $this->youtubeDl = new YoutubeDl();
            $ytDlpBin = (new ExecutableFinder())->find('yt-dlp');
            if ($ytDlpBin) {
                $this->youtubeDl->setBinPath($ytDlpBin);
            }
        }

        return $this->youtubeDl;
    }

    /**
     * Get YoutubeDl download options
     *
     * @return Options
     */
    public function getYoutubeDlOptions(): Options
    {
        if (is_null($this->youtubeDlOptions)) {
            $this->youtubeDlOptions = Options::create()
                ->downloadPath($this->getDownloadDir())
                ->noPlaylist();
        }

        return $this->youtubeDlOptions;
    }

    /**
     * Set YoutubeDl download options
     *
     * @param Options $options
     * @return $this
     */
    public function setYoutubeDlOptions(Options $options): static
    {
        $this->youtubeDlOptions = $options;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setDownloadDir(string $dir): static
    {
        parent::setDownloadDir($dir);
        $this->youtubeDlOptions = $this->getYoutubeDlOptions()->downloadPath($this->getDownloadDir());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSaveFilename(): string
    {
        if (is_null($this->saveFilename)) {
            $ytDlpBin = (new ExecutableFinder())->find('yt-dlp');
            $binPath = $ytDlpBin ?: null;
            $options = $this->getYoutubeDlOptions()
                ->url($this->getUrl())
                ->output('%(title)s-%(id)s.%(ext)s')
                ->skipDownload(true);
            $arguments = [
                '--ignore-config',
                '--ignore-errors',
                '--get-filename',
                ...ArgvBuilder::build($options),
            ];
            $process = (new DefaultProcessBuilder())->build($binPath, null, $arguments);
            $process->run();
            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                $output = iconv(mb_detect_encoding($output, mb_detect_order(), true), 'UTF-8', $output);
                if ($basename = pathinfo($output, PATHINFO_BASENAME)) {
                    $this->saveFilename = $basename;
                }
            }
        }

        if (!$this->saveFilename) {
            $this->saveFilename = parent::getSaveFilename();
        }

        return $this->saveFilename;
    }

    /**
     * @inheritDoc
     */
    public function withNewRequest(): static
    {
        parent::withNewRequest();

        $this->youtubeDl = null;
        $this->youtubeDlOptions = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withCookieJar(CookieJarInterface $cookieJar): static
    {
        parent::withCookieJar($cookieJar);

        $tempCookieFile = tempnam(sys_get_temp_dir(), 'tmc');
        if (file_exists($tempCookieFile)) {
            $cookiesArr = $cookieJar->toArray();
            CookieHelper::toNetscapeCookies($cookiesArr, $tempCookieFile);
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()->cookies($tempCookieFile);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withCookies(array $cookies, string $domain): static
    {
        parent::withCookies($cookies, $domain);

        $tempCookieFile = tempnam(sys_get_temp_dir(), 'tmc');
        if (file_exists($tempCookieFile)) {
            $cookiesArr = CookieJar::fromArray($cookies, $domain)->toArray();
            CookieHelper::toNetscapeCookies($cookiesArr, $tempCookieFile);
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()->cookies($tempCookieFile);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withHeaders(array $headers): static
    {
        parent::withHeaders($headers);

        foreach ($headers as $header => $value) {
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()->header((string) $header, (string) $value);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withUserAgent(string $userAgent): static
    {
        parent::withUserAgent($userAgent);

        $this->youtubeDlOptions = $this->getYoutubeDlOptions()->userAgent($userAgent);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withProxy(string $proxy): static
    {
        parent::withProxy($proxy);

        $this->youtubeDlOptions = $this->getYoutubeDlOptions()->proxy($proxy);

        return $this;
    }

    /**
     * Set external downloader to aria2c if it is available
     *
     * @param int $threads The maximum number of connections for each download
     * @param string $splitSize The minimum split size for each download
     * @return $this
     */
    public function withAria2c(int $threads = 1, string $splitSize = '20M'): static
    {
        if ($aria2cBin = (new ExecutableFinder())->find('aria2c')) {
            if (!preg_match('/^\d+[KM]$/i', $splitSize)) {
                $splitSize = '20M';
            }
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()
                ->externalDownloader($aria2cBin)
                ->externalDownloaderArgs('-x ' . $threads . ' -k ' . $splitSize);
        }

        return $this;
    }

    /**
     * Set external downloader to aria2c if it is available (with custom aria2c arguments)
     *
     * @param string $args Custom download arguments of aria2c
     * @return $this
     */
    public function withAria2cOfArgs(string $args): static
    {
        if ($aria2cBin = (new ExecutableFinder())->find('aria2c')) {
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()
                ->externalDownloader($aria2cBin)
                ->externalDownloaderArgs($args);
        }

        return $this;
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function download(): bool
    {
        $lockFile = $this->getSaveFilePath() . '.lock';
        $lockRes = Utils::tryFopen($lockFile, 'w+b');
        if (!flock($lockRes, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException("Another process has locked this file");
        }

        $realPath = null;
        try {
            $youtubeDl = $this->getYoutubeDl();
            $this->youtubeDlOptions = $this->getYoutubeDlOptions()
                ->url($this->getUrl())
                ->output($this->getSaveFilename());

            $videos = $youtubeDl->download($this->getYoutubeDlOptions())->getVideos();
            /** @var Video $video */
            $video = reset($videos);
            if (($video instanceof Video) && ($videoErr = $video->getError())) {
                throw new RuntimeException(sprintf(
                    "An error occurred while downloading the video: %s",
                    $videoErr
                ));
            }
            try {
                // \YoutubeDl\Entity\Video::getFile() may return null if the file has already been downloaded
                $realPath = $video->getFile()->getRealPath();
            } catch (Throwable $e) {
                $realPath = $this->getSaveFilePath();
            }
            if (file_exists($realPath)) {
                return pathinfo($realPath, PATHINFO_BASENAME) === $this->getSaveFilename();
            }
            return false;
        } finally {
            if (file_exists($realPath)) {
                $filePathWithoutExt = pathinfo($realPath, PATHINFO_DIRNAME) .
                    DIRECTORY_SEPARATOR .
                    pathinfo($realPath, PATHINFO_FILENAME);
                $fileInfoJson = $filePathWithoutExt . '.info.json';
                if (file_exists($fileInfoJson)) {
                    @unlink($fileInfoJson);
                }
            }
            flock($lockRes, LOCK_UN);
            fclose($lockRes);
            @unlink($lockFile);
        }
    }
}