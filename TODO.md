* create alcamo-xml-conf
  * XmlFileParser
  * loader
    * using an XmlFileParser by default
    * able to replace schema location in an example XML file, perhap
      with dom means, than it could also insert dc:created
* create and test separate class to Exiftool with a run() method


# alcamo-scan

* add config option scan-format
  * set to format by default if not specified
  * if differs from format, add coinversion step to processor
  * add scan-format=png to plustek device settings
* add specific functionality for Plustek to scan also infrared and remove dust

    scan -t test-visible -D plustek -f png
    scan -t test-ir -D plustek -f png --source 'Transparency Adapter Infrared'
    convert test-ir.png -threshold 67% -negate test-ir-bw.png
    convert test-visible.png test-ir-bw.png -compose multiply -composite -roll +0-9 test-correction.png
    convert test-visible.png test-correction.png -compose lighten -composite test-final.png

This implies that scanimage is called always for single images. Perhaps it is more useful to add a new cli to alcamo-scan for that.
