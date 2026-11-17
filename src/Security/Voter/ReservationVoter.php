<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends Voter<string, Reservation>
 */
class ReservationVoter extends Voter
{
    public const VIEW = 'RESERVATION_VIEW';
    public const CANCEL = 'RESERVATION_CANCEL';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CANCEL], true)
            && $subject instanceof Reservation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Librarians and Admins can view and cancel any reservation
        if ($this->security->isGranted('ROLE_LIBRARIAN')) {
            return true;
        }

        /** @var Reservation $reservation */
        $reservation = $subject;
        $reservationMember = $reservation->getMember();

        if ($reservationMember === null || $reservationMember->getUser() === null) {
            return false;
        }

        // Members can only view and cancel their own reservations
        return $reservationMember->getUser() === $user;
    }
}
