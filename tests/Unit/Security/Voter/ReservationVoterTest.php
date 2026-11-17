<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Branch;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use App\Enum\ReservationStatus;
use App\Security\Voter\ReservationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ReservationVoterTest extends TestCase
{
    private Security $security;
    private ReservationVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter = new ReservationVoter($this->security);
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $reservation = $this->createMock(Reservation::class);

        $result = $this->voter->vote($token, $reservation, ['UNSUPPORTED_ATTRIBUTE']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnUnsupportedSubject(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, new \stdClass(), [ReservationVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testDeniesWhenUserNotAuthenticated(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $reservation = $this->createMock(Reservation::class);

        $result = $this->voter->vote($token, $reservation, [ReservationVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsAccessToLibrarianForAnyReservation(): void
    {
        $librarianUser = new User('librarian@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($librarianUser);

        $this->security->method('isGranted')
            ->with('ROLE_LIBRARIAN')
            ->willReturn(true);

        $reservation = $this->createMock(Reservation::class);

        $viewResult = $this->voter->vote($token, $reservation, [ReservationVoter::VIEW]);
        $cancelResult = $this->voter->vote($token, $reservation, [ReservationVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $cancelResult);
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

        $reservation = new Reservation($edition, $member, $branch, ReservationStatus::WAITING, new \DateTimeImmutable('now'));

        $viewResult = $this->voter->vote($token, $reservation, [ReservationVoter::VIEW]);
        $cancelResult = $this->voter->vote($token, $reservation, [ReservationVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $cancelResult);
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

        $reservation = new Reservation($edition, $member, $branch, ReservationStatus::WAITING, new \DateTimeImmutable('now'));

        $viewResult = $this->voter->vote($token, $reservation, [ReservationVoter::VIEW]);
        $cancelResult = $this->voter->vote($token, $reservation, [ReservationVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $viewResult);
        self::assertSame(VoterInterface::ACCESS_DENIED, $cancelResult);
    }
}
