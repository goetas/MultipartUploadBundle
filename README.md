# MultipartUploadBundle
Symfony multipart/related content type handler

## Install
Run `composer require nestpick/multipart-upload-bundle`
Add bundle to symfony

## Make a Request
Sample request
```
Host: localhost
Content-Type: multipart-related; boundary=19D523FB

--19D523FB
Content-Type: application/json

{
    "content": "Some JSON content"
}
--19D523FB
Content-Type: image/png
Content-Disposition: form-data; name="image"; filename="image.jpg"
Content-MD5: 314ca078416a9b27efbe338ac5a2f727

... binary content...

--19D523FB
Content-Type: image/png
Content-Disposition: attachment; filename="image.jpg"
Content-MD5: 314ca078416a9b27efbe338ac5a2f727

... binary content...

--19D523FB
Content-Type: octet/stream
X-Custom-Header: header value

... binary content...

--19D523FB--
```

# Controller
Body will not bee decoded automatically, you can decode it by yourself or use [FOSRestBundle](https://github.com/FriendsOfSymfony/FOSRestBundle) to handle it transparently 
```php
public function (Request $request)
{
    if ('application/javascript' == $request->headers->get('content-type)) {
        $data = json_decode($request->getContent(), true);
    }
}
```

# Uploaded Files
Parts with `form-data; name=` and `filename=` in `Content-Disposition` part-header
will be treated like an regular uploaded file.
```php
$file = $request->files->get('image');
```

Can be used with Symfony's form builder
```php
$builder->add('image', FileType::class);
```

# Attachment Files
Parts with `attachment; filename=` in `Content-Disposition` part-header
will be treated as an attachment file.
```php
$attachment = $request->attributes->get('attachments')[0];
```

# Related Parts
Parts without a `filename` will be treated `UploadedFile` object.
```php
$part = $request->attributes->get('related-parts')[0];
```

Get part's headers
```php
$headers = $part->getHeaders()->all();
```

Get part's content
```php
$content = stream_get_contents($part->getContent());
```
