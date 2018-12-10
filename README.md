# GoetasMultipartUploadBundle

[![Build Status](https://travis-ci.org/goetas/MultipartUploadBundle.png?branch=master)](https://travis-ci.org/goetas/MultipartUploadBundle)
[![Latest Stable Version](https://poser.pugx.org/goetas/multipart-upload-bundle/v/stable.png)](https://packagist.org/packages/goetas/multipart-upload-bundle)
[![Code Coverage](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/badges/coverage.png)](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/)

Symfony `multipart/related` content type handler.

This bundle implements a subset of the [https://www.w3.org/Protocols/rfc1341/7_2_Multipart.html](https://www.w3.org/Protocols/rfc1341/7_2_Multipart.html) specifications 
and allows you to deal with `Content-Type: multipart-related;` requests with Symfony.

## Install
Run `composer require goetas/multipart-upload-bundle`

Add bundle to symfony (if not using symfony/flex)

## Request format

A `multipart-related` request could look like this:

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
## Usage
### Controller
Body will not bee decoded automatically, you can decode it by yourself or use [FOSRestBundle](https://github.com/FriendsOfSymfony/FOSRestBundle) to handle it transparently 
```php
public function (Request $request)
{
    if ('application/json' == $request->headers->get('content-type')) {
        $data = json_decode($request->getContent(), true);
    }
}
```

### Uploaded Files
Parts with `form-data; name=` and `filename=` in `Content-Disposition` part-header
will be treated like an regular uploaded file.
```php
$file = $request->files->get('image');
```

Can be used with Symfony's form builder
```php
$builder->add('image', FileType::class);
```

### Attachment Files
Parts with `attachment; filename=` in `Content-Disposition` part-header
will be treated as an attachment file.
```php
$attachment = $request->attributes->get('attachments')[0];
```

### Related Parts
Parts without a `filename` will be treated as `RelatedPart` object.
```php
$part = $request->attributes->get('related-parts')[0];
```

Get part's headers
```php
$headers = $part->getHeaders()->all();
```

Get part's content
```php
$content = $part->getContent();
```

Get part's content as resource
```php
$content = stream_get_contents($part->getContent(true));
```
