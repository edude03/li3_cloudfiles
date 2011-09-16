li3\_cloudfiles is a Lithium plugin providing a data source that interfaces with Rackspace Cloud Files.

# Installation

        cd /path/to/app/libraries
        git clone git://github.com/w3p/li3_cloudfiles

# Configuration

## Library

app/config/bootstrap/libraries.php:

        Libraries::add('li3_cloudfiles');

## Connection

app/config/bootstrap/connections.php:

        Connections::add('cloudfiles', array(
            'type'     => 'http',
            'scheme'   => 'https',
            'port'     => 443,
            'cache'    => 'default',
            'adapter'  => 'CloudFiles',
            'login'    => 'your_username_here',
            'host'     => 'auth.api.rackspacecloud.com',
            'password' => 'your_api_key_here'
        ));

Note: If you are a Rackspace UK customer, _host_ should be set to *lon.auth.api.rackspacecloud.com*.

*Why caching is important here:*

The Cloud Files API requires that you send an authentication request to obtain a temporary token
to be used in subsequent requests. li3\_cloudfiles will perform the first request and then store
the authentication credentials in the specified cache, respecting the expiration date set by
the Cache-Control response header returned by the Cloud Files API. If you choose not to use
caching at all, an auth request will be sent before each and every operation.

# Models

li3_cloudfiles already provides three models that you can use in your application, `Files`, `StorageContainers`
and `CdnContainers`. You can extend them or use them directly.

At first you may think (or was it just me?) that a "CDN-enabled container" is just a "regular container" with a property 
"CDN enabled" set to *true*. However, Cloud Files differentiates storage from CDN. Files are uploaded to
storage containers and *can* be served by a CDN container created with the same name.

Quoting the official API docs:

    "Containers tracked in the CDN management service are completely separate and distinct from
    the containers defined in the storage service. It is possible for a container to be CDN-enabled
    even if it doesn't exist in the storage system. Users may want the ability to pre-generate
    CDN URLs before actually uploading content and this separation gives them that ability."

    "However, for the content to be served from the CDN, the container names MUST match in both
    the CDN management service and the storage service. For example, you could CDN-enable a container
    called images and be assigned the CDN URL, but you also need to create a container called
    images in the storage service."

## Files

In this example we'll use li3_cloudfiles\Models\Files to upload a thumbnail:

        namespace app\controllers;

        use li3_cloudfiles\models\Files;

        class FilesController extends \lithium\action\Controller {

            public function create() {

                $thumbnail = Files::create(array(
                    'name'      => 'myfile.png',
                    'container' => 'thumbnails',
                    'content'   => file_get_contents('myfile.png'),
                    'type'      => 'image/png'
                ));
                $thumbnail->save();
            }
        }

This is how we can retrieve it later (remember to *always* pass the container name):

        $file = Files::one('myfile.png', array('conditions' => array('container' => 'thumbnails')));
        header("Content-type: {$file->type}");
        echo $file->content;

Now let's retrieve the list of files stored in the `thumbnails` container. Since we want
a list of *files*, we'll use the `Files` model instead of the `Container`.

In the `Files` model there's a special finder that allows you to perform such query:

        foreach (Files::in('thumbnails') as $thumbnail) {
            echo $thumbnail->name;
        }

In the above case the file contents won't be retrieved because they require one additional request
per file. However, if you don't want to do it yourself by iterating over the list of files, you
can use the `cascade` options:

        foreach (Files::in('thumbnails', array('cascade' => true)) as $thumbnail) {
            // now $thumbnail->content has the file contents;
        }

Here's the list of properties:

* name

    File name or full filesystem path.

* container

    Name of the container where the file will be uploaded to.

* content

    Raw file contents.

* type

    File content type.

* (optional) meta

    Associative array containing file metadata. (eg. 'author' => 'Pedro Padron')

And here's a list of all available query parameters:

* limit

    Works just like you expect it to:

            $files = Files::in('thumbnails', array('limit' => 5));
            count($files); // <= 5

* marker

    Starting point from which the files should be retrieved.

    When listing the contents of a container, there's a limit of 10.000 results for
    each request. To retrieve the rest of the files, you have to perform another request
    passing as a marker the last file returned in the previous request. As of the current
    release of this library, you have to manually request for more files.

            // $total = 20000 files
            $total = StorageContainer::one('thumbnails')->count;

            // $files = DocumentArray with 10000 Files
            $files = Files::in('thumbnail');
            $last  = $files->end();

            // $moreFiles = 10000 remaining Files after $last->name
            $moreFiles = Files::in('thumbnail', array('marker' => $last->name));

* prefix

    Only files starting with `prefix` will be returned.

    Considering the following list of files in the _music_ container:

        dead_kennedys-holiday_in_cambodia.mp3
        dead_kennedys-police_truck.mp3
        dezerter-spitaj_milicjanta.mp3
        the_clash-guns_of_brixton.mp3
        toy_dolls-james_bond_lives_down_our_street.mp3

    This will return only Dead Kennedys songs:

            Files::in('music', array('prefix' => 'dead_kennedys'));

    Specifying a `prefix` is most useful when combined with `delimiter`, allowing you to
    traverse into directories.

        foo/thumbnails/small.png
        foo/thumbnails/medium.png
        foo/photo1.png
        foo/photo2.png
        bar/file.txt
        bar/whatever.doc

    Let's retrieve a list of files with the prefix foo:

            Files::in('container', array('prefix' => 'foo'));

    This will return:
        
        foo/thumbnails/small.png
        foo/thumbnails/medium.png
        foo/photo1.png
        foo/photo2.png

* path

    Used to retrieve files under the specified `path`. This another way to simulate a hierarchical
    folder structure in Cloud Files. When creating a file you can use it's name as the
    full filesystem path, such as:

        photos/animals/dogs/poodle.jpg
        photos/animals/dogs/terrier.jpg
        photos/animals/cats/persian.jpg
        photos/animals/cats/siamese.jpg
        photos/plants/fern.jpg
        photos/plants/rose.jpg
        photos/me.jpg

    This is how the contents from photos/animals can be retrieved:

            $files = Files::in('container', array('path' => 'photos/animals'));

    The above code will return the virtual subdirectories under photos/animals:

        photos/animals/dogs
        photos/animals/dogs

    These virtual directories can be distinguished from regular files based on its
    `type` property, which is set to `application/directory`.

    This is compatible with the "old way" of setting up a hierarchical structure, where
    you had to upload a dummy file named after the desired "directory" with its content type
    set to application/directory. Now the API will consider the backslash "/" 


* delimiter

    The `delimiter` is the path separator for a hierarchical structure. You can specify any
    single character as a `delimiter`, although the most common (and saner) choice is the backslash /.

    Using a delimiter is most useful when combined with `prefix`.
    
    Consider the following files:

        foo/thumbnails/small.png
        foo/thumbnails/medium.png
        foo/photo1.png
        foo/photo2.png

    With a `delimiter` and a `prefix` you can retrieve a list of files and directories *in the same nesting level*:

            Files::in('container', array('delimiter' => '/', 'prefix' => 'foo'));

    The above code will return the following:

        foo/thumbnails
        foo/photo1.png
        foo/photo2.png

    As you can see, the files under _thumbnails_ were not returned.

If you are wondering whether you should use `path`, `prefix` or `delimiter` to work with your files/folders,
just go with `prefix` + `delimiter` and forget about `path`. Trust me.

## StorageContainers


## CdnContainers