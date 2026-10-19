<?php

namespace alcamo\scan;

use PHPUnit\Framework\TestCase;

class JsonCacheFileTest extends TestCase
{
    /**
     * @dataProvider basicsProvider
     */
    public function testBasics($fileBaseName, $cacheType, $subdir): void
    {
        $expectedPathname = __DIR__
            . DIRECTORY_SEPARATOR . strtolower($cacheType)
            . DIRECTORY_SEPARATOR . $subdir
            . DIRECTORY_SEPARATOR . $fileBaseName . '.json';

        if (file_exists($expectedPathname)) {
            unlink($expectedPathname);
        }

       putenv('XDG_CACHE_HOME=' . __DIR__ . DIRECTORY_SEPARATOR . 'cache');
       putenv('XDG_RUNTIME_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . 'runtime');

       $jsonCacheFile = new JsonCacheFile($fileBaseName, $cacheType, $subdir);

       $this->assertSame(
           $expectedPathname,
           $jsonCacheFile->getPathname()
       );

       $this->assertSame(0, count($jsonCacheFile));

       $this->assertFalse(isset($jsonCacheFile['foo']));

       $jsonCacheFile['foo'] = 'FOO';
       $jsonCacheFile['bar'] = [ 'BAR' ];

       $jsonCacheFile = null;

       $jsonCacheFile = new JsonCacheFile($fileBaseName, $cacheType, $subdir);

       $this->assertSame(2, count($jsonCacheFile));

       $this->assertTrue(isset($jsonCacheFile['foo']));

       $this->assertSame($jsonCacheFile['foo'], 'FOO');
       $this->assertSame($jsonCacheFile['bar'], [ 'BAR' ]);

       $expectedData = [ [ 'foo', 'FOO' ], [ 'bar', [ 'BAR' ] ] ];

       $i = 0;

       foreach ($jsonCacheFile as $key => $value) {
           $this->assertSame($expectedData[$i][0], $key);
           $this->assertSame($expectedData[$i++][1], $value);
       }

       $this->assertSame(2, $i);

       unset($jsonCacheFile['foo']);

       $jsonCacheFile = null;

       $jsonCacheFile = new JsonCacheFile($fileBaseName, $cacheType, $subdir);

       $this->assertFalse(isset($jsonCacheFile['foo']));

       $this->assertTrue(isset($jsonCacheFile['bar']));

       $jsonCacheFile->truncate();

       $jsonCacheFile = null;

       $jsonCacheFile = new JsonCacheFile($fileBaseName, $cacheType, $subdir);

       $this->assertEquals((object)[], $jsonCacheFile->getData());

       $jsonCacheFile = null;

       unlink($expectedPathname);
    }

    public function basicsProvider(): array
    {
        return [
            [ 'file1', 'CACHE', 'subdir1' ],
            [ 'file2', 'RUNTIME', 'subdir2' ],
        ];
    }
}
