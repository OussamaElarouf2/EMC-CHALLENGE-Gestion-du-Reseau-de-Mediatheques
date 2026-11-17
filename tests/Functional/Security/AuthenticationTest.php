<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Branch;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthenticationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function purgeDatabase(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['member', 'app_user', 'plan', 'branch'] as $table) {
            $conn->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createUser(string $email, string $plainPassword, array $roles): User
    {
        $user = new User($email, '', $roles);
        $hashed = $this->hasher->hashPassword($user, $plainPassword);

        // Reflect password into entity
        $reflection = new \ReflectionProperty(User::class, 'password');
        $reflection->setValue($user, $hashed);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testAnonymousRedirectedToLoginFromAdmin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testAnonymousRedirectedToLoginFromMemberArea(): void
    {
        $this->client->request('GET', '/espace-adherent');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageRendersSuccessfully(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/login"]');
        self::assertSelectorTextContains('h1', 'Authentification');
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'invalid@mediatheque.local',
            '_password' => 'WrongPassword',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-danger');
    }

    public function testLibrarianCanLoginAndIsRedirectedToAdmin(): void
    {
        $this->createUser('librarian@mediatheque.local', 'Librarian123!', ['ROLE_LIBRARIAN']);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'librarian@mediatheque.local',
            '_password' => 'Librarian123!',
        ]);

        $this->client->submit($form);

        // Redirects to /login/redirect, then to /admin
        self::assertResponseRedirects('/login/redirect');
        $this->client->followRedirect();

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testMemberCanLoginAndIsRedirectedToMemberArea(): void
    {
        $user = $this->createUser('member@mediatheque.local', 'Member123!', ['ROLE_MEMBER']);

        $branch = new Branch('Centrale', '10 Rue de la Paix', 'Paris');
        $plan = new Plan('Standard', 3, 21, 1, 14, '1.00');
        $member = new Member($user, $plan, $branch, 'CARD-AUTH-1', new \DateTimeImmutable('+1 month'), new \DateTimeImmutable('now'));

        $this->em->persist($branch);
        $this->em->persist($plan);
        $this->em->persist($member);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'member@mediatheque.local',
            '_password' => 'Member123!',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/login/redirect');
        $this->client->followRedirect();

        self::assertResponseRedirects('/espace-adherent');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Espace Adhérent');
        self::assertAnySelectorTextContains('.font-monospace', 'CARD-AUTH-1');
    }

    public function testMemberCannotAccessAdminBackOffice(): void
    {
        $memberUser = $this->createUser('forbidden@mediatheque.local', 'Member123!', ['ROLE_MEMBER']);

        $this->client->loginUser($memberUser);
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLogoutClearsSession(): void
    {
        $memberUser = $this->createUser('logout@mediatheque.local', 'Member123!', ['ROLE_MEMBER']);

        $this->client->loginUser($memberUser);
        $this->client->request('GET', '/espace-adherent');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->client->request('GET', '/espace-adherent');
        self::assertResponseRedirects('/login');
    }
}
