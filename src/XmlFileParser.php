<?php

namespace alcamo\scan;

use alcamo\conf\FileParserInterface;
use alcamo\uri\FileUriFactory;

class XmlFileParser implements FileParserInterface
{
    public function parse(string $path, ?int $flags = null)
    {
        return ConfDocument::newFromUri(
            (new FileUriFactory())->create($path)
        );
    }
}
