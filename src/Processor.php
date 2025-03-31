<?php

namespace alcamo\scan;

use alcamo\cli\ProgressReporter;
use alcamo\exception\{DataValidationFailed, ProcessFailed};
use alcamo\rdfa\{AbstractDateTimeStmt, RdfaData};

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

    private $fixedRdfaData_;    ///< RdfaData
    private $dates_;            ///< array
    private $titles_;           ///< array
    private $coverages_;        ///< array
    private $batchSize_;        ///< array
    private $filenames_;        ///< array
    private $rotateDegrees_;    ///< ?int
    private $createPdf_;        ///< bool
    private $progressReporter_; ///< ProgressReporter

    public function __construct(
        RdfaData $fixedRdfaData,
        array $dates,
        array $titles,
        array $coverages,
        ?int $batchSize,
        array $filenames,
        ?int $rotateDegrees,
        bool $createPdf,
        ProgressReporter $progressReporter
    ) {
        $this->fixedRdfaData_ = $fixedRdfaData;

        $this->dates_ = $dates;

        $this->titles_ = $titles;

        $this->coverages_ = $coverages;

        $this->batchSize_ = $batchSize;

        $this->filenames_ = $filenames;

        $this->rotateDegrees_ = $rotateDegrees;

        $this->createPdf_ = $createPdf;

        $this->progressReporter_ = $progressReporter;

        if (isset($this->batchSize_)) {
            $requiredFileCount = count($this->dates_)
                * count($this->titles_)
                * count($this->coverages_)
                * $this->batchSize_;

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
            $fileNo = 0;

            foreach ($this->dates_ as $date) {
                foreach ($this->titles_ as $title) {
                    foreach ($this->coverages_ as $coverage) {
                        $this->processBatch(
                            $this->fixedRdfaData_->replace(
                                RdfaData::newFromIterable(
                                    [
                                        'dc:coverage' => $coverage,
                                        'dc:date' => $date,
                                        'dc:title' => $title
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
            }
        } else {
            $this->processBatch(
                $this->fixedRdfaData_->replace(
                    RdfaData::newFromIterable(
                        [
                            'dc:coverage' => $this->coverages_[0],
                            'dc:date' => $this->dates_[0],
                            'dc:title' => $this->titles_[0]
                        ]
                    )
                ),
                $this->filenames_
            );
        }
    }

    protected function createBasename(RdfaData $rdfaData): string
    {
        $props = [];

        foreach (static::BASENAME_PROPS as $curie) {
            if (isset($rdfaData[$curie])) {
                if ($rdfaData[$curie] instanceof AbstractDateTimeStmt) {
                    $props[] = $rdfaData[$curie]->format('Y-m-d');
                } else {
                    $props[] = $rdfaData[$curie];
                }
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
            . ($fileCount > 1 ? '.%02.d' : '');

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
        $this->progressReporter_->write("Writing $outfilename ...");

        $cmdLine = 'exiftool '
            . "-o $outfilename "
            . '-HostComputer=' . getenv('HOSTNAME') . ' ';

        foreach ($rdfaData as $curie => $value) {
            if (isset(static::EXIFTOOL_MAP[$curie])) {
                if ($value instanceof AbstractDateTimeStmt) {
                    $value = $value->format('Y-m-d');
                }

                $cmdLine .=
                    '-' . static::EXIFTOOL_MAP[$curie] . "='$value' ";
            }
        }

        $cmdLine .= $infilename;

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
