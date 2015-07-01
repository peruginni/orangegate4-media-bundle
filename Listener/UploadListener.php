<?php

namespace Symbio\OrangeGate\MediaBundle\Listener;

use Oneup\UploaderBundle\Event\PostPersistEvent;
use Sonata\MediaBundle\Model\MediaManagerInterface;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symbio\OrangeGate\MediaBundle\Admin\MediaAdmin;
use Symbio\OrangeGate\MediaBundle\Entity\Media;
use Symbio\OrangeGate\MediaBundle\SymbioOrangeGateMediaBundle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UploadListener
{
    private $manager;

    private $categoryManager;

    private $pool;

    private $container;

    public function __construct(MediaManagerInterface $manager, Pool $pool, CategoryManagerInterface $categoryManager, ContainerInterface $container)
    {
        $this->manager = $manager;
        $this->pool = $pool;
        $this->categoryManager = $categoryManager;
        $this->container = $container;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();
        $mediaType = $request->get('mediaType');
        $providerName = $mediaType == 'image' ? 'sonata.media.provider.image' : 'sonata.media.provider.file';
        $context = $request->get('context');
        $categoryId = $request->get('category');

        $category = $this->categoryManager->find($categoryId);

        $file = $event->getFile();

        $media = new Media();
        $media->setProviderName($providerName);
        $media->setContext($context);
        $media->setCategory($category);
        $media->setBinaryContent($file);

        //TODO - improve the retrieval of the file name
        if(is_array(current($request->files)) && is_array(current(current($request->files)))) {
            $media->setName(current(current($request->files))[0]->getClientOriginalName());
        }

        $provider = $this->pool->getProvider($providerName);
        $provider->transform($media);

        $this->manager->save($media);

        $mediaAdmin = $this->container->get('orangegate.media.admin.media');
        $response = $event->getResponse();
        $response['name'] = $media->getName();
        $response['size'] = $media->getSize();
        $response['url'] = $mediaType == 'image' ? $provider->generatePublicUrl($media, $provider->getFormatName($media, 'widget_thumb')) : $mediaAdmin->generateObjectUrl('edit', $media);
        $response['id'] = $media->getId();
        $response['mediaType'] = $mediaType;
        $response['contentType'] = $media->getContentType();

        @unlink($file->getPathname());
    }
}
