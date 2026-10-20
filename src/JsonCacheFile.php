<?php

namespace alcamo\scan;

use alcamo\conf\{XdgFileFinder, JsonFileParser, LoaderInterface};
use alcamo\exception\WriteError;

/**
 * @brief JSON file used for caching
 *
 * This class loads a JSON file (creating it if necessary) constisting of one
 * JSON object. The object is exposed via the ArrayAccess and IteratorAggregate
 * interfaces. It is written back to the file upon destruction or by explicit
 * invocation of persist().
 *
 * Thus, the implementation does not support parallel access by more than one
 * program. It is meant to transmit data from one invocation of a command-line
 * program to another one.
 *
 * @date Last reviewed 2026-10-19
 */
class JsonCacheFile implements \Countable, \ArrayAccess, \IteratorAggregate
{
    ///< Cache file base name without suffix
    public const FILE_BASE_NAME = null;

    ///< CACHE or RUNTIME
    public const CACHE_TYPE = null;

    ///< Subdirectory in the directory cache or runtime directory
    public const SUBDIR = 'alcamo';

    protected $pathname_; ///< string
    protected $data_;     ///< object

    /**
     * @param $fileBaseName Filename without path and without
     * suffix. [Default: class constant FILE_BASE_NAME]
     *
     * @param $cacheType Cache type, i.e. `CACHE` or
     * `RUNTIME`. [Default: class constant CACHE_TYPE]
     *
     * @param $subdir Subdirectory in the directory cache or runtime
     * directory. [Default: class constant SUBDIR]
     */
    public function __construct(
        ?string $fileBaseName = null,
        ?string $cacheType = null,
        ?string $subdir = null
    ) {
        $this->pathname_ =
            (new XdgFileFinder(
                $subdir ?? static::SUBDIR,
                $cacheType ?? static::CACHE_TYPE
            ))
            ->find(($fileBaseName ?? static::FILE_BASE_NAME) . '.json');

        $this->data_ = file_exists($this->pathname_)
            ? new JsonFileParser()
            ->parse($this->pathname_, LoaderInterface::AS_OBJECT)
            : (object)[];
    }

    /// Call persist()
    public function __destruct()
    {
        $this->persist();
    }

    public function getPathname(): string
    {
        return $this->pathname_;
    }

    public function getData(): object
    {
        return $this->data_;
    }

    public function count(): int
    {
        return count((array)$this->data_);
    }

    public function offsetExists($key): bool
    {
        return isset($this->data_->$key);
    }

    public function offsetGet($key)
    {
        return $this->data_->$key ?? null;
    }

    public function offsetSet($key, $data): void
    {
        $this->data_->$key = $data;
    }

    public function offsetUnset($key): void
    {
        unset($this->data_->$key);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->data_ as $key => $value) {
            yield $key => $value;
        }
    }

    public function persist(): void
    {
        if (
            file_put_contents(
                $this->pathname_,
                json_encode($this->data_, JSON_PRETTY_PRINT)
            )
                === false
        ) {
            /** @throw alcamo::exception::WriteError if writing to the file
             *  fails. */
            throw (new WriteError())
                ->setMessageContext([ 'filename' => $path ]);
        }
    }

    /// Remove all data in the object
    public function truncate(): void
    {
        $this->data_ = (object)[];
    }
}
