<?php

namespace Cms\BlogBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class BlogpostType extends AbstractType
{
    public function getName()
    {
        return 'blogpost';
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name');
        $builder->add('tagsfield', 'text', array('required' => false));
        $builder->add('content', 'textarea');
    }
}