<?php

namespace alcamo\scan;

use alcamo\cli\ProgressReporter;
use alcamo\exception\ProcessFailed;

class Scanner
{
    public const DEFAULT_BUFFER_SIZE = 64;

    public const DEFAULT_GEOMETRY = 'a4';

    public const DEFAULT_MODE = 'color';

    public const DEFAULT_RESOLUTION = 150;

    public const GEOMETRY_MAP = [
        'a4'  => [ 210, 297 ],
	'a5'  => [ 148, 210 ],
	'a5r' => [ 210, 148 ],
	'a6'  => [ 105, 148 ],
	'a6r' => [ 148, 105 ]
    ];

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

    private $adf_;              ///< bool
    private $backend_;          ///< ?string
    private $batchStart_;       ///< ?int
    private $device_;           ///< ?string
    private $filenamePrefix_;   ///< string
    private $format_;           ///< string
    private $geometry_;         ///< array
    private $mode_;             ///< string
    private $progressReporter_; ///< ProgressReporter
    private $resolution_;       ///< int
    private $subset_;           ///< ?int
    private $totalPageCount_;   ///< ?int

    public function __construct(
        bool $adf,
        ?int $batchStart,
        ?string $device,
        string $filenamePrefix,
        string $format,
        ?string $geometry,
        ?string $mode,
        ProgressReporter $progressReporter,
        ?int $resolution,
        ?int $subset,
        ?int $totalPageCount
    ) {
        $this->adf_ = $adf;

        $this->device_ = $device ?? getenv('SANE_DEFAULT_DEVICE') ?: null;

        if (isset($this->device_)) {
            $this->backend_ = explode(':', $this->device_, 2)[0];
        }

        $this->filenamePrefix_ = $filenamePrefix;

        $this->format_ = $format;

        if (!isset($geometry)) {
            $geometry = self::DEFAULT_GEOMETRY;
        }

        if (ctype_digit($geometry[0])) {
            $this->geometry_ = preg_split('[x\+]', $geometry);
        } else {
            $this->geometry_ = self::GEOMETRY_MAP[$geometry];
        }

        $this->mode_ = $mode ?? self::DEFAULT_MODE;

        $this->progressReporter_ = $progressReporter;

        $this->resolution_ = $resolution ?? self::DEFAULT_RESOLUTION;

        $this->subset_ = $subset;

        $this->batchStart_ = $batchStart ?? (($this->subset_ == 2) ? 2 : null);

        $this->totalPageCount_ = $totalPageCount;
    }

    public function run(): void
    {
        $backendMode = isset(self::MODE_MAP[$this->backend_])
            ? self::MODE_MAP[$this->backend_][$this->mode_]
            : self::MODE_MAP['*'][$this->mode_];

        $filenameFormat = "{$this->filenamePrefix_}.%02d.{$this->format_}";

        $cmdLine = "scanimage "
            . "--format={$this->format_} "
            . "--batch=$filenameFormat "
            . '-p '
            . "--mode=$backendMode "
            . '--buffer-size=' . self::DEFAULT_BUFFER_SIZE . ' ';

        if (isset($this->device_)) {
            $cmdLine .= "--device-name={$this->device_} ";
        }

        if (isset($this->totalPageCount_)) {
            $cmdLine .= "--batch-count={$this->totalPageCount_} ";
        }

        if (isset($this->subset_)) {
            $cmdLine .= "--batch-double ";
        }

        if (isset($this->batchStart_)) {
            $cmdLine .= "--batch-start={$this->batchStart_} ";
        }

        $cmdLine .= "-x {$this->geometry_[0]} -y {$this->geometry_[1]} ";

        if (isset($this->geometry_[2])) {
            $cmdLine .= "-l {$this->geometry_[2]} -t {$this->geometry_[3]} ";
        }

        switch ($this->backend_) {
            case 'epkowa':
                $cmdLine .= "--x-resolution={$this->resolution_} "
                    . "--y-resolution={$this->resolution_} ";
                break;

            default:
                $cmdLine .= "--resolution={$this->resolution_} ";
        }

        if ($this->adf_) {
            $cmdLine .= "--source='Automatic Document Feeder' ";
        } else {
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
