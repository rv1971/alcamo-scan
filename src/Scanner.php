<?php

namespace alcamo\scan;

use alcamo\cli\{LoggableTrait, Logger};
use alcamo\exception\ProcessFailed;

class Scanner
{
    use LoggableTrait;

    /// Pathname of scanimage binary
    public const SCANIMAGE = '/usr/bin/scanimage';

    private $conf_;          ///< ConfDocument
    private $fileDataCache_; ///< FileDataCache
    private $scanimage_;     ///< pathname of scanimage binary

    public function __construct(
        ConfDocument $conf,
        FileDataCache $fileDataCache,
        ?Logger $logger = null
    ) {
        $this->conf_ = $conf;

        $this->fileDataCache_ = $fileDataCache;

        $this->scanimage_ = $conf->scanimage ?? static::SCANIMAGE;

        $this->setLogger($logger);
    }

    public function createCmdLine(
        string $filenamePrefix,
        ?int $totalPageCount = null,
        ?int $batchStart = null,
        ?int $batchIncrement = null
    ): string {
        $cmdLine = "$this->scanimage_ "
            . "--batch={$filenamePrefix}.%04d.{$this->conf_->format} "
            . "--device-name={$this->conf_->name} "
            . "--format={$this->conf_->format} "
            . '--progress ';

        if (isset($this->conf_->backendSource)) {
            $cmdLine .= "--source='{$this->conf_->backendSource->backendId}' ";
            $isAdf = $this->conf_->backendSource->isAdf;
        } else {
            $isAdf = false;
        }

        if (isset($this->conf_->bufferSize)) {
            $cmdLine .= "--buffer-size={$this->conf_->bufferSize} ";
        }

        if (isset($this->conf_->geometry)) {
            $geometry = $this->conf_->geometry->getValue();

            $cmdLine .= "-x {$geometry->w} -y {$geometry->h} ";

            if (isset($geometry->l)) {
                $cmdLine .= "-l {$geometry->l} -t {$geometry->t} ";
            }
        }

        if (isset($this->conf_->mode)) {
            $cmdLine .= "--mode={$this->conf_->backendMode->backendId} ";
        }

        if (isset($this->conf_->resolution)) {
            $cmdLine .= $this->conf_->supportsXYResolution
                ? ("--x-resolution={$this->conf_->resolution} "
                   . "--y-resolution={$this->conf_->resolution} ")
                : "--resolution={$this->conf_->resolution} ";
        }

        if (!$isAdf && $totalPageCount !== 1) {
            $cmdLine .= "--batch-prompt ";
        }

        if (isset($totalPageCount)) {
            $cmdLine .= "--batch-count={$totalPageCount} ";
        }

        if (isset($batchStart)) {
            $cmdLine .= "--batch-start={$batchStart} ";
        }

        if (isset($batchIncrement)) {
            $cmdLine .= "--batch-increment={$batchIncrement} ";
        }

        if (isset($this->logger_)) {
            $this->getLogger()->info($cmdLine);
        }

        return $cmdLine;
    }

    public function run(
        string $filenamePrefix,
        ?int $totalPageCount = null,
        ?int $batchStart = null,
        ?int $batchIncrement = null
    ): void {
        $cmdLine = $this->createCmdLine(
            $filenamePrefix,
            $totalPageCount,
            $batchStart,
            $batchIncrement
        );

        if (passthru($cmdLine, $exitCode) === false) {
            throw (new ProcessFailed())->setMessageContext(
                [ 'command' => $cmdLine ]
            );
        }

        if ($exitCode) {
            throw (new ProcessFailed())->setMessageContext(
                [ 'command' => $cmdLine ]
            );
        }

        $batchEnd = $batchStart + $totalPageCount * $batchIncrement;

        $data = (object)[
            'format' => (string)$this->conf_->format,
            'deviceName' => $this->conf_->name,
            'geometry' => $this->conf_->geometry->getValue(),
            'mode' => (string)$this->conf_->mode,
            'resolution' => $this->conf_->resolution
        ];

        if (isset($this->conf_->source)) {
            $data->source = $this->conf_->source;
            $data->isAdf = $this->conf_->backendSource->isAdf;
        } else {
            $data->isAdf = false;
        }

        foreach (
            glob("{$filenamePrefix}.????.{$this->conf_->format}") as $filename
        ) {
            $this->fileDataCache_[md5_file($filename)] = $data;
        }
    }
}
