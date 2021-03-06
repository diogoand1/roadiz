<?php
/*
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 *
 * @file CacheController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Controllers;

use RZ\Roadiz\Console\CacheCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\RozierApp;

/**
 * {@inheritdoc}
 */
class CacheController extends RozierApp
{
    /**
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function deleteDoctrineCache(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_DOCTRINE_CACHE_DELETE');

        $form = $this->buildDeleteDoctrineForm();
        $form->handleRequest();

        if ($form->isValid()) {
            CacheCommand::clearDoctrine();
            CacheCommand::clearRouteCollections();
            CacheCommand::clearTranslations();
            CacheCommand::clearTemplates();

            $msg = $this->getTranslator()->trans('cache.deleted');
            $this->publishConfirmMessage($request, $msg);

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            $response = new RedirectResponse(
                $this->getService('urlGenerator')->generate('adminHomePage')
            );
            $response->prepare($request);

            return $response->send();
        }

        $this->assignation['form'] = $form->createView();

        $this->assignation['cachesInfo'] = [
            'resultCache' => $this->getService('em')->getConfiguration()->getResultCacheImpl(),
            'hydratationCache' => $this->getService('em')->getConfiguration()->getHydrationCacheImpl(),
            'queryCache' => $this->getService('em')->getConfiguration()->getQueryCacheImpl(),
            'metadataCache' => $this->getService('em')->getConfiguration()->getMetadataCacheImpl(),
        ];

        foreach ($this->assignation['cachesInfo'] as $key => $value) {
            if (null !== $value) {
                $this->assignation['cachesInfo'][$key] = get_class($value);
            } else {
                $this->assignation['cachesInfo'][$key] = false;
            }
        }

        return $this->render('cache/deleteDoctrine.html.twig', $this->assignation);
    }

    /**
     * @return Symfony\Component\Form\Form
     */
    private function buildDeleteDoctrineForm()
    {
        $builder = $this->getService('formFactory')
                        ->createBuilder('form');

        return $builder->getForm();
    }

    /**
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function deleteSLIRCache(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_DOCTRINE_CACHE_DELETE');

        $form = $this->buildDeleteSLIRForm();
        $form->handleRequest();

        if ($form->isValid()) {
            CacheCommand::clearCachedAssets();

            $msg = $this->getTranslator()->trans('cache.deleted');
            $this->publishConfirmMessage($request, $msg);

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            $response = new RedirectResponse(
                $this->getService('urlGenerator')->generate('adminHomePage')
            );
            $response->prepare($request);

            return $response->send();
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('cache/deleteSLIR.html.twig', $this->assignation);
    }

    /**
     * @return Symfony\Component\Form\Form
     */
    private function buildDeleteSLIRForm()
    {
        $builder = $this->getService('formFactory')
                        ->createBuilder('form');

        return $builder->getForm();
    }
}
