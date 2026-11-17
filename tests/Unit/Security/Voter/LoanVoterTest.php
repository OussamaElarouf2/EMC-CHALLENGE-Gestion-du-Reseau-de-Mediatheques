<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use App\Security\Voter\LoanVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class LoanVoterTest extends TestCase
{
    private Security $security;
    private LoanVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter = new LoanVoter($this->security);
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $loan = $this->createMock(Loan::class);

        $result = $this->voter->vote($token, $loan, ['UNSUPPORTED_ATTRIBUTE']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnUnsupportedSubject(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, new \stdClass(), [LoanVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testDeniesWhenUserNotAuthenticated(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $loan = $this->createMock(Loan::class);

        $result = $this->voter->vote($token, $loan, [LoanVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsAccessToLibrarianForAnyLoan(): void
    {
        $librarianUser = new User('librarian@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($librarianUser);

        $this->security->method('isGranted')
            ->with('ROLE_LIBRARIAN')
            ->willReturn(true);

        $loan = $this->createMock(Loan::class);

        $viewResult = $this->voter->vote($token, $loan, [LoanVoter::VIEW]);
        $extendResult = $this->voter->vote($token, $loan, [LoanVoter::EXTEND]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $extendResult);
    }

    public function testGrantsAccessToOwnerMember(): void
    {
        $ownerUser = new User('member@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($ownerUser);

        $this->security->method('isGranted')
            ->with('ROLE_LIBRARIAN')
            ->willReturn(false);

        $branch = new Branch('Centrale', '10 Rue de la Paix', 'Paris');
        $plan = new Plan('Standard', 3, 21, 1, 14, '1.00');
        $member = new Member($ownerUser, $plan, $branch, 'CARD-100', new \DateTimeImmutable('+1 month'), new \DateTimeImmutable('now'));

        $work = new Work('Titre Test', MediaType::BOOK);
        $edition = new Edition($work, 'Titre Edition', 'Editeur', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'), '9781234567890');
        $copy = new Copy($edition, $branch, 'BARCODE-1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        $loan = new Loan($copy, $member, new \DateTimeImmutable('now'), new \DateTimeImmutable('+21 days'));

        $viewResult = $this->voter->vote($token, $loan, [LoanVoter::VIEW]);
        $extendResult = $this->voter->vote($token, $loan, [LoanVoter::EXTEND]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $extendResult);
    }

    public function testDeniesAccessToOtherMember(): void
    {
        $ownerUser = new User('owner@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $intruderUser = new User('intruder@mediatheque.local', 'hash', ['ROLE_MEMBER']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($intruderUser);

        $this->security->method('isGranted')
            ->with('ROLE_LIBRARIAN')
            ->willReturn(false);

        $branch = new Branch('Centrale', '10 Rue de la Paix', 'Paris');
        $plan = new Plan('Standard', 3, 21, 1, 14, '1.00');
        $member = new Member($ownerUser, $plan, $branch, 'CARD-100', new \DateTimeImmutable('+1 month'), new \DateTimeImmutable('now'));

        $work = new Work('Titre Test', MediaType::BOOK);
        $edition = new Edition($work, 'Titre Edition', 'Editeur', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'), '9781234567890');
        $copy = new Copy($edition, $branch, 'BARCODE-1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        $loan = new Loan($copy, $member, new \DateTimeImmutable('now'), new \DateTimeImmutable('+21 days'));

        $viewResult = $this->voter->vote($token, $loan, [LoanVoter::VIEW]);
        $extendResult = $this->voter->vote($token, $loan, [LoanVoter::EXTEND]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_DENIED, $extendResult);
    }
}
