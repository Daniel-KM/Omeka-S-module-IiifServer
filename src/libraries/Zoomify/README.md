Zoomify
=======

Tile generator for use with [OpenSeadragon], [OpenLayers] and various viewers.

This library can be used as a standalone tool, or with the open source digital
library [Omeka S] via the module [IIIF Server], or with the old release [Omeka Classic]
via the plugin [OpenLayers Zoom].


Example of implementation
-------------------------

```php
    // Setup the Zoomify library.
    $processor = new \Zoomify\Zoomify($config);

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

[Zoomify] was a popular viewer to display large images in the past with Flash
(and now without it, of course). It’s still used in various places, because it’s
not only a viewer, but a tile builder too and it has some enterprise features.
Its popularity was related to the fact that an extension was added to a popular
commercial image application. An old description of the format can be found [here].

The Zoomify class is a port of the ZoomifyImage python script to a PHP class.
The original python script was written by Adam Smith, and was ported to PHP
(in the form of ZoomifyFileProcessor) by Wes Wright. The port to Imagick was
done by Daniel Berthereau for the [Bibliothèque patrimoniale] of [Mines ParisTech].

Ported from Python to PHP by Wes Wright
Cleanup for Drupal by Karim Ratib (kratib@open-craft.com)
Cleanup for Omeka Classic by Daniel Berthereau (daniel.github@berthereau.net)
Conversion to ImageMagick by Daniel Berthereau
Integrated in Omeka S and support a specified destination directory.


Warning
-------

Use it at your own risk.

It’s always recommended to backup and to check your files and your databases
regularly so you can roll back if needed.


License
-------

This library is licensed under the [GNU/GPL] v3.

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the
Free Software Foundation; either version 2 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc.,
59 Temple Place, Suite 330, Boston, MA  02111-1307  USA


Copyright
---------

* Copyright 2005 Adam Smith (asmith@agile-software.com)
* Copyright Wes Wright (http://greengaloshes.cc)
* Copyright Justin Henry (http://greengaloshes.cc)
* Copyright 2014-2017 Daniel Berthereau (Daniel.github@Berthereau.net)


[IIIF Server]: https://github.com/Daniel-KM/Omeka-S-module-IiifServer
[OpenSeadragon]: https://openseadragon.github.io
[OpenLayers]: https://openlayers.org/en/latest/examples/zoomify.html
[OpenLayers Zoom]: https://github.com/Daniel-KM/OpenLayersZoom
[Omeka S]: https://omeka.org/s
[Omeka Classic]: https://omeka.org
[GD]: https://secure.php.net/manual/en/book.image.php
[Imagick]: https://php.net/manual/en/book.imagick.php
[ImageMagick]: https://www.imagemagick.org/
[Zoomify]: http://www.zoomify.com/
[here]: https://ecommons.cornell.edu/bitstream/handle/1813/5410/Introducing_Zoomify_Image.pdf
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[Bibliothèque patrimoniale]: https://patrimoine.mines-paristech.fr
[Mines ParisTech]: http://mines-paristech.fr
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
