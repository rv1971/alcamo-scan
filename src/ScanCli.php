<?php

namespace alcamo\scan;

use alcamo\cli\AbstractCli;
use alcamo\exception\ProcessFailed;
use alcamo\rdfa\RdfaData;
use GetOpt\{ArgumentException, GetOpt, Operand};

class ScanCli extends AbstractCli
{
    public const DEFAULT_FORMAT = 'jpeg';

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

    public const OPTIONS =
        [
            'adf' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Use automatic document feeder.'
            ],
            'audience' => [
                'u',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core audience.'
            ],
            'batch' => [
                'b',
                GetOpt::OPTIONAL_ARGUMENT,
                'Pages per RDF tuple. [All in ADF]',
                'count',
                Validator::class . '::validateBatch'
            ],
            'coverage' => [
                'a',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core coverage values.',
                'comma-separated'
            ],
            'creator' => [
                'c',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core creator.'
            ],
            'date' => [
                'd',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core created values.',
                'comma-separated'
            ],
            'device' => [
                'D',
                GetOpt::REQUIRED_ARGUMENT,
                'Scanner to use.',
                'sane-device'
            ],
            'format' => [
                'f',
                GetOpt::REQUIRED_ARGUMENT,
                'File format. [' . self::DEFAULT_FORMAT . ']',
                null,
                Validator::class . '::validateFormat'
            ],
            'geometry' => [
                'g',
                GetOpt::REQUIRED_ARGUMENT,
                'Scan area geometry. [a4]',
                'w>x<h>[+<l>+<t>]|<name',
                Validator::class . '::validateGeometry'
            ],
            'language' => [
                'l',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core language.',
                null,
                Validator::class . '::validateLanguage'
            ],
            'mode' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Scan mode: color|gray|binary. [' . self::DEFAULT_MODE . ']',
                'mode',
                Validator::class . '::validateMode'
            ],
            'process-only' => [
                null,
                GetOpt::MULTIPLE_ARGUMENT,
                'Only process existing files.'
            ],
            'publisher' => [
                'p',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core publisher.'
            ],
            'resolution' => [
                'r',
                GetOpt::REQUIRED_ARGUMENT,
                'Resolution. [' . self::DEFAULT_RESOLUTION . ']',
                'dpi',
                Validator::class . '::validateResolution'
            ],
            'rotate' => [
                'R',
                GetOpt::NO_ARGUMENT,
                'Rotate 90° clockwise.'
            ],
            'raw-scan-only' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Only execute raw scan.'
            ],
            'title' => [
                't',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core title values.',
                'comma-separated'
            ],
            'type' => [
                'T',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core type.',
                null,
                Validator::class . '::validateType'
            ]
        ]
        + parent::OPTIONS;

    private $device_;        ///< string
    private $backend_;       ///< string
    private $scanFormat_;    ///< string
    private $filenamePrefix_ ///< string
    private $dates_;         ///< array
    private $titles_;        ///< array
    private $coverages_;     ///< array

    public function innerRun(): int
    {
        if (!$this->getOption('title') && !$this->getOption('raw-scan-only')) {
            throw new ArgumentException(
                'Either --title or --raw-scan-only must be given'
            );
        }

        $this->device_ =
            $this->getOption('device') ?? getenv('SANE_DEFAULT_DEVICE');

        if ($this->device_ === false) {
            $this->device_ = null;
        }

        $this->backend_ = explode(':', $this->device_, 2)[0];

        $this->batchSize_ = (int)$this->getOption('batch');

        $this->scanFormat_ = $this->getOption('format') ?? self::DEFAULT_FORMAT;

        $this->filenamePrefix_ = 'scan.' . date('Ydm\THis');

        $this->dates_ = $this->getOption('date')
            ? explode(',', $this->getOption('date'))
            : [ null ];

        $this->titles_ = explode(',', $this->getOption('title'));

        $this->coverages_ = $this->getOption('coverage')
            ? explode(',', $this->getOption('coverage'))
            : [ null ];

        if (!$this->getOption('process-only')) {
            $this->scanPages();
        }

        if (!$this->getOption('raw-scan-only')) {
            $this->processImageFiles();
        }

        return 0;
    }

    protected function createBasename(RdfaData $rdfaData): string
    {
        $props = [];

        foreach (
            [
                'dc:publisher',
                'dc:created',
                'dc:audience',
                'dc:title',
                'dc:coverage'
            ] as $propCurie
        ) {
            if ($rdfaData[$propCurie]) {
                switch ($propCurie) {
                    case 'dc:created':
                        $props[] = $this->rdfaData_[$propCurie]
                            ->format('Y-m-d');
                        break;

                    default:
                        $props[] = $this->rdfaData_[$propCurie];
                }
            }
        }

        return implode('.', $props);
    }

    private function scanPages(): void
    {
        $geometryString =
            $this->getOption('geometry') ?? self::DEFAULT_GEOMETRY;

        if (ctype_digit($geometryString[0])) {
            $geometry = preg_split('[x\+]', $geometryString);
        } else {
            $geometry = self::GEOMETRY_MAP[$geometryString];
        }

        $mode = $this->getOption('mode') ?? self::DEFAULT_MODE;

        $backendMode = isset(self::MODE_MAP[$this->backend_])
            ? self::MODE_MAP[$this->backend_][$mode]
            : self::MODE_MAP['*'][$mode];

        $totalPageCount = isset($this->batchSize_)
            ? (count($this->dates_)
               * count($this->titles_)
               * count($this->coverages_)
               * $this->batchSize_)
            : null;

        $filenameFormat = "{$this->filenamePrefix_}.%.2d.{$this->scanFormat_}";

        $cmdLine = "scanimage "
            . "--format={$this->scanFormat_} "
            . "--batch=$filenameFormat "
            . '-p '
            . "--mode=$backendMode ";

        if (isset($this->device_)) {
            $cmdLine .= "-device-name=$this->device_ ";
        }

        if ($this->getOption('batch')) {
            $cmdLine .= "--batch-count=$totalPageCount ";
        }

        $cmdLine .= "-x {$geometry[0]} -y {$geometry[1]} ";

        if (isset($geometry[2])) {
            $cmdLine .= "-l {$geometry[2]} -t {$geometry[3]} ";
        }

        $resolution = $this->getOption('resolution')
            ?? self::DEFAULT_RESOLUTION;

        switch ($this->backend_) {
            case 'epkowa':
                $cmdLine .=
                    "--x-resolution=$resolution --y-resolution=$resolution ";
                break;

            default:
                $cmdLine .= "--resolution=$resolution ";
        }

        if ($this->getOption('adf')) {
            $cmdLine .= "--source='Automatic Document Feeder' ";
        } else {
            $cmdLine .= "--batch-prompt ";
        }

        $this->getProgressReporter()->write($cmdLine, 1);

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

    private function processImageFiles(): void
    {
        $files = $this->getOption('process-only')
            ?: glob("{$this->filenamePrefix_}.[0-9][0-9].{$this->scanFormat_}");

        $fixedRdfaData = RdfaData::newFromIterable(
            [
                'dc:audience'  => $this->getOption('audience'),
                'dc:creator'   => $this->getOption('creator'),
                'dc:language'  => $this->getOption('language'),
                'dc:publisher' => $this->getOption('publisher'),
                'dc:type'      => $this->getOption('type')
            ]
        );

        $pageNo = 1;
        foreach ($this->dates_ as $date) {
            foreach ($this->titles_ as $title) {
                foreach ($this->coverages_ as $coverage) {
                    $rdfaData = $fixedRdfaData->replace(
                        RdfaData::newFromIterable(
                            [
                                'dc:coverage' => $coverage,
                                'dc:created' => $date,
                                'dc:title' => $title
                            ]
                        )
                    );

                    $basename = $this->createBasename($rdfaData);

                    echo "$basename\n";

                    $pageNo++;
                }
            }
        }
    }
}
