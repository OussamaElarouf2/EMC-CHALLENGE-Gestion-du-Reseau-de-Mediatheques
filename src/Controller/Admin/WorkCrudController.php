<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Work;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Work::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Œuvre')
            ->setEntityLabelInPlural('Œuvres')
            ->setSearchFields(['originalTitle'])
            ->setDefaultSort(['originalTitle' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('originalTitle', 'Titre de l\'œuvre');
        yield AssociationField::new('editions', 'Éditions associées')->onlyOnDetail();
    }
}
