<?php

namespace Goetas\MultipartUploadBundle\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class MultipartRequestListenerTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions()
    {
        return array(
            new GoetasMultipartUploadExtension()
        );
    }

    public function testListenerIsRegistered()
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'goetas.multipart_upload.request_listener',
            'kernel.event_listener',
            ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 200]
        );
    }
}
