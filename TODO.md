# review configuration

* show defaults from ConfigDocument in help message
  * derive custom Help class, overriding renderOptions
  * see http://getopt-php.github.io/getopt-php/help.html
* add example scan-config.xml to package
* automatically store example XML in config location of no config file found?

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
