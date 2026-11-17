<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Plan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Plan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Formule d\'abonnement')
            ->setEntityLabelInPlural('Formules d\'abonnement')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom de la formule');
        yield IntegerField::new('maxSimultaneousLoans', 'Prêts simultanés max');
        yield IntegerField::new('loanDurationDays', 'Durée de prêt (jours)');
        yield IntegerField::new('maxExtensions', 'Prolongations autorisées');
        yield IntegerField::new('extensionDurationDays', 'Durée prolongation (jours)');
        yield TextField::new('penaltyRatePerDay', 'Pénalité journalière (MAD)');
    }
}
