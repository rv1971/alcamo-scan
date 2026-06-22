<?php

namespace alcamo\scan;

use alcamo\cli\{AbstractCli, Logger};
use alcamo\xml_conf\{Loader, XmlFileParser};
use alcamo\dom\DocumentValidator;
use alcamo\dom\schema\FixedSchemaSimpleTypeValidator;
use alcamo\exception\WriteError;
use alcamo\rdfa\RdfaData;
use alcamo\uri\FileUriFactory;
use GetOpt\{ArgumentException, GetOpt, Operand};

class ScanCli extends AbstractCli
{
    public const OPTIONS =
        [
            'audience' => [
                'u',
                GetOpt::REQUIRED_ARGUMENT,
                'Dublin core audience.'
            ],
            'batch' => [
                'b',
                GetOpt::OPTIONAL_ARGUMENT,
                'Multiple pages per RDF tuple. [All available pages]',
                'count'
            ],
            'batch-start' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Start with this page number.',
                'no'
            ],
            'batch-increment' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Increment page numbers by this.',
                'count'
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
                'Only do raw scan of even pages.'
            ],
            'format' => [
                'f',
                GetOpt::REQUIRED_ARGUMENT,
                'File format.'
            ],
            'geometry' => [
                'g',
                GetOpt::REQUIRED_ARGUMENT,
                'Scan area geometry.',
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
                'Scan mode: color|gray|binary.',
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
                'Resolution.',
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
            'source' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Source ID as specified in the config file.'
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
Reads settings and device aliases from $XDG_CONFIG_HOME/alcamo/scan-conf.xml.
EOT;

    public const CONF_FILENAME = 'scan-conf.xml';

    protected const EXAMPLE_CONF_PATHNAME =
        __DIR__ . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'conf'
        . DIRECTORY_SEPARATOR . 'example-conf.xml';

    protected const XSD_PATHNAME =
        __DIR__ . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'xsd'
        . DIRECTORY_SEPARATOR . 'scan-conf.xsd';

    protected const CONF_OPTIONS = [
        'language'   => 'dc:language',
        'type'       => 'dc:type',
        'format'     => 'format',
        'geometry'   => 'geometry',
        'mode'       => 'mode',
        'resolution' => 'resolution',
        'source'     => 'source'
    ];

    protected const OPTION_TYPES = [
        'batch'           => [ ConfDocument::MAIN_NS, 'PageNo' ],
        'batch-increment' => [ ConfDocument::MAIN_NS, 'PageNoIncrement' ],
        'batch-start'     => [ ConfDocument::MAIN_NS, 'PageNo' ]
    ];

    private $conf_; ///< ConfDocument

    public function process($arguments = null)
    {
        parent::process($arguments);

        $this->conf_ = Loader::newFromDocumentClass(ConfDocument::class)
            ->createFromXmlPathnameIfNotExistsAndLoad(
                static::CONF_FILENAME,
                static::EXAMPLE_CONF_PATHNAME,
                static::XSD_PATHNAME,
                $pathname
            );

        if (isset($pathname)) {
            $this->getLogger()->notice("Created $pathname");
        }

        $this->conf_->chooseDevice(
            $this->getOption('device') ?? getenv('SANE_DEFAULT_DEVICE')
        );

        /* Add defaults to option descriptions for help text. */
        foreach (static::CONF_OPTIONS as $cmdLineOption => $option) {
            $getOptOption = $this->getOptionObject($cmdLineOption);

            if (isset($this->conf_->$option)) {
                $getOptOption->setDescription(
                    $getOptOption->getDescription()
                    . " [{$this->conf_->$option}]"
                );
            }
        }
    }

    public function innerRun(): int
    {
        foreach (static::CONF_OPTIONS as $cmdLineOption => $option) {
            if ($this->getOption($cmdLineOption) !== null) {
                $this->conf_->$option = $this->getOption($cmdLineOption);
            }
        }

        (new DocumentValidator())->validate($this->conf_);

        $dates = $this->getOption('date')
            ? explode(',', $this->getOption('date'))
            : [ null ];

        $titles = explode(',', $this->getOption('title'));

        $coverages = $this->getOption('coverage')
            ? explode(',', $this->getOption('coverage'))
            : [ null ];

        $valuesToValidate = [];

        foreach (static::OPTION_TYPES as $optionName => $typeXName) {
            if ($this->getOption($optionName) !== null) {
                $valuesToValidate[$optionName] =
                    [ $this->getOption($optionName), $typeXName ];
            }
        }

        if ($this->getOption('date')) {
            $valuesToValidate['date'] =
                [ implode(' ', $dates), [ ConfDocument::MAIN_NS, 'Dates' ] ];
        }

        if ($valuesToValidate) {
            $errors =
                FixedSchemaSimpleTypeValidator::newFromSchema(
                    $this->conf_->getSchema()
                )
                ->validate($valuesToValidate);

            if ($errors) {
                foreach ($errors as $optionName => $error) {
                    $this->getLogger()->critical(
                        "option --$optionName: $error"
                    );
                }

                return 1;
            }
        }

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
        if ($this->getOption('batch') !== null) {
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

        if (isset($batchSize)) {
            $listSize = max(count($dates), count($titles), count($coverages));

            if (
                (count($dates) > 1 && count($dates) != $listSize)
                || (count($titles) > 1 && count($titles) != $listSize)
                || (count($coverages) > 1 && count($coverages) != $listSize)
            ) {
                throw new ArgumentException(
                    'All lists of length > 1 must have the same size'
                );
            }

            $totalPageCount = $listSize * $batchSize;
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

        $filenamePrefix = $scanOnly && $this->getOperand('file')
            ? $this->getOperand('file')[0]
            : 'scan.' . date('Ydm\THis');

        if (!$this->getOption('process-only')) {
            $scanner = new Scanner(
                $this->conf_,
                $this->getLogger(),
                $batchStart,
                $batchIncrement,
                $filenamePrefix,
                $totalPageCount
            );

            $scanner->run();
        }

        if (!$scanOnly) {
            $filenames = $this->getOperand('file')
                ?: glob(
                    "$filenamePrefix.[0-9][0-9][0-9][0-9].{$this->conf_->format}",
                    GLOB_ERR
                );

            if ($this->getOption('reverse') !== null) {
                $filenames = array_reverse($filenames);
            }

            switch (true) {
                case $this->getOption('no-pdf'):
                    $createPdf = false;
                    break;

                case $this->getOption('pdf'):
                    $createPdf = true;
                    break;

                case $this->conf_->autopdf:
                    $createPdf = $batchSize != 1;
                    break;

                default:
                    $createPdf = false;
            }

            $processor = new Processor(
                $this->getLogger(),
                RdfaData::newFromIterable(
                    [
                        [ 'dc:audience',  $this->getOption('audience') ],
                        [ 'dc:creator',   $this->getOption('creator') ],
                        [ 'dc:language',  $this->conf_->{'dc:language'} ],
                        [ 'dc:publisher', $this->getOption('publisher') ],
                        [ 'dc:type',      $this->conf_->{'dc:type'} ]
                    ]
                ),
                $dates,
                $titles,
                $coverages,
                $batchSize,
                $filenames,
                $this->getOption('rotate') ? 90 : null,
                $batchStart,
                $createPdf,
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
