IIIF Server (module for Omeka S)
================================

[![Build Status](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer.svg?branch=master)](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer)

[IIIF Server] is a module for [Omeka S] that adds the [IIIF] specifications in
order to serve images like an [IIPImage] server. The full specification of the
"International Image Interoperability Framework" standard is supported
(level 2), so any widget that supports it can use it. Rotation, zoom, inside
search, etc. may be managed too. Dynamic lists of records may be created, for
example for browse pages.

In Omeka S, this module may be used in conjunction with the [Universal Viewer],
a widget that can display books, images, maps, audio, movies, pdf, 3D, and
anything else as long as the appropriate extension is installed.

It can be improved by the [OpenLayersZoom], a module that convert big images
like maps and deep paintings, and any other images, into tiles in order to load
and zoom them instantly.

This [Omeka S] module is a rewrite of the [Universal Viewer plugin for Omeka] by
[BibLibre] with the same features as the original plugin, but separated into two
modules (the IIIF server and the widget Universal Viewer).

See a [demo] on the [Bibliothèque patrimoniale] of [Mines ParisTech], or you can
set the url "https://patrimoine.mines-paristech.fr/iiif/collection/7"
in the official [example server], because this is fully interoperable.


Installation
------------

Uncompress files and rename module folder "IiifServer".

Then install it like any other Omeka module.

Note: To keep old options from [Universal Viewer], upgrade it to version 3.4.3
before enabling of IiifServer. Else, simply set them in the config form.

If you need to display big images (bigger than 1 to 10 MB according to your
server), install the module [OpenLayersZoom], a module  that convert big images
like maps and deep paintings, and any other images, into tiles in order to load
and zoom them instantly.

Some options can be set:
- Options for the IIIF server can be changed in the helpers "IiifCollection.php",
  "IiifManifest.php" and "IiifInfo.php" of the module, and via the events.

See below the notes for more info.

* Processing of images

Images are transformed internally via the GD or the ImageMagick libraries. GD is
generally a little quicker, but ImageMagick manages many more formats. An option
allows to select the library to use according to your server and your documents.
So at least one of the php libraries ("php-gd" and "php-imagick" on Debian)
should be installed.

* Display of big images

If your images are big (more than 10 to 50 MB, according to your server and your
public), it’s highly recommended to tile them with a module such [OpenLayersZoom].
Then, tiles will be automatically displayed by Universal Viewer.

* Using externally supplied IIIF manifest and images

If you are harvesting data (via OAI-PMH, for instance) from another system where
images are hosted and exposed via IIIF, you can use a configurable metadata
field to supply the manifest to the Universal Viewer. In this case, no images
are hosted in the Omeka record, but one of the metadata fields has the URL of
the manifest hosted on another server.

For example, you could set the alternative manifest element to "Dublin Core:Has Format"
in the module configuration, and then put a URL like "https://example.com/iiif/HI-SK20161207-0009/manifest"
in the specified element of a record. The Universal Viewer included on that
record’s display page will use that manifest URL to retrieve images and metadata
for the viewer.

* Filtering data of manifests [TODO]

The module creates manifests with all the metadata of each record. The event
`uv.manifest` can be used to modify the exposed data of a manifest for items and
collections. For example, it is possible to modify the citation, to remove some
metadata or to change the thumbnail.


Usage
-----

All routes of the IIIF server are defined in `config/module.config.php`.
They follow the recommandations of the [iiif specifications].

To view the json-ld manifests created for each resources of Omeka S, simply try
these urls (replace :id by a true id):

- https://example.org/iiif/collection/:id for item sets;
- https://example.org/iiif/collection/:id,:id,:id,:id... for multiple resources;
- https://example.org/iiif/:id/manifest for items;
- https://example.org/iiif-img/:id/info.json for images files;
- https://example.org/iiif-img/:id/:region/:size/:rotation/:quality.:format for
  images, for example: https://example.org/iiif-img/1/full/full/270/gray.png;
- https://example.org/ixif-media/:id/info.json for other files;
- https://example.org/ixif-media/:id.:format for the files.

