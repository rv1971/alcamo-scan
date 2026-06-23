<?php

namespace alcamo\scan;

use alcamo\cli\{LoggableTrait, Logger};
use alcamo\exception\ProcessFailed;

/**
 * @brief USB device data
 *
 * @date Last reviewed 2026-06-23
 */
class UsbDevice
{
    use LoggableTrait;

    /// Pathname of lsusb binary
    public const LSUSB = '/usr/bin/lsusb';

    private $bus_;    ///< int
    private $device_; ///< int
    private $id_;     ///< string
    private $label_;  ///< string

    /**
     * @brief Create from a device pathname
     *
     * @param $path Device path such as `bus/usb/002/006` or symbolic link to
     * such a path. Only the last two components are relevant, so it does not
     * matter whether the path is absolute or relative.
     *
     * @param $logger Logger object.
     *
     * @param $lsusb Pathname of the `lsusb` binary.
     *
     * @return UsbDevice object on success. `null` if $path is not an existing
     * symbolic link and does not end in two integer components.  The latter
     * case occurs, for instance, when $path is '/dev/scanner' but no scanner
     * is connected.
     */
    public static function newFromPathname(
        string $pathname,
        ?Logger $logger = null,
        ?string $lsusb = null
    ): ?self {
        if (file_exists($pathname) && is_link($pathname)) {
            $pathname = readlink($pathname);
        }

        $a = explode('/', $pathname);
        $device = array_pop($a);
        $bus = array_pop($a);

        if (!is_numeric($device) || !is_numeric($bus)) {
            return null;
        }

        return new static($bus, $device, $logger, $lsusb);
    }

    /**
     * @param $bus USB bus.
     *
     * @param $bus USB device.
     *
     * @param $logger Logger object.
     *
     * @param $lsusb Pathname of the `lsusb` binary.
     */
    public function __construct(
        int $bus,
        int $device,
        ?Logger $logger = null,
        ?string $lsusb = null
    ) {
        $this->setLogger($logger);

        $this->bus_ = $bus;
        $this->device_ = $device;

        if (!isset($lsusb)) {
            $lsusb = static::LSUSB;
        }

        $cmdLine = "$lsusb -s $bus:$device";

        if (isset($this->logger_)) {
            $this->logger_->info($cmdLine);
        }

        $lsusbOutput = exec($cmdLine, $output, $resultCode);

        if ($resultCode) {
            throw (new ProcessFailed())->setMessageContext(
                [ 'command' => $cmdLine ]
            );
        }

        $a = explode(' ', $lsusbOutput, 7);

        $this->id_ = $a[5];
        $this->label_ = $a[6];
    }

    public function getBus(): int
    {
        return $this->bus_;
    }

    public function getDevice(): int
    {
        return $this->device_;
    }

    public function getId(): string
    {
        return $this->id_;
    }

    public function getLabel(): string
    {
        return $this->label_;
    }
}
