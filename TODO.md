* implement jpeg quality (80% as static constant) and convert any jpeg accordingly
** see https://www.php.net/manual/en/imagick.setcompressionquality.php
* implement option --no-remove-raw-files
* if --pdf, put together files of batch (if any) and apply exit to the
  result, otherwise apply exif to each single file
* check whether higher buffer sizes are useful
