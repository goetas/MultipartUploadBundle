<?php

namespace Goetas\MultipartUploadBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class GoetasMultipartUploadExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        if ($container->hasParameter('Goetas.multipart_upload.stream_temp_dir')) {
            $tempDir = $container->getParameter('Goetas.multipart_upload.stream_temp_dir');
        } else {
            $tempDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        }

        $def = $container->getDefinition('Goetas.multipart_upload.request_listener');
        $def->setArgument(0, $tempDir);
    }
}
