<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

#[AsEventListener(event: 'kernel.exception', priority: 50)]
class CustomErrorListener
{
    public function __construct(
        private readonly Environment $twig
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof NotFoundHttpException) {
            $content = $this->twig->render('bundles/TwigBundle/Exception/error404.html.twig');
            $response = new Response($content, Response::HTTP_NOT_FOUND);
            $event->setResponse($response);
            return;
        }

        if ($throwable instanceof AccessDeniedHttpException) {
            $content = $this->twig->render('bundles/TwigBundle/Exception/error403.html.twig');
            $response = new Response($content, Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
            return;
        }
    }
}
