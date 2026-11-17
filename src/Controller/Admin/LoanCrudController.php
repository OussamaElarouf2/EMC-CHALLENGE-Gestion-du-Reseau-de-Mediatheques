<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class LoanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Loan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prêt')
            ->setEntityLabelInPlural('Prêts')
            ->setDefaultSort(['borrowedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Circulation history must never be deleted to preserve audit trails and avoid FK cascades
        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('copy', 'Exemplaire');
        yield AssociationField::new('member', 'Adhérent');
        yield DateTimeField::new('borrowedAt', 'Date d\'emprunt');
        yield DateField::new('dueDate', 'Échéance prévue');
        yield DateTimeField::new('returnedAt', 'Retour effectif');
        yield IntegerField::new('extensionCount', 'Prolongations');
        yield IntegerField::new('activeCopyId', 'Verrou concurrence (ID exemplaire actif)')
            ->hideOnForm()
            ->onlyOnDetail();
    }
}
