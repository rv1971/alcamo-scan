<?php

namespace alcamo\scan;

use PHPUnit\Framework\TestCase;

class UsbDeviceTest extends TestCase
{
    public function testBasics(): void
    {
        $this->assertNull(UsbDevice::newFromPathname('/foo'));

        $usbData = exec(UsbDevice::LSUSB, $output);

        [ $dummy, $bus, $dummy, $device, $dummy, $id, $label ] =
            explode(' ', $usbData, 7);

        $symlinkPathname = __DIR__ . DIRECTORY_SEPARATOR . 'usbdev';

        if (is_link($symlinkPathname)) {
            unlink($symlinkPathname);
        }

        symlink(
            sprintf('/dev/bus/usb/%03d/%03d', $bus, $device),
            $symlinkPathname
        );

        $usbDevice = UsbDevice::newFromPathname($symlinkPathname);

        unlink($symlinkPathname);

        $this->assertSame((int)$bus, $usbDevice->getBus());
        $this->assertSame((int)$device, $usbDevice->getDevice());
        $this->assertSame($id, $usbDevice->getId());
        $this->assertSame($label, $usbDevice->getLabel());
    }
}