By default, ids are the internal ids of Omeka S, but it is recommended to use
your own single and permanent identifiers that don’t depend on an internal
pointer in a database. The term `Dublin Core Identifier` is designed for that
and a record can have multiple single identifiers. There are many possibilities:
named number like in a library or a museum, isbn for books, or random id like
with ark, noid, doi, etc. They can be displayed in the public url with the
modules [Ark] and/or [Clean Url].

If item sets are organized hierarchically with the plugin [Collection Tree], it
will be used to build manifests for item sets.


Notes
-----

- The plugin works fine for a standard usage, but the images server may be
  improved for requests made outside of the Universal Viewer when OpenLayersZoom
  is used. Without it, a configurable limit should be set (10 MB by default).

*Warning*

PHP should be installed with the extension "exif" in order to get the size of
images. This is the case for all major distributions and providers.


3D models
---------

The creation of manifests for 3D models is fully supported by the widget and
natively managed since the release 2.3 of [Universal Viewer] via the [threejs]
library.

* Possible requirement

The module [Archive Repertory] must be installed when the json files that
represent the 3D models use files that are identified by a basename and not a
full url. This is generally the case, because the model contains an external
image for texture. Like Omeka hashes filenames when it ingests files, the file
can’t be retrieved by the Universal Viewer.

This module is not required when there is no external images or when these
images are referenced in the json files with a full url.

* Example

  - Allow the extension `json` and the media type `application/json` in the
    global settings.
  - Install the module [Archive Repertory].
  - Download (or add via urls) the next three files from the official examples:
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thumb.jpg
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.jpg
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.json
  - Add a new item with these three files, in this order, and the following
  metadata:
    - Title: The Kiss
    - Date: 2015-11-27
    - Description: Soap stone statuette of Rodin’s The Kiss. Found at Snooper’s Paradise in Brighton UK.
    - Rights: 3D model produced by Sophie Dixon
    - LIcense (or Rights): by-nc-nd
  - Go to the public page of the item and watch it!

*Important*: When using [Archive Repertory] and when two files have the same
base name (here "thekiss.jpg" and "thekiss.json"), the image, that is referenced
inside the json, must be uploaded before the json.
Furthermore, the name of the thumbnail must be `thumb.jpg` and it is recommended
to upload it first.

Finally, note that 3D models are often heavy, so the user has to wait some
seconds that the browser loads all files and prepares them to be displayed.


TODO / Bugs
-----------

- When a item set contains non image items, the left panel with the index is
  displayed only when the first item contains an image.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database regularly so you can
roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

See documentation on the IIIF on its site.

Current maintainers of the plugin for Omeka 2 and the module for Omeka S:
* Daniel Berthereau (see [Daniel-KM])

First version of this module was built for [Mines ParisTech].


Copyright
---------

* Copyright Daniel Berthereau, 2015-2017
* Copyright BibLibre, 2016-2017


[IIIF Server]: https://github.com/Daniel-KM/Omeka-S-module-IIIF-Server
[Universal Viewer]: https://github.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Omeka S]: https://omeka.org/s
[Omeka]: https://omeka.org
[IIIF]: http://iiif.io
[IIPImage]: http://iipimage.sourceforge.net
[UniversalViewer]: https://github.com/UniversalViewer/universalviewer
[Universal Viewer plugin for Omeka]: https://github.com/Daniel-KM/UniversalViewer4Omeka
[demo]: https://patrimoine.mines-paristech.fr/collections/play/7
[Bibliothèque patrimoniale]: https://patrimoine.mines-paristech.fr
[BibLibre]: https://github.com/biblibre
[Mines ParisTech]: http://mines-paristech.fr
[example server]: http://universalviewer.io/examples/
[Upgrade to Omeka S]: https://github.com/Daniel-KM/UpgradeToOmekaS
[wiki]: https://github.com/UniversalViewer/universalviewer/wiki/Configuration
[iiif specifications]: http://iiif.io/api/
[OpenLayersZoom]: https://github.com/Daniel-KM/Omeka-S-module-OpenLayersZoom
[Collection Tree]: https://github.com/Daniel-KM/Omeka-S-module-CollectionTree
[threejs]: https://threejs.org
[Archive Repertory]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[module issues]: https://github.com/Daniel-KM/UniversalViewer4Omeka/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT licence]: https://github.com/UniversalViewer/universalviewer/blob/master/LICENSE.txt
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
