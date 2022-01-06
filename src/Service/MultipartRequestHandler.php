<?php

namespace Goetas\MultipartUploadBundle\Service;

use Riverline\MultiPartParser\Converters\HttpFoundation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class MultipartRequestHandler
{
    /**
     * @var bool
     */
    private $injectFirstPart;

    public function __construct(bool $injectFirstPart = true)
    {
        $this->injectFirstPart = $injectFirstPart;
    }

    public function processRequest(Request $request)
    {
        $contentType = $request->headers->get('Content-Type');
        if (null === $contentType || 0 !== strpos($contentType, 'multipart/') || false !== strpos($contentType, 'multipart/form-data')) {
            return;
        }

        $streamedPart = HttpFoundation::convert($request);

        if ($this->injectFirstPart === true) {
            $request->headers->remove('Content-Type');
            $request->headers->remove('Content-Length');
        }
        $attachments = [];
        $relatedParts = $streamedPart->getParts();

        foreach ($relatedParts as $k => $part) {
            if ($this->injectFirstPart === true && 0 === $k) {
                $request->headers->add($part->getHeaders());
                if ('application/x-www-form-urlencoded' === $part->getHeader('Content-Type')) {
                    $output = [];
                    parse_str($part->getBody(), $output);
                    $request->request->add($output);
                } else {
                    $this->setRequestContent($request, $part->getBody());
                }
                continue;
            }

            $contentDisposition = $part->getHeader('Content-Disposition');

            if ($contentDisposition === null) {
                continue;
            }

            $fileName = $part->getFileName();

            if ($fileName!== null) {
                $fp = tmpfile();
                fwrite($fp, $part->getBody());
                rewind($fp);

                $tmpPath = stream_get_meta_data($fp)['uri'];

                $ref = new \ReflectionClass('Symfony\Component\HttpFoundation\File\UploadedFile');
                $params = $ref->getConstructor()->getParameters();
                if ('error' === $params[3]->getName()) { // symfony 4
                    $file = new UploadedFile($tmpPath, urldecode($fileName), $part->getMimeType(), null, true);
                } else { // symfony < 4
                    $file = new UploadedFile($tmpPath, urldecode($fileName), $part->getMimeType(), filesize($tmpPath), null, true);
                }
                @$file->ref = $fp;
                $attachments[] = $file;
            }

            if (($formName = $this->isDispositionFormData($contentDisposition)) !== null) {
                $formPath = $this->parseKey($formName);

                if ($fileName !== null) {
                    $files = $request->files->all();
                    $files = $this->mergeFormArray($files, $formPath, $file);
                    $request->files->replace($files);
                } else {
                    $data = $request->request->all();
                    $data = $this->mergeFormArray($data, $formPath, $part->getBody());
                    $request->request->replace($data);
                }
            }
        }

        $request->attributes->set('attachments', $attachments);
        $request->attributes->set('related-parts', $relatedParts);
    }

    private function mergeFormArray($array, $path, $data)
    {
        if (count($path) > 0) {
            $key = array_shift($path);

            if (!is_array($array)) {
                $array = [];
            }

            if (!empty($key)) {
                $array[$key] = $this->mergeFormArray($array[$key] ?? [], $path, $data);
            } else {
                $array[] = $data;
            }

            return $array;
        }

        return $data;
    }

    private function parseKey($key)
    {
        return array_map(
            function ($v) {
                return trim($v, ']');
            },
            explode('[', $key)
        );
    }

    private function isDispositionFormData($value)
    {
        if (preg_match('/(?:^|form-data;\s*)name="?([^";]+)("|;|$)/', $value, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function setRequestContent(Request $request, $content)
    {
        $p = new \ReflectionProperty(Request::class, 'content');
        $p->setAccessible(true);
        $p->setValue($request, $content);
    }
}
