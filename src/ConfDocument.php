<?php

namespace alcamo\scan;

use alcamo\cli\Logger;
use alcamo\xml_conf\ConfDocument as BaseConfDocument;
use alcamo\exception\DataNotFound;

class ConfDocument extends BaseConfDocument
{
    public const MAIN_NS = 'https://github.com/rv1971/alcamo-scan/conf/';

    public const TYPE_CONVERTER_MAP =
        [
            self::MAIN_NS . ' Geometry'
            => __CLASS__ . '::createGeometryFromString'
        ]
        + parent::TYPE_CONVERTER_MAP;

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
    public function chooseDevice(string $deviceId, ?Logger $logger = null): void
    {
        if ($deviceId) {
            /** Look for a device with the specified ID or name, if given. */
            $this->device_ = $this->query(
                "/*/c:device[@id = '$deviceId' or @name = '$deviceId']"
            )[0];
        } else {
            /** If no device ID is given, look for a USB device at
             *  `/dev/scanner`. */
            $usbDevice = UsbDevice::newFromPathname(
                '/dev/scanner',
                $logger,
                $this->lsusb
            );

            if (isset($usbDevice)) {
                $this->device_ = $this->query(
                    "/*/c:device[@usbId = '{$usbDevice->getId()}']"
                )[0];

                $this->device_->name = sprintf(
                    '%s:libusb:%03d:%03d',
                    $this->device_->backend,
                    $usbDevice->getBus(),
                    $usbDevice->getDevice()
                );
            }
        }

        if (!isset($this->device_)) {
            throw (new DataNotFound())->setMessageContext(
                [ 'extraMessage' => "scanner $deviceId not configured" ]
            );
        }
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

            case 'exiftool':
            case 'lsusb':
            case 'scanimage':
                return $this->query('/*/c:pathnames')[0]->$optionName;

            default:
                return $this->device_->$optionName
                    ?? $this->defaults_->$optionName;
        }
    }

    public function __set($optionName, $value): void
    {
        switch (true) {
            case $optionName == 'geometry':
                $geometries = $this->query('/*/c:geometries')[0];

                if (
                    isset($geometries->query("c:geometry[@id = '$value']")[0])
                ) {
                    $this->device_->$optionName = $value;
                } else {
                    /* If a non-predefined geometry is used, create an entry
                     * with id '_' for it. This implies that the geometry is
                     * then syntax-checked in the overall validation. */
                    $geometry = $this->createElementNS(
                        self::MAIN_NS,
                        'geometry',
                        $value
                    );

                    $geometry->id = '_';

                    $geometries->appendChild($geometry);

                    $this->device_->$optionName = '_';
                }

                break;

            default:
                $this->device_->$optionName = $value;
        }
    }

    protected function afterLoad(): void
    {
        parent::afterLoad();

        $this->defaults_ = $this->query('/*/c:defaults')[0];
    }
}
