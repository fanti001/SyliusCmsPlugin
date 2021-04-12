<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusCmsPlugin\Controller;

use BitBag\SyliusCmsPlugin\Entity\PageInterface;
use BitBag\SyliusCmsPlugin\Entity\PageTranslationInterface;
use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PageController extends ResourceController
{
    public function renderLinkAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::SHOW);

        $code = $request->get('code');
        $pageResourceResolver = $this->get('bitbag_sylius_cms_plugin.resolver.page_resource');

        $page = $pageResourceResolver->findOrLog($code);

        if (null === $page) {
            return new Response();
        }

        $this->eventDispatcher->dispatch(ResourceActions::SHOW, $configuration, $page);


        if ($configuration->isHtmlRequest()) {
            return $this->render($configuration->getTemplate(ResourceActions::SHOW . '.html'), [
                'configuration' => $configuration,
                'metadata' => $this->metadata,
                'resource' => $page,
                $this->metadata->getName() => $page,
            ]);
        }

        return $this->viewHandler->handle($configuration, View::create($page));
    }

    public function previewAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::CREATE);

        /** @var PageInterface $page */
        $page = $request->get('id') && $this->repository->find($request->get('id')) ?
            $this->repository->find($request->get('id')) :
            $this->factory->createNew();
        $form = $this->resourceFormFactory->create($configuration, $page);
        $defaultLocale = $this->getParameter('locale');

        $form->handleRequest($request);

        $page->setFallbackLocale($request->get('_locale', $defaultLocale));
        $page->setCurrentLocale($request->get('_locale', $defaultLocale));

        $this->resolveImage($page);

        $this->get('bitbag_sylius_cms_plugin.controller.helper.form_errors_flash')->addFlashErrors($form);

        if (!$configuration->isHtmlRequest()) {
            $this->viewHandler->handle($configuration, View::create($page));
        }

        return $this->render($configuration->getTemplate(ResourceActions::CREATE . '.html'), [
            'resource' => $page,
            'preview' => true,
            $this->metadata->getName() => $page,
        ]);
    }

    private function resolveImage(PageInterface $page): void
    {
        /** @var PageTranslationInterface $translation */
        $translation = $page->getTranslation();

        $image = $translation->getImage();

        if (!$image || !$image->getPath()) {
            return;
        }

        $file = $image->getFile() ?: new File($this->getParameter('sylius_core.public_dir') . $image->getPath());
        $base64Content = base64_encode(file_get_contents($file->getPathname()));
        $path = 'data:' . $file->getMimeType() . ';base64, ' . $base64Content;

        $image->setPath($path);
        $page->getTranslation()->setImage($image);
    }
}
