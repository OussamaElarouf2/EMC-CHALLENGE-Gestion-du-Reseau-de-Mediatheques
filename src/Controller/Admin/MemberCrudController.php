<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Member;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Member::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Adhérent')
            ->setEntityLabelInPlural('Adhérents')
            ->setSearchFields(['membershipNumber'])
            ->setDefaultSort(['membershipNumber' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('membershipNumber', 'Numéro d\'adhérent');
        yield AssociationField::new('user', 'Compte utilisateur');
        yield AssociationField::new('plan', 'Abonnement');
        yield AssociationField::new('homeBranch', 'Site de rattachement');
        yield DateField::new('subscriptionEndsAt', 'Fin d\'abonnement');
        yield DateTimeField::new('createdAt', 'Inscrit le')->hideOnForm();
    }
}
