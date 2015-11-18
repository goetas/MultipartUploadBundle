<?php

namespace Nestpick\MultipartUploadBundle\EventListener;

use Nestpick\MultipartUploadBundle\Exception\UploadProcessorException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class MultipartRequestListener
{
    protected $tempDir;
    public function __construct($tempDir)
    {
        $this->tempDir = $tempDir;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $contentType = $request->headers->get('Content-Type');

        if (strpos($contentType, 'multipart/related') === 0) {
            list($onlyContentType, $boundary) = $this->getContentTypeAndBoundary($contentType);
            $parts = $this->getRequestParts($request, $boundary);

            foreach ($parts as $k => $part) {
                list($fileMime, $content) = $this->splitPart($part);

                if (!$k) {
                    $request->headers->set('Content-Type', $fileMime);

                    if ($fileMime == 'application/x-www-form-urlencoded'){
                        $output = [];
                        parse_str($content, $output);
                        $request->request->add($output);
                    } else {
                        $ref = new \ReflectionClass(get_class($request));
                        $p = $ref->getProperty('content');
                        $p->setAccessible(true);
                        $p->setValue($request, $content);
                    }
                } else {
                    $tmpPath = tempnam($this->tempDir, 'MultipartRequestListener');
                    file_put_contents($tmpPath, $content);
                    $file = new File($tmpPath);
                    $request->attributes->set('_multipart_related_' . $k, $file);
                    $request->attributes->set('_multipart_related', $file);
                }
            }
        }
    }
    /**
     * @param string $content
     * @return array
     * @throws UploadProcessorException
     */
    protected function splitPart($content)
    {
        if (empty($content)) {
            throw new UploadProcessorException(sprintf('An empty content found'));
        }

        $headerLimitation = strpos($content, "\r\n"."\r\n") + 1;
        if ($headerLimitation == -1) {
            throw new UploadProcessorException('Unable to determine headers limit');
        }
        $contentType = null;
        $headersContent = substr($content, 0, $headerLimitation);
        $headersContent = trim($headersContent);
        $body = substr($content, $headerLimitation);
        $body = trim($body);

        foreach (explode("\r\n", $headersContent) as $header) {
            $parts = explode(':', $header);
            if (count($parts) != 2) {
                continue;
            }

            $name = trim($parts[0]);
            if (strtolower($name) == 'content-type') {
                $contentType = trim($parts[1]);
                break;
            }
        }

        return array($contentType, $body);
    }


    /**
     * Get part of a resource.
     * @param Request $request
     * @param $boundary
     * @return string
     * @throws UploadProcessorException
     */
    protected function getRequestParts(Request $request, $boundary)
    {
        $contentHandler = $request->getContent(true);

        $delimiter = '--'.$boundary."\r\n";
        $endDelimiter = '--'.$boundary.'--';
        $boundaryCount = 0;
        $parts = array();
        while (!feof($contentHandler)) {
            $line = fgets($contentHandler);
            if ($line === false) {
                throw new UploadProcessorException('An error appears while reading input');
            }

            if ($boundaryCount == 0) {
                if ($line != $delimiter) {
                    if (ftell($contentHandler) == strlen($line)) {
                        throw new UploadProcessorException('Expected boundary delimiter');
                    }
                } else {
                    continue;
                }
                $boundaryCount++;
            } elseif ($line == $delimiter) {
                $boundaryCount++;
                continue;
            } elseif ($line == $endDelimiter || $line == $endDelimiter . "\r\n") {
                $parts[$boundaryCount-1] = substr($parts[$boundaryCount-1], 0,-2);
                break;
            }

            if (!isset($parts[$boundaryCount])) {
                $parts[$boundaryCount] = '';
            }

            $parts[$boundaryCount] .= $line;
        }
        $parts[$boundaryCount] = substr($parts[$boundaryCount], 0,-2);
        return array_values($parts);
    }

    /**
     * Parse the content type and boundary from Content-Type header.
     * @param string $contentType
     * @return array
     * @throws UploadProcessorException
     */
    protected function getContentTypeAndBoundary($contentType)
    {
        $contentParts = explode(';', $contentType);
        if (count($contentParts) != 2) {
            throw new UploadProcessorException('Boundary may be missing');
        }

        $contentType = trim($contentParts[0]);
        $boundaryPart = trim($contentParts[1]);

        $shouldStart = 'boundary=';
        if (substr($boundaryPart, 0, strlen($shouldStart)) != $shouldStart) {
            throw new UploadProcessorException('Boundary is not set');
        }

        $boundary = substr($boundaryPart, strlen($shouldStart));
        if (substr($boundary, 0, 1) == '"' && substr($boundary, -1) == '"') {
            $boundary = substr($boundary, 1, -1);
        }

        return array($contentType, $boundary);
    }
}