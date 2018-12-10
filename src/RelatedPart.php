<?php

namespace Goetas\MultipartUploadBundle;

use Symfony\Component\HttpFoundation\HeaderBag;

class RelatedPart
{
    /**
     * @var HeaderBag
     */
    private $headers;

    /**
     * @var string
     */
    private $content;

    /**
     * @param array|string[] $headers
     * @param string         $content
     */
    public function __construct(string $content, array $headers = [])
    {
        $this->headers = new HeaderBag($headers);
        $this->content = $content;
    }

    /**
     * @return HeaderBag
     */
    public function getHeaders(): HeaderBag
    {
        return $this->headers;
    }

    /**
     * @param bool $asResource
     *
     * @return string|resource
     */
    public function getContent(bool $asResource = false)
    {
        if ($asResource) {
            $resource = fopen('php://memory', 'rb+');
            fwrite($resource, $this->content);
            rewind($resource);

            return $resource;
        }

        return $this->content;
    }
}
