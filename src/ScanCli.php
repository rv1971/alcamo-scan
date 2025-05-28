<?php

namespace alcamo\scan;

use alcamo\cli\AbstractCli;
use alcamo\conf\{HasConfTrait, Loader, XdgFileFinder};
use alcamo\rdfa\RdfaData;
use GetOpt\{ArgumentException, GetOpt, Operand};

class ScanCli extends AbstractCli
{
    use HasConfTrait;

    public const DEFAULT_FORMAT = 'jpeg';

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
                'Multiple pages per RDF tuple. [All available pages]',
                'count',
                Validator::class . '::validatePageNumber'
            ],
            'batch-start' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Start with this page number.',
                'no',
                Validator::class . '::validatePageNumber'
            ],
            'batch-increment' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Increment page numbers by this.',
                'count',
                Validator::class . '::validatePageNumberIncrement'
            ],
            'combine' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Instead of creating the cartesian product of the lists of meta data, combine them. All meta data options with more than one entry must have the same length.'
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
                'Dublin core date values.',
                'comma-separated',
                Validator::class . '::validateDates'
            ],
            'device' => [
                'D',
                GetOpt::REQUIRED_ARGUMENT,
                'Scanner to use.',
                'sane-device'
            ],
            'even' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Only do raw scan of even pages.'
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
                'Scan mode: color|gray|binary. [' . Scanner::DEFAULT_MODE . ']',
                'mode',
                Validator::class . '::validateMode'
            ],
            'no-remove-raw-files' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Do not remove raw files.'
            ],
            'odd' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Only do raw scan of odd pages.'
            ],
            'pdf' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Create PDF output.'
            ],
            'process-only' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Only process existing files.'
            ],
            'publisher' => [
                'p',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core publisher.'
            ],
            'refresh-device' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Choose a new device based on the configuration. Useful if '
                . 'the device name of a USB scanner has changed.'
            ],
            'resolution' => [
                'r',
                GetOpt::REQUIRED_ARGUMENT,
                'Resolution. [' . Scanner::DEFAULT_RESOLUTION . ']',
                'dpi',
                Validator::class . '::validateResolution'
            ],
            'reverse' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Reverse order of pages.'
            ],
            'rotate' => [
                'R',
                GetOpt::NO_ARGUMENT,
                'Rotate 90° clockwise.'
            ],
            'scan-only' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Only do raw scan.'
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

    public const OPERANDS = [
        'file' => Operand::MULTIPLE
    ];

    public const DESCRIPTION = <<<'EOT'
Reads defaults from $XDG_CONFIG_HOME/alcamo/scan.ini:
* dc:language  Value for dc:language.
* dc:type      Value for dc:type.
* device       Use the device matching this regexp. The chosen device is stored
               in a state file and re-used in the next invocation.
