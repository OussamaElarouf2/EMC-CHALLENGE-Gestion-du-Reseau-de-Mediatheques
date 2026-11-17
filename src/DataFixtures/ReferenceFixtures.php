<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Branch;
use App\Entity\Plan;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ReferenceFixtures extends Fixture
{
    public const BRANCH_1 = 'branch-1';
    public const BRANCH_2 = 'branch-2';
    public const PLAN_YOUTH = 'plan-youth';
    public const PLAN_STANDARD = 'plan-standard';
    public const PLAN_PASSION = 'plan-passion';
    public const USER_ADMIN = 'user-admin';
    public const USER_LIBRARIAN = 'user-librarian';

    public function load(ObjectManager $manager): void
    {
        // 1. Two Branches
        $branch1 = new Branch('Médiathèque Centrale', '12 Rue de la République, Agadir');
        $branch2 = new Branch('Médiathèque Victor Hugo', '45 Boulevard Hassan II, Agadir');

        $manager->persist($branch1);
        $manager->persist($branch2);

        $this->addReference(self::BRANCH_1, $branch1);
        $this->addReference(self::BRANCH_2, $branch2);

        // 2. Three Subscription Plans
        $planYouth = new Plan('Jeunesse / Étudiant', 3, 21, 1, 14, '1.00');
        $planStandard = new Plan('Adulte Standard', 5, 28, 2, 14, '2.00');
        $planPassion = new Plan('Pass Passion / Famille', 10, 35, 2, 21, '2.00');

        $manager->persist($planYouth);
        $manager->persist($planStandard);
        $manager->persist($planPassion);

        $this->addReference(self::PLAN_YOUTH, $planYouth);
        $this->addReference(self::PLAN_STANDARD, $planStandard);
        $this->addReference(self::PLAN_PASSION, $planPassion);

        // 3. Demo Staff Accounts (Admin & Librarian)
        $adminUser = new User(
            'admin@mediatheque.local',
            password_hash('Admin123!', PASSWORD_BCRYPT),
            ['ROLE_ADMIN']
        );
        $librarianUser = new User(
            'librarian@mediatheque.local',
            password_hash('Librarian123!', PASSWORD_BCRYPT),
            ['ROLE_LIBRARIAN']
        );

        $manager->persist($adminUser);
        $manager->persist($librarianUser);

        $this->addReference(self::USER_ADMIN, $adminUser);
        $this->addReference(self::USER_LIBRARIAN, $librarianUser);

        $manager->flush();
    }
}
