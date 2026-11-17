<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Copy;
use App\Enum\CopyStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CopyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Copy::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Exemplaire')
            ->setEntityLabelInPlural('Exemplaires')
            ->setSearchFields(['barcode'])
            ->setDefaultSort(['barcode' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('barcode', 'Code-barres');
        yield AssociationField::new('edition', 'Édition');
        yield AssociationField::new('currentBranch', 'Médiathèque actuelle');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Disponible' => CopyStatus::AVAILABLE,
                'En prêt' => CopyStatus::ON_LOAN,
                'Mis de côté' => CopyStatus::HELD_FOR_PICKUP,
                'En transfert' => CopyStatus::IN_TRANSIT,
                'Perdu' => CopyStatus::LOST,
                'Dégradé' => CopyStatus::DAMAGED,
            ])
            ->renderAsBadges([
                'available' => 'success',
                'on_loan' => 'info',
                'held_for_pickup' => 'warning',
                'in_transit' => 'primary',
                'lost' => 'danger',
                'damaged' => 'danger',
            ]);
        yield DateTimeField::new('createdAt', 'Enregistré le')->hideOnForm();
    }
}
