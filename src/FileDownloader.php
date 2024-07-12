<?php

namespace Procket\Downloader;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class FileDownloader extends AbstractDownloader
{
    /**
     * @inheritDoc
     * @throws ConnectionException
     * @throws RequestException
     */
    public function download(): bool
    {
        $oriSaveFilename = $this->getSaveFilename();
        $oriSaveFilePath = $this->getSaveFilePath();
        $tmpSaveFilename = $oriSaveFilename . '.download';
        $tmpSaveFilePath = $oriSaveFilePath . '.download';

        if ($this->finished()) {
            if (file_exists($tmpSaveFilePath)) {
                @unlink($tmpSaveFilePath);
            }
            return true;
        }

        $this->setSaveFilename($tmpSaveFilename);
        $resource = Utils::tryFopen($tmpSaveFilePath, 'a+b');
        if (!flock($resource, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException("Another process has locked this file");
        }
        try {
            $this->resumable()->getRequest()
                ->timeout(0)
                ->sink($resource)
                ->get($this->getUrl())
                ->throw();
        } finally {
            flock($resource, LOCK_UN);
            fclose($resource);
        }

        if (rename($tmpSaveFilePath, $oriSaveFilePath)) {
            $this->setSaveFilename($oriSaveFilename);
            return true;
        }

        return false;
    }
}