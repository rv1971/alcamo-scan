<?php

namespace alcamo\scan;

use alcamo\cli\AbstractCli;
use alcamo\rdfa\RdfaData;
use GetOpt\{ArgumentException, GetOpt, Operand};

class ScanCli extends AbstractCli
{
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
                'Dublin core date values.',
                'comma-separated'
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
                'Only do scan of even pages.'
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
                'Only do scan of odd pages.'
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
                'Resolution. [' . Scanner::DEFAULT_RESOLUTION . ']',
                'dpi',
                Validator::class . '::validateResolution'
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

    public function innerRun(): int
    {
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
                    'No filename operands can be given in combined mode'
                );
            }
        }

        $batchSize = $this->getOption('batch');

        $filenamePrefix = $scanOnly && $this->getOperand('file')
            ? $this->getOperand('file')[0]
            : 'scan.' . date('Ydm');

        $format = $this->getOption('format') ?? self::DEFAULT_FORMAT;

        $dates = $this->getOption('date')
            ? explode(',', $this->getOption('date'))
            : [ null ];

        $titles = explode(',', $this->getOption('title'));

        $coverages = $this->getOption('coverage')
            ? explode(',', $this->getOption('coverage'))
            : [ null ];

        if (!isset($batchSize)) {
            if (count($dates) > 1) {
                throw new ArgumentException(
                    '--batch-size must be given with multiple dates'
                );
            }

            if (count($titles) > 1) {
                throw new ArgumentException(
                    '--batch-size must be given with multiple titles'
                );
            }

            if (count($coverages) > 1) {
                throw new ArgumentException(
                    '--batch-size must be given with multiple coverages'
                );
            }
        }

        $totalPageCount = isset($batchSize)
            ? (count($dates) * count($titles) * count($coverages) * $batchSize)
            : null;

        if (!$this->getOption('process-only')) {
            $scanner = new Scanner(
                (bool)$this->getOption('adf'),
                $this->getOption('batch-start'),
                $this->getOption('device'),
                $filenamePrefix,
                $format,
                $this->getOption('geometry'),
                $this->getOption('mode'),
                $this->getProgressReporter(),
                $this->getOption('resolution'),
                ($this->getOption('odd')
                 ? 1
                 : ($this->getOption('even') ? 2 : null)),
                $totalPageCount
            );

            $scanner->run();
        }

        if (!$scanOnly) {
            $filenames = $this->getOperand('file')
                ?: glob("$filenamePrefix.[0-9][0-9].$format", GLOB_ERR);

            $processor = new Processor(
                RdfaData::newFromIterable(
                    [
                        'dc:audience'  => $this->getOption('audience'),
                        'dc:creator'   => $this->getOption('creator'),
                        'dc:language'  => $this->getOption('language'),
                        'dc:publisher' => $this->getOption('publisher'),
                        'dc:type'      => $this->getOption('type')
                    ]
                ),
                $dates,
                $titles,
                $coverages,
                $batchSize,
                $filenames,
                $this->getOption('rotate') ? 90 : null,
                $this->getOption('pdf') ?? false,
                $this->getProgressReporter()
            );

            $processor->run();


            if (
                !$this->getOption('process-only')
                && !$this->getOption('no-remove-raw-files')
            ) {
                foreach ($filenames as $filename) {
                    unlink($filename);
                }
            }
        }

        return 0;
    }
}
