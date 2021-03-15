IIIF Server (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

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

Of course, if you use only externally supplied IIIF manifests, you don't need
this module.

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

### Support

3D models are not supported by the IIIF standard, that manages only images,
audio and video files. Nevertheless, it is possible to create manifests that
follows the standard except the format of the file, like an extended version of
the standard. Only the widget [Universal Viewer] supports it natively since
version 2.3, via the [threejs] library. It is called "ixif".

The other viewers integrated via modules ([Diva] and [Mirador]) in Omeka don’t
support 3D.

The [threejs] library supports only some 3D formats. It can be its own format (a
json file with data, see below example with The Kiss), or [glTF]™.

The Graphics Language Transmission Format is a royalty-free publishing 3D format
designed for sharing and web, unlike many other 3D formats that are designed for
edition. It is supported by all professional 3D software and browsers, so it is
the recommended format.

The [threejs] library included in Universal Viewer doesn't include all its own
extensions that can be used to manage 3D models. In particular, the draco
compressor extension is not included. It's not so important, because the files
are usually zipped by the server if you configured it so. For example, add this
to your Apache config or in the file `.htaccess` at the root of Omeka:

```htaccess
<IfModule mod_deflate.c>
    <IfModule mod_filter.c>
        AddOutputFilterByType DEFLATE text/plain text/html text/css text/javascript application/javascript application/x-javascript application/ld+json application/json text/xml application/xml model/gltf-binary model/gltf+json
    </IfModule>
</IfModule>
```

### Deprecated support

***Important***: For IIIF Presentation v2, only the threejs format (see example
below) is really supported. The manifest can managed glTF files (as json or
binary), but Universal Viewer may or may not support them. For IIIF Presentation
v3, only glTF version 2 is supported, not the threejs format, neither deprecated
version 1 of glTF. You can convert these formats between them lossless.

If you need support for other 3D models formats, you need to compile the
Universal Viewer with the right extensions for the ThreeJs part.

### Cors

To share the `json` with other IIIF servers, the server may need to allow CORS
(see above).

### Media-types and security issue

By default, the 3D models are not allowed in the default global settings. The
module adds them during install, but if they are removed, you have to re-add
them:

- extension `.glb` and media type `model/gltf-binary` (single file glTF);
- extension `.gltf` and media type `model/gltf+json` (main file of a glTF media);
- extension `.json` and media type `model/vnd.threejs+json` (main file of a threejs media);
- extension `.json` and media type `application/json` (to manage the case where
  the files are authenticated as json, instead of a 3D model);
- extension `.bin` and media type `application/octet-stream` (binary file).

In some cases, if you use single file, the `.glb` files or the related `.bin`
are identified as `application/octet-stream`, that means that they are not
recognized. If you can't upload these files for security reasons (a bin file can
be a malware), you need to disable the file validation in the global settings.
Don't forget to reenable it after upload, because it is a security issue, or add
other security checks somewhere else, in particular during authentication or
with a server virus scanner (generally the [clam av] on Linux server).

### Size warning

It is important to warn visitors about the size of the 3D models: not everyone
has the fiber. Unlike big images that can be tiled statically or dynamically, no
3D model streaming format is supported for now and 3D models should be fully
loaded to be displayed, in particular when they are served as one binary file.

The examples below are 46 MB (Flying Helmet, [17 files], glTF) and 16 MB (The Kiss,
3 files, ThreeJs).

### Possible requirement

The module [Archive Repertory] must be installed when the json files that
represent the 3D models use files that are identified by a basename and not a
full url. This is generally the case, because the model contains external
images for textures and binary files for data. Like Omeka hashes filenames when
it ingests files, the files can’t be retrieved by the Universal Viewer.

This module is not required when there is no external images or when these
images are referenced in the json files with a full url.

***Important***: When using [Archive Repertory] and when two files have the same
base name (for example "thekiss.jpg" and "thekiss.json" below), the image, that
is referenced inside the json, must be uploaded before the json in order to keep
the original name (the json file will be renamed thekiss.1.json). The issue is
the same with gltf files: if there is a "my-object.gltf" and a "my-object.bin",
the file "my-object.bin" should be loaded before.

More generally, all files must have a different filename, excluding the
extension. So when there are more than one duplicate filename, you have to
rename all referenced filenames.

If you want another order, save the item one time with the order above, then
reorder medias and save the item.

The list of files should be flat. If the images or any other files are in a
subdirectory, for example "textures/example61_baseColor.png", you have two
possibilities:
- move all files to the root of the main file and update the main file with the
  new filenames "example61_baseColor.png",
