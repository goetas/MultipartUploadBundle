<?php

namespace Goetas\MultipartUploadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder('goetas_multipart_upload');

        if (method_exists($tb, 'goetas_multipart_upload')) {
            $root = $tb->getRootNode()->children();
        } else {
            $root = $tb->root('jms_serializer')->children();
        }

        $root
            ->booleanNode('first_part_as_default')
                ->defaultTrue()
            ->end();

        return $tb;
    }

}