EOT;

    public const LIST_DEVICES_CMD = 'scanimage -f "%d "';

    private $stateFile_; ///< string
    private $state_;     ///< array

    public function innerRun(): int
    {
        $confLoader = new Loader();

        $this->conf_ = $confLoader->load('scan.ini');

        $this->loadState();

        $scanOnly = $this->getOption('scan-only')
            || $this->getOption('even') || $this->getOption('odd');

        if (!$this->getOption('title') && !$scanOnly) {
            throw new ArgumentException(
                'One of --title, --odd, --even, --scan-only must be given'
            );
        }

        if ($scanOnly) {
            if (count($this->getOperand('file')) > 1) {
                throw new ArgumentException(
                    'In scan-only mode at most one filename prefix can be '
                    . 'given as an operand'
                );
            }
        } elseif (!$this->getOption('process-only')) {
            if (count($this->getOperand('file'))) {
                throw new ArgumentException(
                    'No filename operands can be given in scan-and-process mode'
                );
            }
        }

        $batchStart = $this->getOption('batch-start')
            ?? ($this->getOption('even') ? 2 : null);

        $batchIncrement = $this->getOption('batch-increment')
            ?? ($this->getOption('even') || $this->getOption('odd') ? 2 : null);

        $device = $this->getOption('device') ?? $this->state_['device'];

        /* A number of options imply --batch */
        if ($this->getOption('batch')) {
            $batchSize = (int)$this->getOption('batch');
        } else {
            $batchSize =  $this->getOption('batch-increment')
                || $this->getOption('batch-start')
                || $this->getOption('even')
                || $this->getOption('odd');
        }

        if (!$batchSize) {
            /* If --batch not given, scan one page. */
            $batchSize = 1;
        } elseif ($batchSize == 1) {
            /* If --batch given without argument, scan all available pages. */
            $batchSize = null;
        }

        $filenamePrefix = $scanOnly && $this->getOperand('file')
            ? $this->getOperand('file')[0]
            : 'scan.' . date('Ydm\THis');

        $format = $this->getOption('format') ?? self::DEFAULT_FORMAT;

        $dates = $this->getOption('date')
            ? explode(',', $this->getOption('date'))
            : [ null ];

        $titles = explode(',', $this->getOption('title'));

        $coverages = $this->getOption('coverage')
            ? explode(',', $this->getOption('coverage'))
            : [ null ];

        if ($this->getOption('combine')) {
            if (!isset($batchSize)) {
                throw new ArgumentException(
                    '--batch must be given with --combine'
                );
            }

            $listSize = max(count($dates), count($titles), count($coverages));

            if (
                (count($dates) > 1 && count($dates) != $listSize)
                || (count($titles) > 1 && count($titles) != $listSize)
                || (count($coverages) > 1 && count($coverages) != $listSize)
            ) {
                throw new ArgumentException(
                    'With --combine all lists of length > 1 must have the same size'
                );
            }

            $totalPageCount = $listSize * $batchSize;
        } elseif (isset($batchSize)) {
            $totalPageCount =
                count($dates) * count($titles) * count($coverages) * $batchSize;
        } else {
            if (count($dates) > 1) {
                throw new ArgumentException(
                    '--batch must be given with multiple dates'
                );
            }

            if (count($titles) > 1) {
                throw new ArgumentException(
                    '--batch must be given with multiple titles'
                );
            }

            if (count($coverages) > 1) {
                throw new ArgumentException(
                    '--batch must be given with multiple coverages'
                );
            }

            $totalPageCount = null;
        }

        if (!$this->getOption('process-only')) {
            $scanner = new Scanner(
                (bool)$this->getOption('adf'),
                $batchStart,
                $batchIncrement,
                $device,
                $filenamePrefix,
                $format,
                $this->getOption('geometry'),
                $this->getOption('mode'),
                $this->getProgressReporter(),
                $this->getOption('resolution'),
                $totalPageCount
            );

            $scanner->run();
        }

        if (!$scanOnly) {
            $filenames = $this->getOperand('file')
                ?: glob("$filenamePrefix.[0-9][0-9].$format", GLOB_ERR);

            if ($this->getOption('reverse')) {
                $filenames = array_reverse($filenames);
            }

            $processor = new Processor(
                RdfaData::newFromIterable(
                    [
                        'dc:audience'  => $this->getOption('audience'),
                        'dc:creator'   => $this->getOption('creator'),
                        'dc:language'
                        => $this->getOption('language')
                        ?? $this->conf_['dc:language'],
                        'dc:publisher' => $this->getOption('publisher'),
                        'dc:type'
                        => $this->getOption('type') ?? $this->conf_['dc:type']
                    ]
                ),
                $dates,
                $titles,
                $coverages,
                $batchSize,
                $this->getOption('combine') ?? false,
                $filenames,
                $this->getOption('rotate') ? 90 : null,
                $batchStart,
                $this->getOption('pdf') ?? false,
                $this->getProgressReporter()
            );

            $processor->run();


            if (!$this->getOption('no-remove-raw-files')) {
                foreach ($filenames as $filename) {
                    unlink($filename);
                }
            }
        }

        $this->saveState();

        return 0;
    }

    protected function loadState(): void
    {
        $this->stateFile_ =
            (new XdgFileFinder(null, 'STATE'))->find('scan.json');

        if (is_readable($this->stateFile_)) {
            $this->state_ =
                json_decode(file_get_contents($this->stateFile_), true);
        } else {
            $this->state_ = [];
        }

        if (
            isset($this->conf_['device'])
            && (!isset($this->state_['device'])
                || $this->getOption('refresh-device'))
        ) {
            $devices = shell_exec(static::LIST_DEVICES_CMD);

            if (is_string($devices)) {
                $matchingDevices = preg_grep(
                    '/' . $this->conf_['device'] . '/',
                    explode(' ', $devices)
                );

                if ($matchingDevices) {
                    $this->state_['device'] = reset($matchingDevices);
                }
            }
        }
    }

    protected function saveState(): void
    {
        file_put_contents($this->stateFile_, json_encode($this->state_));
    }
}