- create the subdirectories ("textures" here) in the item directory, so "files/original/xxx/textures/",
  "files/large/xxx/textures/", "files/medium/xxx/textures/", and "files/square/xxx/textures/",
  move the files inside them, and update the value in the database, prepending
  "textures/" to the value in the column `storage id` of the table `media`.
This last point will be managed automatically in a future version of the module
[Archive Repertory].

### Thumbnail

Because 3D models are mainly data and textures, no thumbnail can be created with
standard tools (GD or Image Magick). In order to display an image in the pages
"documents browse" or "document view", a thumbnail can be added to the item.

It can be done as an asset attached to the item, that is the simpler way, or as
the first media of the item. In this second case, it should be the first media
and the original filename should be "thumb", "thumbnail", "screenshot",
"vignette", or "miniatura". The extension should be "png", "jpeg", "jpg", "gif",
or "webp". This name allows to make the distinction between the thumbnail and
the textures that belong to the item.

### Examples

#### Example with glTF

With glTF, a 3D media can be a single file that contains all data, binary data
and textures, or a main file that references many other files. Of course, it is
simpler to manage only one file, but it may be not the better choice in all the
cases, in particular when the media are big (not everyone has the fiber).

With a single file, no special configuration is needed: just load it as a
standard media or url.

With a multi-files media, you need to import the thumbnail first (or as asset),
then the binary file if the filename is the same than the main json file, then
the shaders and textures.

- Create a new item with the following metadata:
  - Title: Flight Helmet
  - License: Public domain
  - Add this file as thumbnail of the item: https://github.com/KhronosGroup/glTF-Sample-Models/blob/master/2.0/FlightHelmet/screenshot/screenshot.jpg
- Add all the files in the directory https://github.com/KhronosGroup/glTF-Sample-Models/tree/master/2.0/FlightHelmet/glTF,
  starting with the file "FlightHelmet.bin".
- Go to the public page of the item and watch it!

Of course, it is simpler to use a spreadsheet with modules [Bulk Import] or [CSV Import].

#### Example with a threejs file

This example requires to enable the extension and media types for json (see
above) and requires the module [Archive Repertory].

- Create a new item with the following metadata:
  - Title: The Kiss
  - Date: 2015-11-27
  - Description: Soap stone statuette of Rodin’s The Kiss. Found at Snooper’s Paradise in Brighton UK.
  - Rights: 3D model produced by Sophie Dixon
  - License (or Rights): by-nc-nd
- Add the next three files (as uploaded files or as url if they are served by a
  https server), taken from the official examples:
  - http://files.universalviewer.io/manifests/foundobjects/thekiss/thumb.jpg
  - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.jpg
  - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.json
- Go to the public page of the item and watch it!

Note that the three files above should be uploaded because the server "http://files.universalviewer.io"
has an issue with its certificate.


TODO / Bugs
-----------

- [ ] Implements ArrayObject to all classes to simplify events.
- [ ] When a item set contains non image items, the left panel with the index is displayed only when the first item contains an image (UV).
- [ ] Use the option "no storage" for url of a media for external server.
- [ ] Job to update data of [IIIF Image].
- [ ] Use only arrays, not standard objects.
- [ ] Manage url prefix.
- [ ] Manage all 3D formats (glTD binary, [see example] or https://iiif-3d-manifests.netlify.app).
- [ ] Store the json precise type (model/gltf+json and model/vnd.threejs?) in media during import or via a job (see module ExtractOcr for xml).
- [ ] Manage subdirectories with module Archive Repertory.

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

* Copyright Daniel Berthereau, 2015-2021 (see [Daniel-KM])
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
[Ark]: https://gitlab.com/Daniel-KM/omeka-s-module-Ark
[Clean Url]: https://gitlab.com/Daniel-KM/omeka-s-module-CleanUrl
[Collection Tree]: https://gitlab.com/Daniel-KM/Omeka-S-module-CollectionTree
[Deep Zoom]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Deep Zoom Image]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Zoomify]: http://www.zoomify.com/
[OpenLayers]: https://openlayers.org/
[threejs]: https://threejs.org
[17 files]: https://github.com/KhronosGroup/glTF-Sample-Models/tree/master/2.0/FlightHelmet
[clam av]: https://www.clamav.net
[glTF]: https://en.wikipedia.org/wiki/GlTF
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Bulk Import]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkImport
[CSV Import]: https://github.com/omeka-s-modules/CSVImport
[see example]: https://www.morphosource.org/manifests/1fbaa268-b02f-4b46-a249-cef46bcbe04c
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
