<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réservation')
            ->setEntityLabelInPlural('Réservations')
            ->setDefaultSort(['createdAt' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Preserve reservation history and FIFO integrity
        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('edition', 'Édition demandée');
        yield AssociationField::new('member', 'Adhérent');
        yield AssociationField::new('pickupBranch', 'Médiathèque de retrait');
        yield AssociationField::new('assignedCopy', 'Exemplaire assigné');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente (FIFO)' => ReservationStatus::WAITING,
                'Prête pour retrait' => ReservationStatus::READY,
                'Honorée' => ReservationStatus::FULFILLED,
                'Expirée' => ReservationStatus::EXPIRED,
                'Annulée' => ReservationStatus::CANCELLED,
            ])
            ->renderAsBadges([
                'waiting' => 'warning',
                'ready' => 'success',
                'fulfilled' => 'secondary',
                'expired' => 'danger',
                'cancelled' => 'dark',
            ]);
        yield DateTimeField::new('createdAt', 'Date de demande');
        yield DateTimeField::new('pickupDeadlineAt', 'Limite de retrait');
        yield DateTimeField::new('fulfilledAt', 'Honorée le');
        yield TextField::new('activeMemberEditionKey', 'Clé unicité active')
            ->hideOnForm()
            ->onlyOnDetail();
    }
}
