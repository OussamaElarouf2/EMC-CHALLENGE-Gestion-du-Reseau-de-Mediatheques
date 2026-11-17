<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Edition;
use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class EditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Edition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Édition')
            ->setEntityLabelInPlural('Éditions')
            ->setSearchFields(['title', 'isbn', 'publisher'])
            ->setDefaultSort(['title' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('work', 'Œuvre parente');
        yield TextField::new('title', 'Titre de l\'édition');
        yield TextField::new('isbn', 'ISBN (10 ou 13 chiffres)');
        yield ChoiceField::new('mediaType', 'Support')
            ->setChoices([
                'Livre' => MediaType::BOOK,
                'Bande dessinée' => MediaType::COMIC,
                'DVD' => MediaType::DVD,
            ]);
        yield TextField::new('publisher', 'Éditeur');
        yield IntegerField::new('publicationYear', 'Année');
        yield UrlField::new('coverUrl', 'Couverture (URL)');
        yield ChoiceField::new('completenessStatus', 'Complétude')
            ->setChoices([
                'Complet' => CompletenessStatus::COMPLETE,
                'À compléter' => CompletenessStatus::TO_COMPLETE,
            ])
            ->renderAsBadges([
                'complete' => 'success',
                'to_complete' => 'warning',
            ]);
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }
}
