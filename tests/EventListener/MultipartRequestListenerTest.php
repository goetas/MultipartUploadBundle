<?php

namespace Goetas\MultipartUploadBundle\EventListener;

use Goetas\MultipartUploadBundle\Exception\MultipartProcessorException;
use Goetas\MultipartUploadBundle\TestKernel;
use PHPUnit\Framework\TestCase;
use Riverline\MultiPartParser\StreamedPart;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MultipartRequestListenerTest extends TestCase
{
    /**
     * @var GetResponseEvent
     */
    private $event;

    /**
     * @var MultipartRequestListener
     */
    private $listener;

    /**
     * @var Request
     */
    private $request;

    public function setUp()
    {
        $this->listener = new MultipartRequestListener();

        $this->request = new Request();
        $this->event = new GetResponseEvent(new TestKernel(), $this->request, HttpKernelInterface::MASTER_REQUEST);
    }

    public function testPrimaryNotInjectedAsMain()
    {
        $this->listener = new MultipartRequestListener(false);

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->request->headers->set('Other', 'value');
        $this->setRequestContent(
            $content = "--delimiter\r\n"
                . "Header: value\r\n"
                . "Content-Type: application/*\r\n"
                . "\r\n"
                . "Content\r\n"
                . "--delimiter--\r\n"
        );

        $this->listener->onKernelRequest($this->event);

        // headers not changed
        self::assertEquals([
            'content-type' => ['multipart/related; boundary=delimiter'],
            'other' => ['value']
        ],
            $this->request->headers->all()
        );

        self::assertCount(1, $this->request->attributes->get('related-parts'));

        // request content not changed
        self::assertEquals($content, $this->request->getContent());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testItCompile()
    {
        $listener = new MultipartRequestListener();

        $listener->onKernelRequest($this->event);
    }

    public function testBoundaryIsNotSetError()
    {
        $this->request->headers->set('Content-Type', 'multipart/related');
        $this->listener->onKernelRequest($this->event);

        self::assertSame(400, $this->event->getResponse()->getStatusCode());
    }

    public function testBodyWithoutBoundaryDelimiterError()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent('body without delimiter');

        $this->listener->onKernelRequest($this->event);
        self::assertSame(400, $this->event->getResponse()->getStatusCode());
    }

    public function testSplitPartError()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        self::assertSame(400, $this->event->getResponse()->getStatusCode());
    }

    public function testSplitPartError2()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        self::assertSame(400, $this->event->getResponse()->getStatusCode());
    }

    public function testPrimaryPartEmpty()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals([], $this->request->headers->all());

        // Content was cleared since it was empty in the primary part
        self::assertEquals('', $this->request->getContent());
    }

    public function testPrimaryPartHasContentButNotHeaders()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "Content\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals([], $this->request->headers->all());

        // Part's content was become Request's content
        self::assertEquals('Content', $this->request->getContent());
    }

    public function testPrimaryPartWithHeadersButNotContent()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent(
            "--delimiter\r\n" .
            "Header: value\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter--"
        );

        $this->listener->onKernelRequest($this->event);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals(['header' => ['value']], $this->request->headers->all());

        // Part's content was become Request's content
        self::assertEquals('', $this->request->getContent());
    }

    public function testPrimaryPartWithHeadersAndContent()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->request->headers->set('Other', 'value');
        $this->setRequestContent(
            ""
            . "--delimiter\r\n"
            . "Header: value\r\n"
            . "Content-Type: application/*\r\n"
            . "\r\n"
            . "Content\r\n"
            . "--delimiter--\r\n"
        );

        $this->listener->onKernelRequest($this->event);

        // Part's headers was merged with Request's headers
        self::assertEquals([
            'header' => ['value'],
            'content-type' => ['application/*'],
            'other' => ['value']],
            $this->request->headers->all()
        );

        // Part's content was become Request's content
        self::assertEquals('Content', $this->request->getContent());
    }

    public function testAttachments()
    {
        // Not printable binary content
        $binaryContent = "\x1b\x0d\x0a\x00";

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter\r\n" .
            "Content-Disposition:attachment; filename=Nome+file.pdf\r\n" .
            "\r\n" .
            "$binaryContent\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        /** @var UploadedFile $attachment */
        $attachment = $this->request->attributes->get('attachments')[0];
        self::assertInstanceOf(UploadedFile::class, $attachment);
        self::assertEquals('Nome file.pdf', $attachment->getClientOriginalName());
        self::assertEquals($binaryContent, file_get_contents($attachment->getPathname()));
    }

    public function testRelatedParts()
    {
        // Not printable binary content
        $binaryContent = "\x1b\x0d\x0a\x00";

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter\r\n" .
            "Header: Related\r\n" .
            "\r\n" .
            "$binaryContent\r\n" .
            "--delimiter--");

        $this->listener->onKernelRequest($this->event);

        $relatedPart = $this->request->attributes->get('related-parts')[1];
        self::assertInstanceOf(StreamedPart::class, $relatedPart);
        self::assertEquals($binaryContent, $relatedPart->getBody());
        self::assertEquals(['header' => 'Related'], $relatedPart->getHeaders());
    }

    public function testFormDataWithoutFilename()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter" .
            "\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter" .
            "\r\n" .
            "Content-Disposition:form-data; name=field[children][]" . "\r\n" .
            "Content-Type:mime/type" . "\r\n" .
            "Content-Length:7" . "\r\n" .
            "Content-Md5:F15C1CAE7882448B3FB0404682E17E61" . "\r\n" .
            "\r\n" .
            "Content" . "\r\n" .
            "--delimiter--"
        );

        $this->listener->onKernelRequest($this->event);

        $value = $this->request->request->get('field')['children'][0];
        self::assertEquals('Content', $value);
    }

    public function testFileUploads()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n" .
            "\r\n" .
            "\r\n" .
            "--delimiter\r\n" .
            "Content-Disposition:form-data; name=field[children][]; filename=Nome+file.pdf\r\n" .
            "Content-Type:mime/type\r\n" .
            "Content-Md5:F15C1CAE7882448B3FB0404682E17E61\r\n" .
            "\r\n" .
            "Content\r\n" .
            "--delimiter--\r\n");

        $this->listener->onKernelRequest($this->event);

        /** @var UploadedFile $attachment */
        $attachment = $this->request->files->get('field')['children'][0];
        self::assertInstanceOf(UploadedFile::class, $attachment);
        self::assertEquals('Content', file_get_contents($attachment->getPathname()));
        self::assertEquals('Nome file.pdf', $attachment->getClientOriginalName());
        self::assertEquals('mime/type', $attachment->getClientMimeType());
        self::assertTrue($attachment->isValid());
    }

    private function setRequestContent($content)
    {
        $p = new \ReflectionProperty(Request::class, 'content');
        $p->setAccessible(true);
        $p->setValue($this->request, $content);
    }
}
