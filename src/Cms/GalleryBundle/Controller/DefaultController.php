<?php

namespace Cms\GalleryBundle\Controller;

use Cms\XutBundle\Entity\Gist;
use Cms\XutBundle\Entity\Tag;
use Cms\GalleryBundle\Form\ImageType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->listAction();
    }

    /**
     * Shows requested gallery image
     *
     * @param int $image_id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function viewAction($image_id)
    {
        $em = $this->getDoctrine()->getManager();
        $image = $em->getRepository('CmsXutBundle:Gist')->findOneById($image_id);

        if (!is_null($image)) {
            return $this->render('CmsGalleryBundle:Default:view.html.twig', array(
                'image' => $image
            ));
        } else {
            throw $this->createNotFoundException('Requested item does not exist');
        }
    }

    /**
     * Lists all galery images
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();
        $images = $em->createQueryBuilder()
            ->select('bl')
            ->from('CmsXutBundle:Gist', 'bl')
            ->where('bl.type = :gisttype')
            ->setParameter('gisttype', 'image')
            ->addOrderBy('bl.date_created')
            ->getQuery()
            ->getResult();

        return $this->render('CmsGalleryBundle:Default:list.html.twig', array(
            'images' => $images
        ));
    }

    /**
     * Renders edit image page
     *
     * @param int $image_id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws AccessDeniedException
     */
    public function editAction($image_id = 0)
    {
        if ($this->_isAdmin()) {
            if (!$image_id) {
                $image = new Gist();
            } else {
                $em = $this->getDoctrine()->getManager();
                $image = $em->getRepository('CmsXutBundle:Gist')->find($image_id);
                /* TODO: check if image exists */
            }

            $form = $this->createForm(new ImageType(), $image);

            return $this->render('CmsGalleryBundle:Default:form.html.twig', array(
                'form' => $form->createView(),
                'image' => $image
            ));
        } else {
            throw new AccessDeniedException();
        }
    }

    public function saveAction($image_id = 0)
    {
        $request = $this->getRequest();
        if ($request->getMethod() == 'POST' && $this->_isAdmin()) {
            $em = $this->getDoctrine()->getManager();
            $currentDate = date("Y-m-d H:i:s");
            if (!$image_id) {
                $image = new Gist();
                $image->setType('image');
                $image->setDateCreated(new \DateTime($currentDate));
            } else {
                $image = $em->getRepository('CmsXutBundle:Gist')->find($image_id);
                if (is_null($image)) {
                    return $this->get('backpack')->sendJsonResponse('Image with requested id does not exist', 'error');
                }
            }

            $image->setDateUpdated(new \DateTime($currentDate));
            $form = $this->createForm(new ImageType(), $image);
            $form->handleRequest($request);
            if ($form->isValid()) {
                /* Tags were passed as string. Process the string */
                $_postValues = $request->request->get('gallery_image'); //FIXME: another form name
                $this->_setTagsFromString($_postValues['tagsfield'], $image);
                $image->upload();
                $em->persist($image);
                $em->flush();
            } else {
                return $this->get('backpack')->sendJsonResponse('The form has missing required fields', 'error');
            }
        } else {
            throw new AccessDeniedException();
        }

        return $this->get('backpack')->sendJsonResponse('');
    }

    protected function _setTagsFromString($tags, $image)
    {
        $newTags = array();
        if (!empty($tags)) {
            $em = $this->getDoctrine()->getManager();
            $tagsArray = explode(',', $tags);
            array_walk($tagsArray, array($this, '_normalizeTagNames'));

            /* Get all tags */
            $allTags = $em->getRepository('CmsXutBundle:Tag')->findAll();

            /* Add tags to the post */
            foreach ($allTags as $_tag) {
                if (count($tagsArray) < 1) {
                    break;
                }
                if (false !== ($key = array_search($_tag->getName(), $tagsArray))) {
                    array_push($newTags, $_tag->getId());
                    $image->addTag($_tag);
                    unset($tagsArray[$key]);
                }
            }

            /* We have new tags, add them to the database */
            if (count($tagsArray) > 0) {
                foreach ($tagsArray as $_tag) {
                    $tag = new Tag();
                    $tag->setType('image');
                    $tag->setName($_tag);
                    $em->persist($tag);
                }
                $em->flush();
            }
        } else {
            /* If the tags string is empty, remove all tags */
            $image->removeAllTags(); /* FIXME: */
        }
    }

    public function removeAction($image_id)
    {
        if ($this->_isAdmin()) {
            $em = $this->getDoctrine()->getManager();
            $image = $em->getRepository('CmsXutBundle:Gist')->find($image_id);

            if (!is_null($image)) {
                $em->remove($image);
                $em->flush();
            } else {
                return $this->get('backpack')->sendJsonResponse('Image with requested id does not exist', 'error');
            }

            return $this->get('backpack')->sendJsonResponse('');
        } else {
            throw new AccessDeniedException();
        }
    }

    /**
     * Returns true if current user has been authorized as admin
     *
     * @return bool
     */
    protected function _isAdmin()
    {
        return true === $this->get('security.context')->isGranted('ROLE_ADMIN');
    }

    protected function _normalizeTagNames(&$tag)
    {
        $tag = trim($tag);
    }
}
