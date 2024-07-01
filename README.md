IIIF Server (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[![Build Status](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer.svg?branch=master)](https://travis-ci.org/Daniel-KM/Omeka-S-module-IiifServer)

[IIIF Server] is a module for [Omeka S] that integrates the [IIIF specifications]
to allow to process and share instantly images of any size and medias (pdf,
audio, video, 3D…) in the desired formats. It can use any image server to display
images and media, like the module [Image Server], but you can use any other
external one, like [Cantaloupe] or [IIP Image].

The full specifications of the [International Image Interoperability Framework]
standard are supported (service 2 or 3, level 2), so any widget that supports it
can use it. Rotation, zoom, inside search, text overlay, etc. may be managed
too. Dynamic lists of records may be created, for example for browse pages.

The IIIF manifests can be displayed with many viewers, the integrated [OpenSeadragon],
the [Universal Viewer], the advanced [Mirador], or the lighter and themable [Diva],
or any other IIIF compatible viewer.

The search is provided by the module [Iiif Search] for common xml formats.


Installation
------------

### Module

See general end user documentation for [installing a module].

The module [Common] must be installed first.

The module uses external libraries, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [IiifServer.zip] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `IiifServer`, go to the root of the module, and run:

```sh
composer install --no-dev
```

Then install it like any other Omeka module.

Note: To keep old options from [Universal Viewer], upgrade it to version 3.4.3
before enabling of IiifServer. Else, simply set them in the config form.

### PHP

PHP should be installed with the extension `exif` in order to get the size of
images. This is the case for all major distributions and providers. At least one
of the php extensions [GD] or [Imagick] are recommended. They are installed by
default in most servers. If not, the image server will use the command line
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

### CORS (Cross-Origin Resource Sharing)

To be able to share manifests and contents with other IIIF servers, the server
should allow [CORS]. This feature can be enable in the config of the module, in
the config of the server or in the file `.htaccess`.

**Warning**: the cors headers should be set one time only. If it is set multiple
times, it will be disabled. This is the purpose of the option in the main config
of the module.

If you prefer to append cors via the config the server, disable the option in
the config first. On Apache 2.4, the module "headers" should be enabled:

```sh
a2enmod headers
systemctl restart apache2
```

Then, you have to add the following rules, adapted to your needs, to the file
`.htaccess` at the root of Omeka S or in the main config of the server:

```
# CORS access for some files.
<IfModule mod_headers.c>
    Header setIfEmpty Access-Control-Allow-Origin "*"
    Header setIfEmpty Access-Control-Allow-Headers "origin, x-requested-with, content-type"
    Header setIfEmpty Access-Control-Allow-Methods "GET, POST"
</IfModule>
```

It is recommended to use the main config of the server, for example  with the
directive `<Directory>`.

To fix Amazon cors issues, see the [aws documentation].

### Cache

When your documents are big (more than 100 to 1000 pages, depending on your
server, your network and your public), you may want to cache manifests in order
to delivrate them instantly. In that case, check the option in the config.

### Local access to iiif source

The iiif authentication api is not yet integrated. Anyway, to access iiif
resources when authenticated, the [fix #omeka/omeka-s/1714] can be patched or
the module [Guest] can be used.

### IIIF button in a theme

A resource page block is available in the theme settings. Else, you can add the
view helper in your theme:

```php
<?= $this->iiifManifestLink($item) ?>
```


Image server
------------

Except if all your manifests are external or if you have no media, you will need
a IIIF image server. It can be the module [Image Server] or an external server,
like [Cantaloupe] or [IIP Image]. The image server may be used to display audio
and video files too, if it supports them, else they will be served by Omeka.

### Module Image Server

The module [Image Server] serves original images and can create tile statically
or dynamically. This is the simplest way to get instant big images with Omeka.
The images are the original ones, stored by Omeka.

### External images servers

For other image servers, two configurations are possible.

#### Use the Omeka media type "IIIF Image"

The first possibility is to use an external server with a specific url path or a
subdomain. In that case, you have to create or import all your images as media
"[IIIF Image]". With this media type, the full json is saved in metadata of the
media itself and the original images remain separate from Omeka.

This is the simplest when your images are already managed by one or multiple
dedicated third-party image server (for instance one of the software listed in
the [official list](https://github.com/IIIF/awesome-iiif/#image-servers) of the
IIIF community).

Of course, it requires a second tool to manage your images, at least to copy
your directories of images in the image server (generally by ftp or a shared
disk space).

#### Use original files as storage for the image server

The second possibility is to use the original images, so the files inside the
directory "files/original". In that cases, the original files are managed by
Omeka with the media types "Upload" or "Url". This possibility requires that the
image server to be on the same server than Omeka, or at least that the image
server can access the original directory via the file system of via http, or any
another protocol.

Three params should be set:
- set the original directory as the base path in your image server (option "FilesystemSource.BasicLookupStrategy.path_prefix"
  for Cantaloupe);
- set the option "filename with extension" in the config of the module.
- add some rules in Apache config or in htaccess to redirect request to the
  image server. Normally, a regex starting with iiif/ and finishing with the
  supported file extensions is enough.

#### Note for Cantaloupe

In some cases or if not configured, Image Api v3 does not work and images are
not displayed, so keep Image Api v2 in that case.


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

### Config options for manifest

#### Incompatible data

Some extracted texts produce invalid unicode / utf-8 data, so it is recommended
to exclude the ocr data from the manifest. Anyway, it should not be included to
follow the iiif specifications: the metadata are only used to display basic data
about the document.

On new install, these properties are not included: `dcterms:tableOfContents`,
`bibo:content` and `extracttext:extracted_text`. You may have to add them to
avoid future issues.

#### Text overlay

For the overlay, only alto xml files are supported currently. They are
automatically included in the manifest.

To enable it, you may check config of the viewer, for example add plugin "overlay"
in the site setting of Mirador.

The alto xml files should be attached to the item as a media for now and it
should have the same source filename than the image (except extension).

A future version will allow to use a linked media or a uri via a property.

A full example of iiif manifest with search, autocomplete and overlay can be
found in the [Wellcome library](https://iiif.wellcomecollection.org/presentation/v2/b19956435).

#### Input format of the property for structures (table of contents)

IIIF allows to display [structures] of documents.

The default structure is the simple sequential list of iiif medias.

To build structures for a complex document with a table of contents, you can use
a specific property and fill a value with the needed json, or with a literal
value with the following format. Each row is a part of the structure:

```csv
{id}, {label}, {canvasIndexOrRangeId1}; {canvasIndexOrRangeId2}; …; {canvasIndexOrRangeIdN}
```

Example:

```csv
cover, Front Cover, 1
r2, Introduction, 2; 3; 4; 5
backCover, Back Cover, 6
```

A new format allows to include the view number in the third column:

```csv
cover, Front Cover, 1, 1
r2, Introduction, 2, 2; 3; 4; 5
backCover, Back Cover, 6, 6
```


The range id (first part of a row) is the name of the range, that will be used
to create the uri. To avoid collision with other indexes, it must not be a
numeric value. It should be a simple alphanumeric name, without space, diacritic
or any other special character, so it will be stable among all coding standards.
It must not contain characters `:/?&#%=<>;,`. Ideally, the url-encoded id should be
the same than the id. Anyway, this name is url-encoded in the final uri.

Furthermore, the range ids must be unique in all the item.

It can be skipped, so the line number will be used. In that case, keep the first
comma to indicate that there is no specific range name. For example if `r2` was
not provided above, the internal range id will be `r2` anyway, so `r` for range
and `2` for second line. Nevertheless, this possibility is not recommended
because the uri will change when a new line will be inserted.

The second part of the row is the label of the range, for example the title of
the chapter. If empty, it will be used for the structure, but not displayed in
the table of the viewer.

The last part of the row is the list of the top canvases or top ranges that the
current range contains, so generally a list of images and sub-sections.

In most of the cases, the canvas index is the media position. Only medias that
are used in the iiif are enumerated, not the specific medias, like pdf, xml, etc.
attached to the item, so take care of its value, that may be different from the
Omeka internal position in the list of attached medias to an item. Other indexes
will be managed as range indexes if they are in the list of the range ids (first
part of the row). If not, it will be a canvas alphanumeric name.

The first range id of the first line is  the root of the tree. There can be only
one root in iiif v3, but multiple structures. So if there are multiple roots
(see below), a main range is added with all the roots as main branches.

If you use a xml value with module [DataType Rdf], the structure above will be
composed of canvases (element `c` here):

```xml
<c id="cover" label="Front Cover" ranges="1"/>
<c id="r2" label="Introduction" ranges="2; 3; 4; 5"/>
<c id="backcover" label="Back Cover" ranges="6"/>
```

The ranges can be omitted anyway, since the xml structure itself provide it (see
nested xml below).

So it is possible to build complex hierarchical table of contents from this
literal value, even with such an incomplete example, that is automatically
completed with pages that are not sections:

```csv
toc, Table of Contents, cover; intro; r1; r2; backcover
    cover, Front cover, cover
    intro, Introduction, 2-5
    r1, First chapter, 6; r1-1; r1-2; 12
        r1-1, First section, r1-1-1; r1-1-2; illustration1; illus2
            r1-1-1, First sub-section, 8-9
            r1-1-2, Second sub-section, 9-10
    r2, Second chapter, 13
    backcover, Back cover, "backcover"
illustration1, First illustration non paginated, illus1
illustration3, Third illustration non paginated, illus3
```

equivalent of this nested xml:

```xml
<c id="toc" label="Table of Contents">
    <c id="cover" label="Front cover"/>
    <c id="intro" label="Introduction" range="2-5"/>
    <c id="r1" label="First chapter" range="6; r1-1; r1-2; 12">
        <c id="r1-1" label="First section" range="r1-1-1; r1-1-2; illustration1; illus2">
            <c id="r1-1-1" label="First sub-section" range="8-9"/>
            <c id="r1-1-2" label="Second sub-section" range="9-10"/>
            <c id="illustration1" label="First illustration non paginated" range="illus1"/>
        </c>
    </c>
    <c id="r2" label="Second chapter" range="13"/>
    <c id="backcover" label="Back cover"/>
</c>
<c id="illustration3" label="Third illustration non paginated" range="illus3"/>
```

equivalent of this flat indented xml (not recommended and deprecated):

```xml
<c id="toc" label="Table of Contents" range="cover; intro; r1; r2; backcover"/>
    <c id="cover" label="Front cover" range="cover"/>
    <c id="intro" label="Introduction" range="2-5"/>
    <c id="r1" label="First chapter" range="6; r1-1; r1-2; 12"/>
        <c id="r1-1" label="First section" range="r1-1-1; r1-1-2; illustration1; illus2"/>
            <c id="r1-1-1" label="First sub-section" range="8-9"/>
            <c id="r1-1-2" label="Second sub-section" range="9-10"/>
    <c id="r2" label="Second chapter" range="13"/>
    <c id="backcover" label="Back cover" range="backcover"/>
<c id="illustration1" label="First illustration non paginated" range="illus1"/>
<c id="illustration3" label="Third illustration non paginated" range="illus3"/>
```

to this json output (iiif v2):

```json
[
  {
    "@id": "https://example.org/iiif/book1/range/toc",
    "@type": "sc:Range",
    "label": "Table of Contents",
    "ranges": [
      {
        "@id": "https://example.org/iiif/book1/range/cover",
        "@type": "sc:Range",
        "label": "Front cover",
        "ranges": [
          {
            "@id": "https://example.org/iiif/book1/range/cover",
            "@type": "sc:Range",
            "label": "Front cover"
          }
        ]
      },
      {
        "@id": "https://example.org/iiif/book1/range/intro",
        "@type": "sc:Range",
        "label": "Introduction",
        "canvases": [
          "https://example.org/iiif/book1/canvas/p2",
          "https://example.org/iiif/book1/canvas/p3",
          "https://example.org/iiif/book1/canvas/p4",
          "https://example.org/iiif/book1/canvas/p5"
        ]
      },
      {
        "@id": "https://example.org/iiif/book1/range/r1",
        "@type": "sc:Range",
        "label": "First chapter",
        "members": [
          {
            "@id": "https://example.org/iiif/book1/canvas/p6",
            "@type": "sc:Canvas",
            "label": "[6]"
          },
          {
            "@id": "https://example.org/iiif/book1/range/r1-1",
            "@type": "sc:Range",
            "label": "First section",
            "members": [
              {
                "@id": "https://example.org/iiif/book1/range/r1-1-1",
                "@type": "sc:Range",
                "label": "First sub-section",
                "canvases": [
                  "https://example.org/iiif/book1/canvas/p8",
                  "https://example.org/iiif/book1/canvas/p9"
                ]
              },
              {
                "@id": "https://example.org/iiif/book1/range/r1-1-2",
                "@type": "sc:Range",
                "label": "Second sub-section",
                "canvases": [
                  "https://example.org/iiif/book1/canvas/p9",
                  "https://example.org/iiif/book1/canvas/p10"
                ]
              },
              {
                "@id": "https://example.org/iiif/book1/range/illustration1",
                "@type": "sc:Range",
                "label": "First illustration non paginated",
                "canvases": [
                  "https://example.org/iiif/book1/canvas/illus1"
                ]
              },
              {
                "@id": "https://example.org/iiif/book1/canvas/illus2",
                "@type": "sc:Canvas",
                "label": "illus2"
              }
            ]
          },
          {
            "@id": "https://example.org/iiif/book1/canvas/r1-2",
            "@type": "sc:Canvas",
            "label": "r1-2"
          },
          {
            "@id": "https://example.org/iiif/book1/canvas/p12",
            "@type": "sc:Canvas",
            "label": "[12]"
          }
        ]
      },
      {
        "@id": "https://example.org/iiif/book1/range/r2",
        "@type": "sc:Range",
        "label": "Second chapter",
        "canvases": [
          "https://example.org/iiif/book1/canvas/p13"
        ]
      },
      {
        "@id": "https://example.org/iiif/book1/range/backcover",
        "@type": "sc:Range",
        "label": "Back cover",
        "canvases": [
          "https://example.org/iiif/book1/canvas/backcover"
        ]
      }
    ]
  },
  {
    "@id": "https://example.org/iiif/book1/range/illustration3",
    "@type": "sc:Range",
    "label": "Third illustration non paginated",
    "canvases": [
      "https://example.org/iiif/book1/canvas/illus3"
    ]
  }
]
```

or to this json output (iiif v3), a little more verbose:

```json
[
  {
    "id": "https://example.org/iiif/book1/range/rstructure1",
    "type": "Range",
    "label": { "none": [ "Content" ] },
    "items": [
      {
        "id": "https://example.org/iiif/book1/range/toc",
        "type": "Range",
        "label": { "none": [ "Table of Contents" ] },
        "items": [
          {
            "id": "https://example.org/iiif/book1/range/cover",
            "type": "Range",
            "label": { "none": [ "Front cover" ] },
            "items": [
              { "id": "https://example.org/iiif/book1/canvas/cover", "type": "Canvas" }
            ]
          },
          {
            "id": "https://example.org/iiif/book1/range/intro",
            "type": "Range",
            "label": { "none": [ "Introduction" ] },
            "items": [
              { "id": "https://example.org/iiif/book1/canvas/p2", "type": "Canvas" },
              { "id": "https://example.org/iiif/book1/canvas/p3", "type": "Canvas" },
              { "id": "https://example.org/iiif/book1/canvas/p4", "type": "Canvas" },
              { "id": "https://example.org/iiif/book1/canvas/p5", "type": "Canvas" }
            ]
          },
          {
            "id": "https://example.org/iiif/book1/range/r1",
            "type": "Range",
            "label": { "none": [ "First chapter" ] },
            "items": [
              { "id": "https://example.org/iiif/book1/canvas/p6", "type": "Canvas" },
              {
                "id": "https://example.org/iiif/book1/range/r1-1",
                "type": "Range",
                "label": { "none": [ "First section" ] },
                "items": [
                  {
                    "id": "https://example.org/iiif/book1/range/r1-1-1",
                    "type": "Range",
                    "label": { "none": [ "First sub-section" ] },
                    "items": [
                      { "id": "https://example.org/iiif/book1/canvas/p8", "type": "Canvas" },
                      { "id": "https://example.org/iiif/book1/canvas/p9", "type": "Canvas" }
                    ]
                  },
                  {
                    "id": "https://example.org/iiif/book1/range/r1-1-2",
                    "type": "Range",
                    "label": { "none": [ "Second sub-section" ] },
                    "items": [
                      { "id": "https://example.org/iiif/book1/canvas/p9", "type": "Canvas" },
                      { "id": "https://example.org/iiif/book1/canvas/p10", "type": "Canvas" }
                    ]
                  },
                  {
                    "id": "https://example.org/iiif/book1/range/illustration1",
                    "type": "Range",
                    "label": { "none": [ "First illustration non paginated" ] },
                    "items": [
                      { "id": "https://example.org/iiif/book1/canvas/illus1", "type": "Canvas" }
                    ]
                  },
                  { "id": "https://example.org/iiif/book1/canvas/illus2", "type": "Canvas" }
                ]
              },
              { "id": "https://example.org/iiif/book1/canvas/r1-2", "type": "Canvas" },
              { "id": "https://example.org/iiif/book1/canvas/p12", "type": "Canvas" }
            ]
          },
          {
            "id": "https://example.org/iiif/book1/range/r2",
            "type": "Range",
            "label": { "none": [ "Second chapter" ] },
            "items": [
              { "id": "https://example.org/iiif/book1/canvas/p13", "type": "Canvas" }
            ]
          },
          {
            "id": "https://example.org/iiif/book1/range/backcover",
            "type": "Range",
            "label": { "none": [ "Back cover" ] },
            "items": [
              { "id": "https://example.org/iiif/book1/canvas/backcover", "type": "Canvas" }
            ]
          }
        ]
      },
      {
        "id": "https://example.org/iiif/book1/range/illustration3",
        "type": "Range",
        "label": { "none": [ "Third illustration non paginated" ] },
        "items": [
          { "id": "https://example.org/iiif/book1/canvas/illus3", "type": "Canvas" }
        ]
      }
    ]
  }
]
```

Notes to understand the conversion and to fix issues from the literal data:

- The named canvases may not be supported, so you may have to avoid them.
- The indentation is not required, but it simplifies literal visualisation.
- The lines must be ordered as a table of contents or as an index, so the parser
  can understand the structure.
- Each line will be a range, even if it refers to a single canvas, because only
  ranges are displayed to the user. So the cover, the second chapter and the
  illustration are ranges with a single item that is a canvas.
- There are some missing pages (not scanned or lost).
- The names have some meanings ("r1-1-1"), but it's not needed.
- The cover refers to itself, so it's a named canvas within a range. This is not
  possible for iiif v2, so wrap it with double quotes.
- The backcover has a canvas wrapped by double quotes to force it to be a canvas.
- The sub-sections share one page (9).
- The index "r1-2" in the indexes of the first chapter is missing, so it is
  added as a named canvas.
- The index "12" is a canvas, so it is added to the list, and, as a canvas,
  won't be displayed in the index of the viewer.
- The "illustration1" has a named canvas, since "illus1" is not a range.
- The index "illustration1" of the first section is a range, but it is missing
  from the ordered list below the first section. So it is added directly at the
  right place and removed from the end.
- The illustration3 is not used as a range, so it is a root, so there is more
  than one root, so a range is added to wrap all the structure.

Take care of nested structures: items must not belong to themselves, else they
will be managed as canvases.

Of course, if the literal structure is well formed, you don't have to consider
these fixes.

Otherwise, in IIIF v3, multiple structures are appended when there are multiple
values in the item. For example, in a newspaper, it's possible to have a
structure by page like a common table of contents, and a structure by articles.
Indeed, an article can be written on multiple and non-sequential pages, and a
page can contain many articles, illustrations, ads, etc. When there are multiple
structures, the names of the ranges should have the same meaning between each
structure (for example the index "cover" should be the front cover in all
structures), because their uri will be the same.

More info about structures for [IIIF presentation 2.1] and [IIIF presentation 3.0].

### Customize data of manifests

The module creates manifests with all the metadata of each record. A lot of
config options allows to define each part of them. If it's not enough, the event
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
- https://example.org/iiif/:id/manifest for items;
- https://example.org/iiif/set?id[]=:id,:id[]=:id,id[]=:id,id[]=:id…;
- https://example.org/iiif/set/:id,:id,:id,:id is supported too;

- https://example.org/iiif/:id/info.json for images files;
- https://example.org/iiif/:id/:region/:size/:rotation/:quality.:format for
  images, for example: https://example.org/iiif/1/full/full/270/gray.png;
- https://example.org/iiif/:id/info.json for other files;
- https://example.org/iiif/:id.:format for the files.

By default, ids are the internal ids of Omeka S, but it is recommended to use
your own single and permanent identifiers that don’t depend on an internal
pointer in a database. The term `Dublin Core Identifier` is designed for that
and a record can have multiple single identifiers. There are many possibilities:
named number like in a library or a museum, isbn for books, or random id like
with ark, noid, doi, etc. They can be displayed in the public url with the
modules [Ark] and/or [Clean Url].


3D models
---------

3D models are not supported by the IIIF standard, that manages only images (IIIF v2),
and audio and video files (IIIF v3). Nevertheless, it is possible to create
manifests that follows the standard except the format of the file, like an
extended version of the standard. Only the widget [Universal Viewer] supports it
natively since version 2.3, via the [three.js] library. It is called "ixif".

The other viewers integrated via modules ([Diva] and [Mirador]) in Omeka don’t
support 3D.

For more info about support of 3D models and possible other requirements, see
the module [Three JS Model viewer].


TODO / Bugs
-----------

- [x] Implements ArrayObject to all classes to simplify events.
- [ ] Implements ArrayObject to all classes to simplify events for Iiif v2.
- [x] Use only arrays, not standard objects.
- [ ] Type of manifest: Use a list of classes or templates to determine the 3D files.
- [x] Type of manifest: Include pdf as rendering.
- [ ] Structure: Clarify names of canvases and referenced canvas in the table of contents and list of items.
- [ ] Structure: Implements recursive ranges in structures for IIIF v2.
- [ ] Structure: Normalize the format of the structure: csv? ini? yaml? xml? Provide an automatic upgrade too.
- [ ] Structure: Convert structure v3 to v2 and vice-versa.
- [x] Structure: Fully support alphanumeric name for canvas id.
- [ ] Structure: Support translation of structure (use the language of the value?).
- [ ] Structure: Full support of named canvases.
- [ ] Use the option "no storage" for url of a media for external server.
- [ ] Manage url prefix.
- [ ] When a item set contains non image items, the left panel with the index is displayed only when the first item contains an image (UV).
- [ ] Job to update data of [IIIF Image].
- [x] Create a way to cache big iiif manifests (useless for image info.json).
- [ ] Always return a thumbnail in iiif v3.
- [ ] Include thumbnails in canvas to avoid fetching info.json (so cache whole manifest).
- [x] Check if multiple roots is working for structures in iiif v3. (yes, as multiple structures).
- [x] Store dimensions on item/media save.
- [ ] Create a plugin MediaData that will merge MediaDimension, ImageSize, and allows to get media type.
- [ ] Clarify option for home page with "default site", that may not be a site of the item.
- [ ] Podcast on iiif v2.

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

* Copyright Daniel Berthereau, 2015-2024 (see [Daniel-KM])
* Copyright BibLibre, 2016-2017
* Copyright Régis Robineau, 2019 (see [regisrob])
* Copyright Satoru Nakamura, 2021 (see [nakamura196])

First version of this plugin was built for the [Bibliothèque patrimoniale] of
[Mines ParisTech]. This [Omeka S] module is a rewrite of the [Universal Viewer plugin for Omeka]
by [BibLibre] with the same features. Next, it was , but separated into three
modules: the IIIF server, the [Image Server] and the viewer [Universal Viewer].
This viewer integrates the tiler [Zoomify] that was used the plugin [OpenLayers Zoom]
for [Omeka Classic] and another tiler to support the [Deep Zoom Image] tile
format.


[IIIF Server]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer
[Omeka S]: https://omeka.org/s
[Image Server]: https://gitlab.com/Daniel-KM/Omeka-S-module-ImageServer
[International Image Interoperability Framework]: http://iiif.io
[IIIF specifications]: http://iiif.io/api/
[Cantaloupe]: https://cantaloupe-project.github.io
[IIP Image]: http://iipimage.sourceforge.net
[OpenSeadragon]: https://openseadragon.github.io
[Universal Viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[Mirador]: https://gitlab.com/Daniel-KM/Omeka-S-module-Mirador
[Diva]: https://gitlab.com/Daniel-KM/Omeka-S-module-Diva
[Three JS Model viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-ModelViewer
[Omeka Classic]: https://omeka.org
[Iiif Search]: https://github.com/bubdxm/Omeka-S-module-IiifSearch
[GD]: https://secure.php.net/manual/en/book.image.php
[Imagick]: https://php.net/manual/en/book.imagick.php
[ImageMagick]: https://www.imagemagick.org/
[installing a module]: https://omeka.org/s/docs/user-manual/modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[IiifServer.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer/-/releases
[structures]: https://iiif.io/api/presentation/3.0/#54-range
[DataType Rdf]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeRdf
[fix #omeka/omeka-s/1714]: https://github.com/omeka/omeka-s/pull/1714
[Guest]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest
[it doesn't allow the url encoded `/`]: https://stackoverflow.com/questions/13834007/url-with-encoded-slashes-goes-to-404/13839424#13839424
[url encoded slashes]: https://iiif.io/api/image/3.0/#9-uri-encoding-and-decoding
[CORS]: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
[aws documentation]: https://docs.aws.amazon.com/AmazonS3/latest/userguide/cors.html
[official list]: https://github.com/IIIF/awesome-iiif/#image-servers
[internal image server]: #image-server
[Universal Viewer]: https://gitlab.com/Daniel-KM/Omeka-S-module-UniversalViewer
[IIIF presentation 2.1]: https://iiif.io/api/presentation/2.1/#range
[IIIF presentation 3.0]: https://iiif.io/api/presentation/3.0/#54-range
[Ark]: https://gitlab.com/Daniel-KM/omeka-s-module-Ark
[Clean Url]: https://gitlab.com/Daniel-KM/omeka-s-module-CleanUrl
[Collection Tree]: https://gitlab.com/Daniel-KM/Omeka-S-module-CollectionTree
[Deep Zoom]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Deep Zoom Image]: https://msdn.microsoft.com/en-us/library/cc645022(v=vs.95).aspx
[Zoomify]: http://www.zoomify.com/
[OpenLayers]: https://openlayers.org/
[three.js]: https://threejs.org
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
[Universal Viewer plugin for Omeka]: https://gitlab.com/Daniel-KM/Omeka-plugin-UniversalViewer
[BibLibre]: https://github.com/biblibre
[OpenLayers Zoom]: https://gitlab.com/Daniel-KM/Omeka-S-module-OpenLayersZoom
[nakamura196]: https://github.com/nakamura196
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
