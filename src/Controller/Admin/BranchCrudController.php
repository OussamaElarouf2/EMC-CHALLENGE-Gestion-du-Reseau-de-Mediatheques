<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Branch;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BranchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Branch::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Médiathèque (Site)')
            ->setEntityLabelInPlural('Médiathèques (Sites)')
            ->setSearchFields(['name', 'address'])
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom du site');
        yield TextField::new('address', 'Adresse');
    }
}
