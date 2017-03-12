Deepzoom
=======

Tile generator for use with [OpenSeadragon], [OpenLayers] and various viewers.

This library can be used as a standalone tool, or with the open source digital
library [Omeka S] via the module [IIIF Server].


Example of implementation
-------------------------

```php
    // Setup the Zoomify library.
    $processor = new \Deepzoom\Deepzoom($config);

    // Process a source file and save tiles in a destination folder.
    $result = $processor->process($source, $destination);
```

Supported image libraries
-------------------------

The format of the image source can be anything that is managed by the image
libray:

- PHP Extension [GD] (>=2.0)
- PHP extension [Imagick] (>=6.0)
- Command line `convert` [ImageMagick] (>=6.0)

The PHP library `exif` should be installed (generally enabled by default).

History
-------

The source is a mix of the Laravel plugin [Deepzoom] of Jeremy Tubbs, the
standalone open zoom builder [Deepzoom.php] of Nicolas Fabre, the [blog] of
Olivier Mariott, and the [Zoomify converter].


Warning
-------

Use it at your own risk.

It’s always recommended to backup and to check your files and your databases
regularly so you can roll back if needed.


License
-------

This library is licensed under the [MIT] license.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


Copyright
---------

* Copyright 2015 Jeremy Tubbs
* Copyright 2017 Daniel Berthereau (Daniel.github@Berthereau.net)


[IIIF Server]: https://github.com/Daniel-KM/Omeka-S-module-IiifServer
[OpenSeadragon]: https://openseadragon.github.io
[OpenLayers]: https://openlayers.org/en/latest/examples/zoomify.html
[OpenLayers Zoom]: https://github.com/Daniel-KM/OpenLayersZoom
[Omeka S]: https://omeka.org/s
[Omeka Classic]: https://omeka.org
[GD]: https://secure.php.net/manual/en/book.image.php
[Imagick]: https://php.net/manual/en/book.imagick.php
[ImageMagick]: https://www.imagemagick.org/
[Deepzoom]: https://github.com/jeremytubbs/deepzoom
[Deepzoom.php]: https://github.com/nfabre/deepzoom.php
[blog]: http://omarriott.com/aux/leaflet-js-non-geographical-imagery/
[Zoomify converter]: https://github.com/Daniel-KM/Omeka-S-module-IiifServer/tree/master/src/libraries/Zoomify
[MIT]: https://www.gnu.org/licenses/gpl-3.0.html
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
