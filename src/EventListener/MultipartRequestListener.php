<?php

namespace Goetas\MultipartUploadBundle\EventListener;

use Riverline\MultiPartParser\Converters\HttpFoundation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class MultipartRequestListener
{
    public function onKernelRequest(GetResponseEvent $event)
    {
        try {
            $this->processRequest($event->getRequest());
        } catch (\LogicException $e) {
            $message = 'Bad Request';

            if ($e->getMessage()) {
                $message .= ': ' . $e->getMessage();
            }

            $response = new Response($message, 400);

            $event->setResponse($response);
        }
    }

    private function processRequest(Request $request)
    {
        $contentType = $request->headers->get('Content-Type');
        if (0 === strpos($contentType, 'multipart/related')) {

            $streamedPart = HttpFoundation::convert($request);
            $request->headers->remove('Content-Type');
            $request->headers->remove('Content-Length');

            $attachments = [];
            $relatedParts = $streamedPart->getParts();

            foreach ($relatedParts as $k => $part) {

                if (0 === $k) {
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

                $fp = tmpfile();
                fwrite($fp, $part->getBody());
                rewind($fp);

                $tmpPath = stream_get_meta_data($fp)['uri'];
                $file = new UploadedFile($tmpPath, urldecode($part->getFileName()), $part->getMimeType(), null, true);
                @$file->ref = $fp;

                if (($formName = $this->isDispositionFormData($contentDisposition)) !== null) {
                    $formPath = $this->parseKey($formName);

                    $files = $request->files->all();
                    $files = $this->mergeFilesArray($files, $formPath, $file);
                    $request->files->replace($files);
                } elseif (($fileName = $part->getFileName()) !== null) {
                    $attachments[] = $file;
                }
            }

            $request->attributes->set('attachments', $attachments);
            $request->attributes->set('related-parts', $relatedParts);
        }
    }

    private function mergeFilesArray($array, $path, $file)
    {
        if (count($path) > 0) {
            $key = array_shift($path);

            if (!is_array($array)) {
                $array = [];
            }

            if (!empty($key)) {
                $array[$key] = $this->mergeFilesArray($array[$key] ?? [], $path, $file);
            } else {
                $array[] = $file;
            }

            return $array;
        }

        return $file;
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
