<?php

namespace alcamo\scan;

use alcamo\cli\ProgressReporter;
use alcamo\exception\ProcessFailed;

class Scanner
{
    public const MODE_MAP = [
        'epkowa' => [
            'color'  => 'Color',
            'gray'   => 'Gray',
            'binary' => 'Binary'
        ],
        '*' => [
            'color'  => 'Color',
            'gray'   => 'Gray',
            'binary' => 'Lineart'
        ]
    ];

    private $device_;           ///< device
    private $batchStart_;       ///< ?int
    private $batchIncrement_;   ///< ?int
    private $filenamePrefix_;   ///< string
    private $progressReporter_; ///< ProgressReporter
    private $totalPageCount_;   ///< ?int

    public function __construct(
        Device $device,
        ?int $batchStart,
        ?int $batchIncrement,
        string $filenamePrefix,
        ProgressReporter $progressReporter,
        ?int $totalPageCount
    ) {
        $this->device_ = $device;

        $this->batchStart_ = $batchStart;

        $this->batchIncrement_ = $batchIncrement;

        $this->filenamePrefix_ = $filenamePrefix;

        $this->progressReporter_ = $progressReporter;

        $this->totalPageCount_ = $totalPageCount;
    }

    public function run(): void
    {
        $backend = explode(':', $this->device_['name'], 2)[0];

        $backendMode = (self::MODE_MAP[$backend] ?? self::MODE_MAP['*'])
            [$this->device_['mode']];

        $filenameFormat =
            "{$this->filenamePrefix_}.%02d.{$this->device_['format']}";

        $cmdLine = "scanimage "
            . "--device-name={$this->device_['name']} "
            . "--format={$this->device_['format']} "
            . "--batch=$filenameFormat "
            . '-p '
            . "--mode=$backendMode "
            . "--buffer-size={$this->device_['bufferSize']} ";

        if (isset($this->totalPageCount_)) {
            $cmdLine .= "--batch-count={$this->totalPageCount_} ";
        }

        if (isset($this->batchStart_)) {
            $cmdLine .= "--batch-start={$this->batchStart_} ";
        }

        if (isset($this->batchIncrement_)) {
            $cmdLine .= "--batch-increment={$this->batchIncrement_} ";
        }

        $geometry = preg_split('/[x\+]/', $this->device_['geometry']);

        $cmdLine .= "-x {$geometry[0]} -y {$geometry[1]} ";

        if (isset($geometry[2])) {
            $cmdLine .= "-l {$geometry[2]} -t {$geometry[3]} ";
        }

        switch ($backend) {
            case 'epkowa':
                $cmdLine .= "--x-resolution={$this->device_['resolution']} "
                    . "--y-resolution={$this->device_['resolution']} ";
                break;

            default:
                $cmdLine .= "--resolution={$this->device_['resolution']} ";
        }

        if ($this->device_['adf']) {
            $cmdLine .= "--source='Automatic Document Feeder' ";
        } elseif ($this->totalPageCount_ !== 1) {
            $cmdLine .= "--batch-prompt ";
        }

        $this->progressReporter_->write($cmdLine, 1);

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
