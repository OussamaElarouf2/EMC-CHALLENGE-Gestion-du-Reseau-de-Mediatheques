<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Transfer;
use App\Enum\TransferStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TransferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transfer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Transfert logistique')
            ->setEntityLabelInPlural('Transferts logistiques')
            ->setDefaultSort(['requestedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Preserve logistics and transfer audit trail
        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('copy', 'Exemplaire');
        yield AssociationField::new('sourceBranch', 'Site d\'origine');
        yield AssociationField::new('targetBranch', 'Site de destination');
        yield AssociationField::new('reservation', 'Réservation rattachée');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Demandé' => TransferStatus::REQUESTED,
                'En transit' => TransferStatus::IN_TRANSIT,
                'Réceptionné' => TransferStatus::RECEIVED,
            ])
            ->renderAsBadges([
                'requested' => 'warning',
                'in_transit' => 'primary',
                'received' => 'success',
            ]);
        yield DateTimeField::new('requestedAt', 'Demandé le');
        yield DateTimeField::new('shippedAt', 'Expédié le');
        yield DateTimeField::new('receivedAt', 'Réceptionné le');
    }
}
