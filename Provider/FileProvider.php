<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Provider;

use Sonata\MediaBundle\Entity\BaseMedia as Media;
use Symfony\Component\Form\Form;
    
class FileProvider extends BaseProvider
{

    public function getReferenceImage(Media $media)
    {

        return sprintf('%s/%s',
            $this->generatePath($media),
            $media->getProviderReference()
        );
    }

    public function getAbsolutePath(Media $media)
    {

        return $this->getReferenceImage($media);
    }

    public function requireThumbnails()
    {
        return false;
    }

    /**
     * build the related create form
     *
     */
    function buildEditForm(Form $form)
    {
        $form->add(new \Symfony\Component\Form\TextField('name'));
        $form->add(new \Symfony\Component\Form\CheckboxField('enabled'));
        $form->add(new \Symfony\Component\Form\TextField('author_name'));
        $form->add(new \Symfony\Component\Form\CheckboxField('cdn_is_flushable'));
        $form->add(new \Symfony\Component\Form\TextareaField('description'));
        $form->add(new \Symfony\Component\Form\TextField('copyright'));

        $form->add(new \Symfony\Component\Form\FileField('binary_content', array(
            'secret' => 'file'
        )));
    }

    /**
     * build the related create form
     *
     */
    function buildCreateForm(Form $form)
    {
        $form->add(new \Symfony\Component\Form\FileField('binary_content', array(
            'secret' => 'file'
        )));
    }
    
    public function postPersist(Media $media)
    {
        if (!$media->getBinaryContent()) {
            return;
        }

        $file = $this->getFilesystem()->get(
            sprintf('%s/%s', $this->generatePath($media), $media->getProviderReference()),
            true
        );
        $file->setContent(file_get_contents($media->getBinaryContent()->getPath()));

        $this->generateThumbnails($media);
    }

    public function postUpdate(Media $media)
    {
        if (!$media->getBinaryContent()) {
            return;
        }

        $this->fixBinaryContent($media);

        $file = $this->getFilesystem()->get(
            sprintf('%s/%s', $this->generatePath($media), $media->getProviderReference()),
            true
        );
        $file->setContent(file_get_contents($media->getBinaryContent()->getPath()));

        $this->generateThumbnails($media);
    }

    public function fixBinaryContent(Media $media)
    {
        if (!$media->getBinaryContent()) {

            return;
        }

        // if the binary content is a filename => convert to a valid File
        if (!$media->getBinaryContent() instanceof \Symfony\Component\HttpFoundation\File\File) {

            if (!is_file($media->getBinaryContent())) {
                throw new RuntimeException('The file does not exist : ' . $media->getBinaryContent());
            }

            $binary_content = new \Symfony\Component\HttpFoundation\File\File($media->getBinaryContent());

            $media->setBinaryContent($binary_content);
        }
    }

    public function prePersist(Media $media)
    {

        $this->fixBinaryContent($media);

        $media->setProviderName($this->name);
        $media->setProviderStatus(Media::STATUS_OK);

        if (!$media->getBinaryContent()) {

            return;
        }

        // this is the original name
        if (!$media->getName()) {
            $media->setName($media->getBinaryContent()->getName());
        }

        // this is the name used to store the file
        if (!$media->getProviderReference()) {
           $media->setProviderReference(sha1($media->getBinaryContent()->getName() . rand(11111, 99999)) . $media->getBinaryContent()->getExtension());
        }

        $media->setContentType($media->getBinaryContent()->getMimeType());
        $media->setSize($media->getBinaryContent()->size());

        $media->setCreatedAt(new \Datetime());
        $media->setUpdatedAt(new \Datetime());
    }


    public function generatePublicUrl(Media $media, $format)
    {

        // todo: add a valid icon set
        return $this->getCdn()->getPath(sprintf('media_bundle/images/files/%s/file.png',$format));
    }

    public function getHelperProperties(Media $media, $format, $options = array())
    {
        return array_merge(array(
          'title'       => $media->getName(),
          'thumbnail'   => $this->getReferenceImage($media),
          'file'        => $this->getReferenceImage($media),
        ), $options);
    }

    public function generatePrivateUrl(Media $media, $format)
    {

        return false;
    }

    public function preUpdate(Media $media)
    {

        $this->fixBinaryContent($media);
        
        if (!$media->getBinaryContent()) {

            return;
        }
                
        // this is the name used to store the file
        if (!$media->getProviderReference()) {
           $media->setProviderReference(sha1($media->getBinaryContent()->getName() . rand(11111, 99999)) . $media->getBinaryContent()->getExtension());
        }

        $media->setContentType($media->getBinaryContent()->getMimeType());
        $media->setSize($media->getBinaryContent()->size());
        $media->setUpdatedAt(new \Datetime());
    }

    public function preRemove(Media $media)
    {

    }
}