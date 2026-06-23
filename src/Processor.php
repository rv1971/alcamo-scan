<?php

namespace alcamo\scan;

use alcamo\cli\{LoggableTrait, Logger};
use alcamo\exception\DataValidationFailed;
use alcamo\rdf_literal\DateLiteral;
use alcamo\rdfa\RdfaData;

class Processor
{
    use LoggableTrait;

    public const BASENAME_PROPS = [
        'dc:publisher',
        'dc:date',
        'dc:audience',
        'dc:title',
        'dc:coverage'
    ];

    private const BACKGROUND = '#00000000';

    private $logger_;           ///< Logger
    private $fixedRdfaData_;    ///< RdfaData
    private $filenames_;        ///< array
    private $rotateDegrees_;    ///< ?int
    private $createPdf_;        ///< bool

    public function __construct(
        Logger $logger,
        RdfaData $fixedRdfaData,
        array $filenames,
        ?int $rotateDegrees,
        bool $createPdf
    ) {
        $this->setLogger($logger);

        $this->fixedRdfaData_ = $fixedRdfaData;

        $this->filenames_ = $filenames;

        $this->rotateDegrees_ = $rotateDegrees;

        $this->createPdf_ = $createPdf;
    }

    public function run(
        array $dates,
        array $titles,
        array $coverages,
        ?int $batchSize,
        ?int $batchStart
    ): void {
        if (isset($batchSize)) {
            $requiredFileCount =
                max(count($dates), count($titles), count($coverages))
                * $batchSize;

            if ($requiredFileCount != count($this->filenames_)) {
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'extraMessage' =>
                        count($this->filenames_)
                        . " files given while $requiredFileCount needed"
                    ]
                );
            }

            $this->runCombineMode($dates, $titles, $coverages, $batchSize, $batchStart);
        } else {
            $this->runSingleDocumentMode($dates[0], $titles[0], $coverages[0]);
        }
    }

    protected function runCombineMode(
        array $dates,
        array $titles,
        array $coverages,
        ?int $batchSize,
        ?int $batchStart
    ): void {
        $fileNo = $batchStart;

        $listSize = max(
            count($dates),
            count($titles),
            count($coverages)
        );

        if (count($dates) == 1) {
            $dates = array_fill(0, $listSize, $dates[0]);
        }

        if (count($titles) == 1) {
            $titles = array_fill(0, $listSize, $titles[0]);
        }

        if (count($coverages) == 1) {
            $coverages = array_fill(0, $listSize, $coverages[0]);
        }

        for ($i = 0; $i < $listSize; $i++) {
            $this->processBatch(
                $this->fixedRdfaData_->replace(
                    RdfaData::newFromIterable(
                        [
                            [ 'dc:coverage', $coverages[$i] ],
                            [ 'dc:date', new DateLiteral($dates[$i]) ],
                            [ 'dc:title', $titles[$i] ]
                        ]
                    )
                ),
                array_slice(
                    $this->filenames_,
                    $fileNo,
                    $batchSize
                )
            );

            $fileNo += $batchSize;
        }
    }

    protected function runSingleDocumentMode(
        string $date,
        string $title,
        string $coverage
    ): void {
        $this->processBatch(
            $this->fixedRdfaData_->replace(
                RdfaData::newFromIterable(
                    [
                        [ 'dc:coverage', $coverage ],
                        [ 'dc:date', $date ],
                        [ 'dc:title', $title ]
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
        $exiftool = new Exiftool($this->logger_);

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

            $exiftool->applyRdfaData(
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

                    $exiftool->applyRdfaData($rdfaData, $tmpname, $filename);

                    unlink($tmpname);
                } else {
                    $exiftool->applyRdfaData($rdfaData, $filenames[$i], $filename);
                }
            }
        }
    }
}
