<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Branch;
use App\Entity\Contributor;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\ContributorRole;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Clock\ClockInterface;

class CatalogueFixtures extends Fixture implements DependentFixtureInterface
{
    public const EDITION_POPULAR = 'edition-popular';
    public const TOTAL_COPIES = 300;

    public function __construct(
        private readonly ClockInterface $clock
    ) {
    }

    public function getDependencies(): array
    {
        return [
            ReferenceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now();
        $faker = Factory::create('fr_FR');
        $faker->seed(20261117);

        /** @var Branch $branch1 */
        $branch1 = $this->getReference(ReferenceFixtures::BRANCH_1, Branch::class);
        /** @var Branch $branch2 */
        $branch2 = $this->getReference(ReferenceFixtures::BRANCH_2, Branch::class);
        $branches = [$branch1, $branch2];

        // 28 curated real works with real authors, publishers, and media types
        $worksData = [
            // Books (16)
            [
                'title' => 'Les Misérables',
                'author' => 'Victor Hugo',
                'publisher' => 'Gallimard',
                'year' => 1862,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Notre-Dame de Paris',
                'author' => 'Victor Hugo',
                'publisher' => 'Folio Classique',
                'year' => 1831,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'L\'Étranger',
                'author' => 'Albert Camus',
                'publisher' => 'Gallimard',
                'year' => 1942,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'La Peste',
                'author' => 'Albert Camus',
                'publisher' => 'Gallimard',
                'year' => 1947,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Le Petit Prince',
                'author' => 'Antoine de Saint-Exupéry',
                'publisher' => 'Gallimard Jeunesse',
                'year' => 1943,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
                'isPopular' => true,
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'publisher' => 'Gallimard',
                'year' => 1949,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'La Ferme des animaux',
                'author' => 'George Orwell',
                'publisher' => 'Folio',
                'year' => 1945,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Le Comte de Monte-Cristo',
                'author' => 'Alexandre Dumas',
                'publisher' => 'Le Livre de Poche',
                'year' => 1844,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Les Trois Mousquetaires',
                'author' => 'Alexandre Dumas',
                'publisher' => 'Folio',
                'year' => 1844,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Du côté de chez Swann',
                'author' => 'Marcel Proust',
                'publisher' => 'Gallimard',
                'year' => 1913,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Germinal',
                'author' => 'Émile Zola',
                'publisher' => 'Garnier-Flammarion',
                'year' => 1885,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Vingt mille lieues sous les mers',
                'author' => 'Jules Verne',
                'publisher' => 'Hetzel / Hachette',
                'year' => 1870,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Le Seigneur des Anneaux : La Communauté de l\'Anneau',
                'author' => 'J.R.R. Tolkien',
                'publisher' => 'Christian Bourgois',
                'year' => 1954,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Harry Potter à l\'école des sorciers',
                'author' => 'J.K. Rowling',
                'publisher' => 'Gallimard Jeunesse',
                'year' => 1997,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Cent ans de solitude',
                'author' => 'Gabriel García Márquez',
                'publisher' => 'Seuil',
                'year' => 1967,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'La Métamorphose',
                'author' => 'Franz Kafka',
                'publisher' => 'Folio',
                'year' => 1915,
                'type' => MediaType::BOOK,
                'role' => ContributorRole::AUTHOR,
            ],

            // Comics (7)
            [
                'title' => 'Tintin : Le Lotus bleu',
                'author' => 'Hergé',
                'publisher' => 'Casterman',
                'year' => 1936,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Tintin au Tibet',
                'author' => 'Hergé',
                'publisher' => 'Casterman',
                'year' => 1960,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Astérix le Gaulois',
                'author' => 'René Goscinny',
                'illustrator' => 'Albert Uderzo',
                'publisher' => 'Dargaud',
                'year' => 1961,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Astérix et Cléopâtre',
                'author' => 'René Goscinny',
                'illustrator' => 'Albert Uderzo',
                'publisher' => 'Dargaud',
                'year' => 1965,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Gaston Lagaffe : Gare aux gaffes',
                'author' => 'André Franquin',
                'publisher' => 'Dupuis',
                'year' => 1966,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Lucky Luke : Daisy Town',
                'author' => 'Morris',
                'illustrator' => 'René Goscinny',
                'publisher' => 'Dargaud',
                'year' => 1972,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],
            [
                'title' => 'Persépolis',
                'author' => 'Marjane Satrapi',
                'publisher' => 'L\'Association',
                'year' => 2000,
                'type' => MediaType::COMIC,
                'role' => ContributorRole::AUTHOR,
            ],

            // DVDs (5)
            [
                'title' => 'Inception',
                'author' => 'Christopher Nolan',
                'publisher' => 'Warner Bros',
                'year' => 2010,
                'type' => MediaType::DVD,
                'role' => ContributorRole::DIRECTOR,
            ],
            [
                'title' => 'Interstellar',
                'author' => 'Christopher Nolan',
                'publisher' => 'Paramount Pictures',
                'year' => 2014,
                'type' => MediaType::DVD,
                'role' => ContributorRole::DIRECTOR,
            ],
            [
                'title' => 'Le Voyage de Chihiro',
                'author' => 'Hayao Miyazaki',
                'publisher' => 'Studio Ghibli',
                'year' => 2001,
                'type' => MediaType::DVD,
                'role' => ContributorRole::DIRECTOR,
            ],
            [
                'title' => 'Dune (Partie 1)',
                'author' => 'Denis Villeneuve',
                'publisher' => 'Warner Bros',
                'year' => 2021,
                'type' => MediaType::DVD,
                'role' => ContributorRole::DIRECTOR,
            ],
            [
                'title' => 'Le Fabuleux Destin d\'Amélie Poulain',
                'author' => 'Jean-Pierre Jeunet',
                'publisher' => 'UGC Fox Distribution',
                'year' => 2001,
                'type' => MediaType::DVD,
                'role' => ContributorRole::DIRECTOR,
            ],
        ];

        /** @var array<string, Contributor> $contributorsCache */
        $contributorsCache = [];
        $editions = [];

        foreach ($worksData as $index => $data) {
            $work = new Work($data['title']);
            $manager->persist($work);

            // Deduplicate contributors
            $authorName = $data['author'];
            if (!isset($contributorsCache[$authorName])) {
                $contributor = new Contributor($authorName);
                $manager->persist($contributor);
                $contributorsCache[$authorName] = $contributor;
            }
            $author = $contributorsCache[$authorName];

            // ISBN generated deterministically via Faker
            $isbn = $data['type'] === MediaType::DVD ? null : $faker->unique()->isbn13();

            $edition = new Edition(
                $work,
                $data['title'],
                $data['publisher'],
                $data['year'],
                $data['type'],
                CompletenessStatus::COMPLETE,
                $now->modify(sprintf('-%d days', $faker->numberBetween(30, 300))),
                $isbn
            );
            $manager->persist($edition);

            $primaryContrib = new EditionContributor($edition, $author, $data['role'], 1);
            $manager->persist($primaryContrib);

            if (isset($data['illustrator'])) {
                $illustratorName = $data['illustrator'];
                if (!isset($contributorsCache[$illustratorName])) {
                    $illContrib = new Contributor($illustratorName);
                    $manager->persist($illContrib);
                    $contributorsCache[$illustratorName] = $illContrib;
                }
                $secondaryContrib = new EditionContributor(
                    $edition,
                    $contributorsCache[$illustratorName],
                    ContributorRole::ILLUSTRATOR,
                    2
                );
                $manager->persist($secondaryContrib);
            }

            $editions[] = $edition;
            $this->addReference('edition-' . $index, $edition);

            if (!empty($data['isPopular'])) {
                $this->addReference(self::EDITION_POPULAR, $edition);
            }
        }

        // Generate exactly 300 Copies distributed across the 28 editions
        // 20 editions get 11 copies = 220; 8 editions get 10 copies = 80; total = 300
        $copyGlobalIndex = 0;
        foreach ($editions as $editionIndex => $edition) {
            $numCopiesForEdition = $editionIndex < 20 ? 11 : 10;

            for ($i = 0; $i < $numCopiesForEdition; ++$i) {
                $branch = $branches[$copyGlobalIndex % 2];
                $barcode = sprintf('CPY-%05d', $copyGlobalIndex + 1);

                $copy = new Copy(
                    $edition,
                    $branch,
                    $barcode,
                    CopyStatus::AVAILABLE,
                    $now->modify(sprintf('-%d days', $faker->numberBetween(15, 200)))
                );
                $manager->persist($copy);

                $this->addReference('copy-' . $copyGlobalIndex, $copy);
                ++$copyGlobalIndex;
            }
        }

        $manager->flush();
    }
}
