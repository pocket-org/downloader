<?php

namespace Procket\Downloader\Commands;

use Procket\Downloader\VideoDownloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class DownloadVideoCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(
            'download:video'
        )->addArgument(
            'url',
            InputArgument::REQUIRED,
            'Video site link for download'
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
        )->addOption(
            'with-aria2c',
            null,
            InputOption::VALUE_NONE,
            'Download with aria2c',
        )->addOption(
            'aria2c-args',
            null,
            InputOption::VALUE_OPTIONAL,
            'Download arguments of aria2c',
            '-x 1 -k 20M'
        )->setDescription(
            'Download video by url'
        );
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $saveAs = $input->getOption('save-as');
        $proxy = $input->getOption('proxy');
        $withAria2c = $input->getOption('with-aria2c');
        $aria2cArgs = $input->getOption('aria2c-args');
        $videoDownloader = new VideoDownloader($url);
        $videoDownloader->setDownloadDir(getcwd());
        if ($proxy) {
            $videoDownloader->withProxy($proxy);
        }
        if ($saveAs) {
            $videoDownloader->setSaveFilePath($saveAs);
        }
        if ($withAria2c) {
            $videoDownloader->withAria2cOfArgs($aria2cArgs);
        }

        $output->write('Downloading...');
        if ($videoDownloader->download()) {
            $output->writeln(" -- <info>successful</info>");
            return Command::SUCCESS;
        } else {
            $output->writeln(" -- <error>failed</error>");
            return Command::FAILURE;
        }
    }
}