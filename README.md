# GoetasMultipartUploadBundle

[![Build Status](https://travis-ci.org/goetas/MultipartUploadBundle.png?branch=master)](https://travis-ci.org/goetas/MultipartUploadBundle)
[![Latest Stable Version](https://poser.pugx.org/goetas/multipart-upload-bundle/v/stable.png)](https://packagist.org/packages/goetas/multipart-upload-bundle)
[![Code Coverage](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/badges/coverage.png)](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/goetas/MultipartUploadBundle/?branch=master)

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
Content-Type: text/html
Content-Disposition: form-data; name="content"
Content-MD5: 314ca078416a9b27efbe338ac5a2f727

<a href="https://github.com/goetas/MultipartUploadBundle">HTML content</a>

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
Body will not be decoded automatically, you can decode it by yourself or use [FOSRestBundle](https://github.com/FriendsOfSymfony/FOSRestBundle) to handle it transparently 
```php
public function (Request $request)
{
    if ('application/json' == $request->headers->get('content-type')) {
        $data = json_decode($request->getContent(), true);
    }
}
```

### Form Fields
Parts with `form-data; name=` in `Content-Disposition` part's headers
will be treated like an regular uploaded file.
```php
$html = $request->request->get('content');
```

Can be used with Symfony's form builder
```php
$builder->add('content', TextAreaType::class);
```

### Uploaded Files
Parts with `form-data; name=` and `filename=` in `Content-Disposition` part's headers
will be treated like an regular uploaded file.
```php
$file = $request->files->get('image');
```

Can be used with Symfony's form builder
```php
$builder->add('image', FileType::class);
```

### Attachment Files
Parts with `attachment; filename=` in `Content-Disposition` part's headers
will be treated as an attachment file.
```php
$attachment = $request->attributes->get('attachments')[0];
```

### Related Parts
Parts without a `filename` will be treated as `RelatedPart` object.
```php
$part = $request->attributes->get('related-parts')[0];
```

- Get part's headers
```php
$headers = $part->getHeaders()->all();
```

- Get part's content
```php
$content = $part->getContent();
```

- Get part's content as resource
```php
$content = stream_get_contents($part->getContent(true));
```

- First part injected

By default, when a message is `multipart/*`, the first part will become the Symfony message content.
You can disable this by setting `first_part_as_default` to `false`.
```php
$content = $request->getContent(); // content of the first part, not the whole message
```

## Configurations

```yaml
goetas_multipart_upload:
  first_part_as_default: true
```

## Note 

The code in this project is provided under the 
[MIT](https://opensource.org/licenses/MIT) license. 
For professional support 
contact [goetas@gmail.com](mailto:goetas@gmail.com) 
or visit [https://www.goetas.com](https://www.goetas.com)

