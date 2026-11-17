<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\OpenLibrary\BookImporter;
use App\Service\OpenLibrary\ImportResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_LIBRARIAN')]
class OpenLibraryImportController extends AbstractController
{
    #[Route('/admin/import-isbn', name: 'app_admin_import_isbn', methods: ['GET', 'POST'])]
    public function import(Request $request, BookImporter $bookImporter): Response
    {
        $lastIsbn = '';
        $result = null;

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('import_isbn', $token)) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_admin_import_isbn');
            }

            $lastIsbn = trim((string) $request->request->get('isbn'));
            $result = $bookImporter->import($lastIsbn);

            if ($result->isSuccess()) {
                $this->addFlash('success', $result->message ?? 'Notice importée avec succès !');
                if ($result->edition !== null) {
                    return $this->redirectToRoute('app_catalogue_show', ['id' => $result->edition->getId()]);
                }
            } elseif ($result->status === ImportResult::STATUS_SKIPPED) {
                $this->addFlash('info', $result->message ?? 'Ce document existe déjà dans le catalogue.');
                if ($result->edition !== null) {
                    return $this->redirectToRoute('app_catalogue_show', ['id' => $result->edition->getId()]);
                }
            } else {
                $this->addFlash('error', $result->message ?? 'Impossible d\'importer cet ISBN.');
            }
        }

        return $this->render('admin/import_isbn.html.twig', [
            'lastIsbn' => $lastIsbn,
            'result' => $result,
        ]);
    }
}
