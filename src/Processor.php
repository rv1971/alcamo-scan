<?php

namespace alcamo\scan;

use alcamo\cli\Logger;
use alcamo\exception\{DataValidationFailed, ProcessFailed};
use alcamo\rdf_literal\DateLiteral;
use alcamo\rdfa\RdfaData;

class Processor
{
    public const BASENAME_PROPS = [
        'dc:publisher',
        'dc:date',
        'dc:audience',
        'dc:title',
        'dc:coverage'
    ];

    protected const EXIFTOOL_MAP = [
        'dc:coverage'  => 'Coverage',
        'dc:creator'   => 'Creator',
        'dc:date'      => 'Date',
        'dc:language'  => 'Language',
        'dc:publisher' => 'Publisher',
        'dc:title'     => 'Title',
        'dc:type'      => 'Type'
    ];

    private const BACKGROUND = '#00000000';

    private $logger_;           ///< Logger
    private $fixedRdfaData_;    ///< RdfaData
    private $dates_;            ///< array
    private $titles_;           ///< array
    private $coverages_;        ///< array
    private $batchSize_;        ///< array
    private $filenames_;        ///< array
    private $rotateDegrees_;    ///< ?int
    private $batchStart_;       ///< ?int
    private $createPdf_;        ///< bool

    public function __construct(
        Logger $logger,
        RdfaData $fixedRdfaData,
        array $dates,
        array $titles,
        array $coverages,
        ?int $batchSize,
        array $filenames,
        ?int $rotateDegrees,
        ?int $batchStart,
        bool $createPdf
    ) {
        $this->logger_ = $logger;

        $this->fixedRdfaData_ = $fixedRdfaData;

        $this->dates_ = $dates;

        $this->titles_ = $titles;

        $this->coverages_ = $coverages;

        $this->batchSize_ = $batchSize;

        $this->filenames_ = $filenames;

        $this->rotateDegrees_ = $rotateDegrees;

        $this->batchStart_ = $batchStart;

        $this->createPdf_ = $createPdf;

        if (isset($this->batchSize_)) {
            $requiredFileCount =  max(
                count($this->dates_),
                count($this->titles_),
                count($this->coverages_)
            ) * $this->batchSize_;

            if ($requiredFileCount != count($this->filenames_)) {
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'extraMessage' =>
                        count($this->filenames_)
                        . " files given while $requiredFileCount needed"
                    ]
                );
            }
        }
    }

    public function run(): void
    {
        if (isset($this->batchSize_)) {
            $this->runCombineMode();
        } else {
            $this->runSingleDocumentMode();
        }
    }

    protected function runCombineMode(): void
    {
        $fileNo = $this->batchStart_;

        $listSize = max(
            count($this->dates_),
            count($this->titles_),
            count($this->coverages_)
        );

        if (count($this->dates_) == 1) {
            $this->dates_ = array_fill(0, $listSize, $this->dates_[0]);
        }

        if (count($this->titles_) == 1) {
            $this->titles_ = array_fill(0, $listSize, $this->titles_[0]);
        }

        if (count($this->coverages_) == 1) {
            $this->coverages_ = array_fill(0, $listSize, $this->coverages_[0]);
        }

        for ($i = 0; $i < $listSize; $i++) {
            $this->processBatch(
                $this->fixedRdfaData_->replace(
                    RdfaData::newFromIterable(
                        [
                            [ 'dc:coverage', $this->coverages_[$i] ],
                            [ 'dc:date', new DateLiteral($this->dates_[$i]) ],
                            [ 'dc:title', $this->titles_[$i] ]
                        ]
                    )
                ),
                array_slice(
                    $this->filenames_,
                    $fileNo,
                    $this->batchSize_
                )
            );

            $fileNo += $this->batchSize_;
        }
    }

    protected function runSingleDocumentMode(): void
    {
        $this->processBatch(
            $this->fixedRdfaData_->replace(
                RdfaData::newFromIterable(
                    [
                        [ 'dc:coverage', $this->coverages_[0] ],
                        [ 'dc:date', new DateLiteral($this->dates_[0]) ],
                        [ 'dc:title', $this->titles_[0] ]
                    ]
                )
            ),
            $this->filenames_
        );
    }

    protected function createBasename(RdfaData $rdfaData): string
    {
        $props = [];

        foreach (static::BASENAME_PROPS as $curie) {
            if (isset($rdfaData[$curie])) {
                $props[] = $rdfaData[$curie]->first();
            }
        }

        return strtr(implode('.', $props), ' ', '_');
    }

    private function processBatch(RdfaData $rdfaData, array $filenames): void
    {
        if ($this->createPdf_) {
            $tmpname0 = tempnam(getcwd(), 'scan.process.');

            $tmpname = "$tmpname0.pdf";

            rename($tmpname0, $tmpname);

            $images = new \Imagick($filenames);

            if (isset($this->rotateDegrees_)) {
                foreach ($images as $image) {
                    $image->rotateImage(self::BACKGROUND, $this->rotateDegrees_);
                }
            }

            if (!$images->writeImages($tmpname, true)) {
                throw new \RuntimeException('Imagick failed to create PDF');
            }

            $this->runExiftool(
                $rdfaData,
                $tmpname,
                $this->createBasename($rdfaData) . '.pdf'
            );

            unlink($tmpname);
        } else {
            $fileCount = count($filenames);

            $filenameFormat = $this->createBasename($rdfaData)
            . ($fileCount > 1 ? '.%04.d' : '');

            for ($i = 0; $i < $fileCount; $i++) {
                $suffix = strrchr($filenames[$i], '.');
                $filename = sprintf($filenameFormat, $i + 1) . $suffix;

                if (isset($this->rotateDegrees_)) {
                    $tmpname0 = tempnam(getcwd(), 'scan.process.');

                    $tmpname = "$tmpname0.$suffix";

                    rename($tmpname0, $tmpname);

                    $image = new \Imagick($filenames[$i]);

                    $image->rotateImage(
                        self::BACKGROUND,
                        $this->rotateDegrees_
                    );

                    $image->writeImage($tmpname);

                    $this->runExiftool($rdfaData, $tmpname, $filename);

                    unlink($tmpname);
                } else {
                    $this->runExiftool($rdfaData, $filenames[$i], $filename);
                }
            }
        }
    }

    private function runExiftool(
        RdfaData $rdfaData,
        string $infilename,
        string $outfilename
    ): void {
        $this->logger_->notice("Writing $outfilename ...");

        $cmdLine = 'exiftool '
            . "-o $outfilename "
            . '-HostComputer=' . getenv('HOSTNAME') . ' ';

        foreach (static::EXIFTOOL_MAP as $curie => $prop) {
            if (isset($rdfaData[$curie])) {
                $cmdLine .= "-$prop='{$rdfaData[$curie]->first()}' ";
            }
        }

        $cmdLine .= $infilename;

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
