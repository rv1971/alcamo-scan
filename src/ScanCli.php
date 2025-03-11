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
            'publisher' => [
                'p',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core publisher.'
            ],
            'resolution' => [
                'r',
                GetOpt::REQUIRED_ARGUMENT,
                'Resolution. [150]',
                'dpi',
                Validator::class . '::validateResolution'
            ],
            'rotate' => [
                'R',
                GetOpt::NO_ARGUMENT,
                'Rotate 90° clockwise.'
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

    private $rdfaData_;
    private $format_;

    public function innerRun(): int
    {
        if (!$this->getOption('title')) {
            throw new ArgumentException('No title specified');
        }

        foreach (
            $this->getOption('date')
            ? explode(',', $this->getOption('date'))
            : [ null ] as $date
        ) {
            $this->format_ = $this->getOption('format') ?? self::DEFAULT_FORMAT;

            $fixedRdfaData = RdfaData::newFromIterable(
                [
                    'dc:creator'   => $this->getOption('creator'),
                    'dc:language'  => $this->getOption('language'),
                    'dc:publisher' => $this->getOption('publisher'),
                    'dc:type'      => $this->getOption('type')
                ]
            );

            foreach (explode(',', $this->getOption('title')) as $title) {
                foreach (
                    $this->getOption('coverage')
                    ? explode(',', $this->getOption('coverage'))
                    : [ null ] as $coverage
                ) {
                    $this->rdfaData_ = $fixedRdfaData->replace(
                        RdfaData::newFromIterable(
                            [
                                'dc:coverage' => $coverage,
                                'dc:created' => $date,
                                'dc:title' => $title
                            ]
                        )
                    );

                    $filename = $this->createFilename();

                    echo "$filename\n";
                }
            }
        }

        return 0;
    }

    protected function createFilename(): string
    {
        $props = [];

        foreach (
            [
                'dc:publisher',
                'dc:created',
                'dc:title',
                'dc:coverage'
            ] as $propCurie
        ) {
            if ($this->rdfaData_[$propCurie]) {
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

        return implode('.', $props) . '.' . $this->format_;
    }
}
