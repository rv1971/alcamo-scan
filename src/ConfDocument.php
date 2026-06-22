<?php

namespace alcamo\scan;

use alcamo\xml_conf\AbstractConfDocument;
use alcamo\exception\{DataNotFound, ProcessFailed};

class ConfDocument extends AbstractConfDocument
{
    public const MAIN_NS = 'https://github.com/rv1971/alcamo-scan/conf/';

    public const TYPE_CONVERTER_MAP =
        [
            self::MAIN_NS . ' Geometry'
            => __CLASS__ . '::createGeometryFromString'
        ]
        + parent::TYPE_CONVERTER_MAP;

    private const LSUSB_CMD = '/usr/bin/lsusb';

    /// Convert a geometry string to a 2- or 4-element array
    public static function createGeometryFromString(string $value): \stdClass
    {
        $a = preg_split('/[x\+]/', $value);

        return (object)array_combine(
            isset($a[2]) ? [ 'w', 'h', 'l', 't' ] : [ 'w', 'h' ],
            $a
        );
    }

    private $defaults_; ///< Element
    private $device_;  ///< Element

    /// Choose a configured device by ID or name
    public function chooseDevice(string $deviceId): void
    {
        if ($deviceId) {
            /** Look for a device with the specified ID or name, if given. */
            $this->device_ = $this->query(
                "/*/c:device[@id = '$deviceId' or @name = '$deviceId']"
            )[0];
        } else {
            /** If no device ID is given, look for a USB device at
             *  `/dev/scanner`. */
            $devicePath = readlink('/dev/scanner');

            if ($devicePath !== false) {
                $a = explode('/', $devicePath);
                $usbDevice = array_pop($a);
                $usbBus = array_pop($a);

                $cmdLine = self::LSUSB_CMD . " -s $usbBus:$usbDevice";

                $lsusbOutput = exec($cmdLine, $output, $resultCode);

                if ($resultCode) {
                    throw (new ProcessFailed())->setMessageContext(
                        [ 'command' => $cmdLine ]
                    );
                }

                $usbId = explode(' ', $lsusbOutput)[5];

                $this->device_ =
                    $this->query("/*/c:device[@usbId = '$usbId']")[0];

                $this->device_->setAttribute(
                    'name',
                    "{$this->device_->backend}:libusb:$usbBus:$usbDevice"
                );
            }
        }

        if (!isset($this->device_)) {
            throw (new DataNotFound())->setMessageContext(
                [ 'extraMessage' => "scanner $deviceId not configured" ]
            );
        }
   }

    public function __isset(string $optionName): bool
    {
        return $this->__get($optionName) !== null;
    }

    public function __get(string $optionName)
    {
        switch ($optionName) {
            case 'backendMode':
                return $this->device_->query(
                    "c:modes/c:mode[@id = '{$this->mode}']"
                )[0];

            case 'backendSource':
                return $this->device_->query(
                    "c:sources/c:source[@id = '{$this->source}']"
                )[0];

            case 'geometry':
                $geometryId = $this->device_->$optionName
                    ?? $this->defaults_->$optionName;

                return $this->query(
                    "/*/c:geometries/c:geometry[@id = '$geometryId']"
                )[0];

            default:
                return $this->device_->$optionName
                    ?? $this->defaults_->$optionName;
        }
    }

    public function __set($optionName, $value): void
    {
        switch (true) {
            case substr($optionName, 0, 3) == 'dc:':
                $this->device_
                    ->setAttributeNS(self::DC_NS, $optionName, $value);
                break;

            case $optionName == 'geometry':
                $geometries = $this->query('/*/c:geometries')[0];

                if (
                    isset($geometries->query("c:geometry[@id = '$value']")[0])
                ) {
                    $this->device_->setAttribute($optionName, $value);
                } else {
                    /* If a non-predefined geometry is used, create an entry
                     * with id '_' for it. This implies that the geometry is
                     * then syntax-checked in the overall validation. */
                    $geometry = $this->createElementNS(
                        self::MAIN_NS,
                        'geometry',
                        $value
                    );

                    $geometry->setAttribute('id', '_');

                    $geometries->appendChild($geometry);

                    $this->device_->setAttribute($optionName, '_');
                }

                break;

            default:
                $this->device_->setAttribute($optionName, $value);
        }
    }

    protected function afterLoad(): void
    {
        parent::afterLoad();

        $this->getXPath()->registerNamespace('c', self::MAIN_NS);

        $this->defaults_ = $this->query('/*/c:defaults')[0];
    }
}
