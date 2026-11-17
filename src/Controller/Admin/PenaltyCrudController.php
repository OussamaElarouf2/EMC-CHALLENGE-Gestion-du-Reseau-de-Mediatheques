<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Penalty;
use App\Enum\PenaltyReason;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PenaltyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalty::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pénalité')
            ->setEntityLabelInPlural('Pénalités')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Preserve accounting and penal history
        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('loan', 'Prêt concerné');
        yield TextField::new('amount', 'Montant (MAD)');
        yield ChoiceField::new('reason', 'Motif')
            ->setChoices([
                'Retard' => PenaltyReason::LATE,
                'Exemplaire perdu' => PenaltyReason::LOST,
                'Exemplaire détérioré' => PenaltyReason::DAMAGED,
            ])
            ->renderAsBadges([
                'late' => 'warning',
                'lost' => 'danger',
                'damaged' => 'danger',
            ]);
        yield BooleanField::new('isPaid', 'Réglée')->renderAsSwitch(false);
        yield DateTimeField::new('paidAt', 'Date de règlement');
        yield DateTimeField::new('createdAt', 'Générée le')->hideOnForm();
    }
}
