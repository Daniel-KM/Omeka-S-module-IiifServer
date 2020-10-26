IIIF Server (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better.__

[![Build Status](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer.svg?branch=master)](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer)

**IMPORTANT**: This readme is for the development version of IIIF Server. See
[stable version here]. This new version is working, but requires the module [Image Server].

[IIIF Server] is a module for [Omeka S] that integrates the [IIIF specifications]
to allow to process and share instantly images of any size and medias (pdf,
audio, video, 3D…) in the desired formats. It requires an image server, like the
module [Image Server].

The full specifications of the [International Image Interoperability Framework]
standard are supported (level 2), so any widget that supports it can use it.
Rotation, zoom, inside search, etc. may be managed too. Dynamic lists of records
may be created, for example for browse pages.

This [Omeka S] module is a rewrite of the [Universal Viewer plugin for Omeka] by
[BibLibre] with the same features as the original plugin, but separated into two
modules (the IIIF server and the widget Universal Viewer). It integrates the
tiler [Zoomify] that was used the plugin [OpenLayers Zoom] for [Omeka Classic]
and another tiler to support the [Deep Zoom Image] tile format.

The IIIF manifests can be displayed with many viewers, the integrated [OpenSeadragon],
the [Universal Viewer], the advanced [Mirador], or the ligher and themable [Diva],
or any other IIIF compatible viewer.

The search is provided by the module [Iiif Search] for common xml formats.


Installation
------------

### Module

Installation can be done:

* From the zip

Download the last release [`IiifServer.zip`] from the list of releases (the
master does not contain the dependencies), uncompress it in the `modules`
directory, and rename the module folder `IiifServer`.

* From the source and for development:

If the module was installed from the source, check if the name of the folder of
the module is `IiifServer`, go to the root of the module, and run:

```sh
composer install --no-dev
```

Then install it like any other Omeka module.

Note: To keep old options from [Universal Viewer], upgrade it to version 3.4.3
before enabling of IiifServer. Else, simply set them in the config form.

### Image server

An image server is required to display the images. It can be the module [Image Server],
or any other IIIF compliant image server. The image server is used to display
audio and video files too.

### PHP

PHP should be installed with the extension `exif` in order to get the size of
images. This is the case for all major distributions and providers. At least one
of the php extensions [`GD`] or [`Imagick`] are recommended. They are installed
by default in most servers. If not, the image server will use the command line
[ImageMagick] tool `convert` automatically.

### Web server and identifiers containing `/`

This point is related to Apache. If you are using nginx, you can skip it.

If you are using Ark as identifier for items and IIIF manifests (through the
modules [Clean Url] and optionally [Ark]), or any other identifier that contains
a forward slash `/`, you must modify the Apache system configuration, because by
default, [it doesn't allow the url encoded `/`] (`%2F`) inside urls for an old
security issue.

Indeed, an Ark identifier contains at least two forward slashes `/` (`ark:/12345/bNw3sx`)
and the IIIF specification requires to use [url encoded slashes], except for the
colon `:` (`ark:%2F12345%2FbNw3sx`). More precisely, this iiif requirement is
specified in the Image Api, not in the presentation Api, but the identifiers are
used in the same way.

```Apache
AllowEncodedSlashes NoDecode
```

If you cannot access to config of the server, two settings can fix it in the
config of the module :
- disable the advanced option "Use the identifiers from Clean Url";
- set the "Prefix to use for identifier".


Notes
-----

The module allows to manage collections (item set level/item list), and
manifests (item level). The info.json (media level) are managed by the image
server.

### Using externally supplied IIIF manifest

If you are harvesting data (via OAI-PMH, for instance) from another system where
images are hosted and exposed via IIIF, you can use a configurable metadata
field to supply the manifest to the viewer (Universal Viewer, Mirador or Diva).
In this case, no images are hosted in the Omeka record, but one of the metadata
fields has the URL of the manifest hosted on another server.

For example, you could set the alternative manifest element to "Dublin Core:Has Format"
in the module configuration, and then put a URL like "https://example.com/iiif/HI-SK20161207-0009/manifest"
in the specified element of a record. The viewer included on that record’s
display page will use that manifest URL to retrieve images and metadata for the
viewer.

### Using a third-party IIIF Image server via the Omeka media type "IIIF Image"

If your images are already managed by one or multiple dedicated third-party
image server (for instance one of the software listed in the [official list](https://github.com/IIIF/awesome-iiif/#image-servers)
of the IIIF community), you can use them directly in your items: create or
import them as media "[IIIF Image]". With this media type, the full json is
saved in metadata of the media itself.

If you don’t want to manage a dedicated image server, you can simply install the
module [Image Server].

### Customize data of manifests

The module creates manifests with all the metadata of each record. The event
`iiifserver.manifest` can be used to modify the exposed data of a manifest for
items, collections, collection lists (search results) and media (`info.json`).
So, it is possible, for example, to modify the citation, to remove or to add
some metadata or to change the thumbnail.

Note: with a collection list, the parameter `resource` is an array of resources.

All the combinations are possible: external manifest for items, iiif image for
external medias, a local standard media file with module Image Server.


Routes and urls
---------------

All routes of the IIIF server are defined in `config/module.config.php`.
They follow the recommandations of the [IIIF specifications].

To view the json-ld manifests created for each resources of Omeka S, simply try
these urls (replace :id by a true id):

- https://example.org/iiif/collection/:id for item sets;
- https://example.org/iiif/collection/:id,:id,:id,:id… for multiple resources (deprecated: use /set below);
- https://example.org/iiif/:id/manifest for items;
- https://example.org/iiif/set?id[]=:id,:id[]=:id,id[]=:id,id[]=:id…;
- https://example.org/iiif/set/:id,:id,:id,:id is supported too;

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


3D models
---------

The creation of manifests for 3D models is fully supported by the widget and
natively managed since the release 2.3 of [Universal Viewer] via the [threejs]
library. The other viewers integrated in Omeka doesn’t support 3D.

* Possible requirement

The module [Archive Repertory] must be installed when the json files that
represent the 3D models use files that are identified by a basename and not a
full url. This is generally the case, because the model contains an external
image for texture. Like Omeka hashes filenames when it ingests files, the file
can’t be retrieved by the Universal Viewer.

This module is not required when there is no external images or when these
images are referenced in the json files with a full url.

To share the `json` with other IIIF servers, the server may need to allow CORS
(see above).

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
    - License (or Rights): by-nc-nd
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

- [ ] Implements ArrayObject to all classes to simplify events.
- [ ] When a item set contains non image items, the left panel with the index is
  displayed only when the first item contains an image (UV).
- [ ] Use the option "no storage" for url of a media for external server.
- [ ] Job to update data of [IIIF Image].
- [ ] Use only arrays, not standard objects.

See module [Image Server].


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
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


Copyright
---------

* Copyright Daniel Berthereau, 2015-2020 (see [Daniel-KM])
* Copyright BibLibre, 2016-2017
* Copyright Régis Robineau, 2019 (see [regisrob])

First version of this plugin was built for the [Bibliothèque patrimoniale] of
[Mines ParisTech].


[IIIF Server]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer
[stable version here]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer/-/tree/3.5.16
[Omeka S]: https://omeka.org/s
[Image Server]: https://gitlab.com/Daniel-KM/Omeka-S-module-ImageServer
[International Image Interoperability Framework]: http://iiif.io
[IIIF specifications]: http://iiif.io/api/
[IIP Image]: http://iipimage.sourceforge.net
[OpenSeadragon]: https://openseadragon.github.io
[Universal Viewer plugin for Omeka]: https://gitlab.com/Daniel-KM/Omeka-plugin-UniversalViewer
[BibLibre]: https://github.com/biblibre
[OpenLayers Zoom]: https://gitlab.com/Daniel-KM/Omeka-S-module-OpenLayersZoom
[Universal Viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Mirador]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mirador
[Diva]: https://gitlab.com/Daniel-KM/Omeka-S-module-Diva
[Omeka Classic]: https://omeka.org
[Iiif Search]: https://github.com/bubdxm/Omeka-S-module-IiifSearch
[`GD`]: https://secure.php.net/manual/en/book.image.php
[`Imagick`]: https://php.net/manual/en/book.imagick.php
[ImageMagick]: https://www.imagemagick.org/
[`IiifServer.zip`]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer/-/releases
[it doesn't allow the url encoded `/`]: https://stackoverflow.com/questions/13834007/url-with-encoded-slashes-goes-to-404/13839424#13839424
[url encoded slashes]: https://iiif.io/api/image/3.0/#9-uri-encoding-and-decoding
[official list]: https://github.com/IIIF/awesome-iiif/#image-servers
[internal image server]: #image-server
[Universal Viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Ark]: https://github.com/BibLibre/omeka-s-module-Ark
[Clean Url]: https://github.com/BibLibre/omeka-s-module-CleanUrl
[Collection Tree]: https://gitlab.com/Daniel-KM/Omeka-S-module-CollectionTree
[Deep Zoom]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Deep Zoom Image]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Zoomify]: http://www.zoomify.com/
[OpenLayers]: https://openlayers.org/
[threejs]: https://threejs.org
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Deepzoom library]: https://gitlab.com/Daniel-KM/LibraryDeepzoom
[Zoomify library]: https://gitlab.com/Daniel-KM/LibraryZoomify
[Deepzoom]: https://github.com/jeremytubbs/deepzoom
[#6]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer/-/issues/6
[IIIF Image]: https://omeka.org/s/docs/user-manual/content/items/#media
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Bibliothèque patrimoniale]: https://patrimoine.mines-paristech.fr
[Mines ParisTech]: http://mines-paristech.fr
[regisrob]: https://github.com/regisrob
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
