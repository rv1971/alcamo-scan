<?php

namespace alcamo\scan;

use alcamo\conf\FileParserInterface;
use alcamo\uri\FileUriFactory;

class XmlFileParser implements FileParserInterface
{
    public function parse(string $filename)
    {
        return ConfDocument::newFromUri(
            (new FileUriFactory())->create($filename)
        );
    }
}
