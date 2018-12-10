<?php

namespace Goetas\MultipartUploadBundle\EventListener;

use Goetas\MultipartUploadBundle\Exception\MultipartProcessorException;
use Goetas\MultipartUploadBundle\RelatedPart;
use Goetas\MultipartUploadBundle\TestKernel;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
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

    /**
     * @var vfsStreamDirectory
     */
    private $vfs;

    public function setUp()
    {
        $this->vfs = vfsStream::setup();

        $this->listener = new MultipartRequestListener($this->vfs->url());

        $this->request = new Request();
        $this->event = new GetResponseEvent(new TestKernel(), $this->request, HttpKernelInterface::MASTER_REQUEST);
        error_reporting(E_ALL);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testItCompile()
    {
        $this->listener = new MultipartRequestListener($this->vfs);

        $this->listener->onKernelRequest($this->event);
    }

    public function testItIsProcessingRequest()
    {
        $listener = $this->getMockBuilder(MultipartRequestListener::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['onKernelRequest'])
            ->getMock();

        $listener->expects($this->once())
            ->method('processRequest')
            ->with($this->request);

        $listener->onKernelRequest($this->event);
    }

    public function testItHandlesMultipartProcessorException()
    {
        $listener = $this->getMockBuilder(MultipartRequestListener::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['onKernelRequest'])
            ->getMock();

        $listener->expects($this->once())
            ->method('processRequest')
            ->willThrowException(new MultipartProcessorException());

        $listener->onKernelRequest($this->event);

        $response = $this->event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('Bad Request', $response->getContent());
    }

    public function testBoundaryIsNotSetError()
    {
        $this->expectException(MultipartProcessorException::class);
        $this->expectExceptionMessageRegExp('/boundary [a-z\s]* missing/i');

        $this->request->headers->set('Content-Type', 'multipart/related');

        $this->listener->processRequest($this->request);
    }

    public function testBodyWithoutBoundaryDelimiterError()
    {
        $this->expectException(MultipartProcessorException::class);
        $this->expectExceptionMessage('boundary delimiter');

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent('body without delimiter');

        $this->listener->processRequest($this->request);
    }

    public function testSplitPartError()
    {
        $this->expectException(MultipartProcessorException::class);
        $this->expectExceptionMessage('Unable to determine headers limit');

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n--delimiter--");

        $this->listener->processRequest($this->request);
    }

    public function testSplitPartError2()
    {
        $this->expectException(MultipartProcessorException::class);
        $this->expectExceptionMessage('Unable to determine headers limit');

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        self::assertEquals('', $this->request->getContent());
    }

    public function testPrimaryPartEmpty()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n\r\n\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals([], $this->request->headers->all());

        // Content was cleared since it was empty in the primary part
        self::assertEquals('', $this->request->getContent());
    }

    public function testPrimaryPartHasContentButNotHeaders()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n\r\nContent\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals([], $this->request->headers->all());

        // Part's content was become Request's content
        self::assertEquals('Content', $this->request->getContent());
    }

    public function testPrimaryPartWithHeadersButNotContent()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\nHeader: value\r\n\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        // `Content-Type` was removed from request since it was not defined in the primary part
        self::assertEquals(['header' => ['value']], $this->request->headers->all());

        // Part's content was become Request's content
        self::assertEquals('', $this->request->getContent());
    }

    public function testPrimaryPartWithHeadersAndContent()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->request->headers->set('Other', 'value');
        $this->setRequestContent("--delimiter\r\nHeader: value\r\nContent-Type: application/*\r\n\r\nContent\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        // Part's headers was merged with Request's headers
        self::assertEquals(['header' => ['value'], 'content-type' => ['application/*'], 'other' => ['value']], $this->request->headers->all());

        // Part's content was become Request's content
        self::assertEquals('Content', $this->request->getContent());
    }

    public function testAttachments()
    {
        // Not printable binary content
        $binaryContent = "\x1b\x0d\x0a\x00";

        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n\r\n\r\n--delimiter\r\nContent-Disposition:attachment; filename=Nome+file.pdf\r\n\r\n$binaryContent\r\n--delimiter--");

        $this->listener->processRequest($this->request);

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
        $this->setRequestContent("--delimiter\r\n\r\n\r\n--delimiter\r\nHeader: Related\r\n\r\n$binaryContent\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        /** @var RelatedPart $relatedParts */
        $relatedParts = $this->request->attributes->get('related-parts')[0];
        self::assertInstanceOf(RelatedPart::class, $relatedParts);
        self::assertEquals($binaryContent, stream_get_contents($relatedParts->getContent(true)));
        self::assertEquals(['header' => ['Related']], $relatedParts->getHeaders()->all());
    }

    public function testFileUploads()
    {
        $this->request->headers->set('Content-Type', 'multipart/related; boundary=delimiter');
        $this->setRequestContent("--delimiter\r\n\r\n\r\n--delimiter\r\nContent-Disposition:form-data; name=field[children][]; filename=Nome+file.pdf\r\nContent-Type:mime/type\r\nContent-Length:7\r\nContent-Md5:F15C1CAE7882448B3FB0404682E17E61\r\n\r\nContent\r\n--delimiter--");

        $this->listener->processRequest($this->request);

        /** @var UploadedFile $attachment */
        $attachment = $this->request->files->get('field')['children'][0];
        self::assertInstanceOf(UploadedFile::class, $attachment);
        self::assertEquals('Content', file_get_contents($attachment->getPathname()));
        self::assertEquals('Nome file.pdf', $attachment->getClientOriginalName());
        self::assertEquals('mime/type', $attachment->getClientMimeType());
        self::assertEquals(7, $attachment->getClientSize());
        self::assertEquals(0, $attachment->getError());
    }

    private function setRequestContent($content)
    {
        $p = new \ReflectionProperty(Request::class, 'content');
        $p->setAccessible(true);
        $p->setValue($this->request, $content);
    }
}
