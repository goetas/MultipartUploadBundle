<?php

namespace Nestpick\MultipartUploadBundle;

use Symfony\Component\HttpFoundation\HeaderBag;

class RelatedPart
{
    /**
     * @var HeaderBag
     */
    private $headers;

    /**
     * @var resource
     */
    private $content;

    /**
     * @param array|string[] $headers
     * @param string $content
     */
    public function __construct(string $content, array $headers = [])
    {
        $this->headers = new HeaderBag($headers);

        $this->content = fopen('php://temp','rb+');
        fwrite($this->content, $content);
        rewind($this->content);
    }

    /**
     * @return HeaderBag
     */
    public function getHeaders(): HeaderBag
    {
        return $this->headers;
    }

    /**
     * @return resource
     */
    public function getContent()
    {
        return $this->content;
    }
}
