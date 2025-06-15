<?php

namespace alcamo\scan;

use alcamo\cli\AbstractCli;
use alcamo\rdfa\RdfaData;
use GetOpt\{ArgumentException, GetOpt, Operand};

class ScanCli extends AbstractCli
{
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
                'Instead of creating the cartesian product of the lists of '
                . 'meta data, combine them. All meta data options with more '
                . 'than one entry must have the same length.'
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
                'File format. [' .
                DeviceSettingsSanitizer::RULES['format']['default']
                . ']',
                null
            ],
            'create-example-ini' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Create an example ini file and print it to the standard output.',
            ],
            'geometry' => [
                'g',
                GetOpt::REQUIRED_ARGUMENT,
                'Scan area geometry. [' .
                DeviceSettingsSanitizer::RULES['geometry']['default']
                . ']',
                'w>x<h>[+<l>+<t>]|<name'
            ],
            'language' => [
                'l',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core language.'
            ],
            'mode' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Scan mode: color|gray|binary. [' .
                DeviceSettingsSanitizer::RULES['mode']['default']
                . ']',
                'mode'
            ],
            'no-pdf' => [
                null,
                GetOpt::NO_ARGUMENT,
                'Do not create PDF output.'
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
            'resolution' => [
                'r',
                GetOpt::REQUIRED_ARGUMENT,
                'Resolution. [' .
                DeviceSettingsSanitizer::RULES['resolution']['default']
                . ']',
                'dpi'
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
                'Dublin core type.'
            ]
        ]
        + parent::OPTIONS;

    public const OPERANDS = [
        'file' => Operand::MULTIPLE
    ];

    public const DESCRIPTION = <<<'EOT'
Reads settings and device aliases from $XDG_CONFIG_HOME/alcamo/scan.ini.
EOT;

    public function innerRun(): int
    {
        $iniFile = IniFile::newFromFilename();

        if ($this->getOption('create-example-ini')) {
            echo $iniFile->createExample();
            exit(0);
        }

        $settings = new Settings($iniFile['settings'] ?? []);

        $settings->addSettings(
            [
                'dc:language' => $this->getOption('language'),
                'dc:type'     => $this->getOption('type')
            ]
        );

        $device = $iniFile->getDevice(
            $this->getOption('device') ?? getenv('SANE_DEFAULT_DEVICE')
        );

        $device->addSettings(
            [
                'adf'        => $this->getOption('adf'),
                'format'     => $this->getOption('format'),
                'geometry'   => $this->getOption('geometry'),
                'mode'       => $this->getOption('mode'),
                'resolution' => $this->getOption('resolution')
            ]
        );

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

        /* A number of options imply --batch */
        if ($this->getOption('batch')) {
            $batchSize = $this->getOption('batch') === 1
                ? null
                : (int)$this->getOption('batch');
        } else {
            $batchSize = $this->getOption('batch-increment')
                || $this->getOption('batch-start')
                || $this->getOption('even')
                || $this->getOption('odd')
                ? null
                : 1;
        }

        $filenamePrefix = $scanOnly && $this->getOperand('file')
            ? $this->getOperand('file')[0]
            : 'scan.' . date('Ydm\THis');

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
                    '--batch with value must be given with --combine'
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
                    '--batch with value must be given with multiple dates'
                );
            }

            if (count($titles) > 1) {
                throw new ArgumentException(
                    '--batch with value must be given with multiple titles'
                );
            }

            if (count($coverages) > 1) {
                throw new ArgumentException(
                    '--batch with value must be given with multiple coverages'
                );
            }

            $totalPageCount = null;
        }

        if (!$this->getOption('process-only')) {
            $scanner = new Scanner(
                $device,
                $batchStart,
                $batchIncrement,
                $filenamePrefix,
                $this->getProgressReporter(),
                $totalPageCount
            );

            $scanner->run();
        }

        if (!$scanOnly) {
            $filenames = $this->getOperand('file')
                ?: glob(
                    "$filenamePrefix.[0-9][0-9].{$device['format']}",
                    GLOB_ERR
                );

            if ($this->getOption('reverse')) {
                $filenames = array_reverse($filenames);
            }

            switch (true) {
                case $this->getOption('no-pdf'):
                    $createPdf = false;
                    break;

                case $this->getOption('pdf'):
                    $createPdf = true;
                    break;

                case $settings['autopdf'] == 'true':
                    $createPdf = $batchSize != 1;
                    break;

                default:
                    $createPdf = false;
            }

            $processor = new Processor(
                RdfaData::newFromIterable(
                    [
                        'dc:audience'  => $this->getOption('audience'),
                        'dc:creator'   => $this->getOption('creator'),
                        'dc:language'  => $settings['dc:language'],
                        'dc:publisher' => $this->getOption('publisher'),
                        'dc:type'      => $settings['dc:type']
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
                $createPdf,
                $this->getProgressReporter()
            );

            $processor->run();


            if (!$this->getOption('no-remove-raw-files')) {
                foreach ($filenames as $filename) {
                    unlink($filename);
                }
            }
        }

        return 0;
    }
}
