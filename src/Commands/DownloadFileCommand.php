<?php

namespace Procket\Downloader\Commands;

use Illuminate\Http\Client\ConnectionException;
use Procket\Downloader\FileDownloader;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadFileCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(
            'download:file'
        )->addArgument(
            'url',
            InputArgument::REQUIRED,
            'Download link'
        )->addOption(
            'save-as',
            null,
            InputOption::VALUE_OPTIONAL,
            'Save file path, this option supports the following placeholders:
                1. %(dir) indicates the current working directory
                2. %(filename) indicates the filename with suffix
                3. %(name) indicates the filename without suffix
                4. %(ext) indicates the set file suffix
            '
        )->addOption(
            'proxy',
            null,
            InputOption::VALUE_OPTIONAL,
            'Set download proxy'
        )->setDescription(
            'Download file by url'
        );
    }

    /**
     * @inheritDoc
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $saveAs = $input->getOption('save-as');
        $proxy = $input->getOption('proxy');
        $fileDownloader = new FileDownloader($url);
        $fileDownloader->setDownloadDir(getcwd());
        if ($proxy) {
            $fileDownloader->withProxy($proxy);
        }
        if ($saveAs) {
            $fileDownloader->setSaveFilePath($saveAs);
        }

        $output->write('Downloading...');
        if ($fileDownloader->download()) {
            $output->writeln(" -- <info>successful</info>");
            return Command::SUCCESS;
        } else {
            $output->writeln(" -- <error>failed</error>");
            return Command::FAILURE;
        }
    }
}