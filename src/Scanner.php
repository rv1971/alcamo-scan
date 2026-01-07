<?php

namespace alcamo\scan;

use alcamo\cli\Logger;
use alcamo\exception\ProcessFailed;

class Scanner
{
    private $conf_;             ///< ConfDocument
    private $logger_;           ///< Logger
    private $batchStart_;       ///< ?int
    private $batchIncrement_;   ///< ?int
    private $filenamePrefix_;   ///< string
    private $totalPageCount_;   ///< ?int

    public function __construct(
        ConfDocument $conf,
        Logger $logger,
        ?int $batchStart,
        ?int $batchIncrement,
        string $filenamePrefix,
        ?int $totalPageCount
    ) {
        $this->conf_ = $conf;

        $this->logger_ = $logger;

        $this->batchStart_ = $batchStart;

        $this->batchIncrement_ = $batchIncrement;

        $this->filenamePrefix_ = $filenamePrefix;

        $this->totalPageCount_ = $totalPageCount;
    }

    public function run(): void
    {
        $filenameFormat =
            "{$this->filenamePrefix_}.%04d.{$this->conf_->format}";

        $cmdLine = "scanimage "
            . "--device-name={$this->conf_->name} "
            . "--format={$this->conf_->format} "
            . "--batch=$filenameFormat "
            . '--progress ';

        if (isset($this->conf_->bufferSize)) {
            $cmdLine .= "--buffer-size={$this->conf_->bufferSize} ";
        }

        if (isset($this->conf_->mode)) {
            $cmdLine .= "--mode={$this->conf_->backendMode} ";
        }

        if (isset($this->conf_->geometry)) {
            $geometry = $this->conf_->geometry->getValue();

            $cmdLine .= "-x {$geometry->w} -y {$geometry->h} ";

            if (isset($geometry->l)) {
                $cmdLine .= "-l {$geometry->l} -t {$geometry->t} ";
            }
        }

        if (isset($this->conf_->resolution)) {
            $cmdLine .= $this->conf_->supportsXYResolution
                ? ("--x-resolution={$this->conf_->resolution} "
                   . "--y-resolution={$this->conf_->resolution} ")
                : "--resolution={$this->conf_->resolution} ";
        }

        if (isset($this->conf_->backendSource)) {
            $cmdLine .= "--source='{$this->conf_->backendSource}' ";
            $isAdf = $this->conf_->backendSource->isAdf;
        } else {
            $isAdf = false;
        }

        if (!$isAdf && $this->totalPageCount_ !== 1) {
            $cmdLine .= "--batch-prompt ";
        }

        if (isset($this->totalPageCount_)) {
            $cmdLine .= "--batch-count={$this->totalPageCount_} ";
        }

        if (isset($this->batchStart_)) {
            $cmdLine .= "--batch-start={$this->batchStart_} ";
        }

        if (isset($this->batchIncrement_)) {
            $cmdLine .= "--batch-increment={$this->batchIncrement_} ";
        }

        $this->logger_->info($cmdLine);

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
    }
}
