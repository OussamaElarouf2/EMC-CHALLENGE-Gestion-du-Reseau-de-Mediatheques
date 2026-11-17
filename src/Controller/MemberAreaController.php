<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
class MemberAreaController extends AbstractController
{
    #[Route('/espace-adherent', name: 'app_member_area')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $member = null;
        if ($user !== null) {
            $member = $em->getRepository(Member::class)->findOneBy(['user' => $user]);
        }

        return $this->render('member/index.html.twig', [
            'user' => $user,
            'member' => $member,
        ]);
    }
}
