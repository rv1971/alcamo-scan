<?php

namespace alcamo\scan;

use alcamo\cli\{LoggableTrait, Logger};
use alcamo\exception\ProcessFailed;
use alcamo\rdfa\{AbstractRdfaData, ImmutableRdfaData};

/**
 * @brief Wrapper for the `exiftool` program
 *
 * @invariant Immutable class.
 *
 * @date Last reviewed 2026-06-22
 */
class Exiftool
{
    use LoggableTrait;

    public const EXIFTOOL_MAP = [
        'dc:coverage'  => 'Coverage',
        'dc:creator'   => 'Creator',
        'dc:date'      => 'Date',
        'dc:language'  => 'Language',
        'dc:publisher' => 'Publisher',
        'dc:title'     => 'Title',
        'dc:type'      => 'Type'
    ];

    /// Pathname of exiftool binary
    public const EXIFTOOL = '/usr/bin/exiftool';

    private $exiftool_; ///< Pathname of exiftool binary

    /**
     * @param $logger Logger object.
     *
     * @param $exiftool Pathname of the `exiftool` binary.
     */
    public function __construct(
        ?Logger $logger = null,
        ?string $exiftool = null
    ) {
        $this->setLogger($logger);
        $this->exiftool_ = $exiftool ?? static::EXIFTOOL;
    }

    public function getExiftool(): string
    {
        return $this->exiftool_;
    }

    public function applyRdfaData(
        $rdfaData,
        string $infilename,
        string $outfilename
    ): void {
        if (!($rdfaData instanceof AbstractRdfaData)) {
            $rdfaData = ImmutableRdfaData::newFromIterable($rdfaData);
        }

        if (isset($this->logger_)) {
            $this->logger_->notice("Writing $outfilename ...");
        }

        $cmdLine = "$this->exiftool_ "
            . "-o $outfilename "
            . '-HostComputer=' . getenv('HOSTNAME') . ' ';

        foreach (static::EXIFTOOL_MAP as $curie => $prop) {
            if (isset($rdfaData[$curie])) {
                $cmdLine .= "-$prop='{$rdfaData->getFirstObject($curie)}' ";
            }
        }

        $cmdLine .= $infilename;

        if (isset($this->logger_)) {
            $this->logger_->info($cmdLine);
        }

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
