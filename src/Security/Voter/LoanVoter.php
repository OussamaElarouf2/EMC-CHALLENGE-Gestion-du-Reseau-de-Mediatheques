<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Loan;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends Voter<string, Loan>
 */
class LoanVoter extends Voter
{
    public const VIEW = 'LOAN_VIEW';
    public const EXTEND = 'LOAN_EXTEND';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EXTEND], true)
            && $subject instanceof Loan;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Librarians and Admins can view and extend any loan
        if ($this->security->isGranted('ROLE_LIBRARIAN')) {
            return true;
        }

        /** @var Loan $loan */
        $loan = $subject;
        $loanMember = $loan->getMember();

        if ($loanMember === null || $loanMember->getUser() === null) {
            return false;
        }

        // Members can only view and extend their own loans
        return $loanMember->getUser() === $user;
    }
}
