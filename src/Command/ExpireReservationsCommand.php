<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReservationQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Expires ready reservations whose 5-day pickup deadline has passed and reallocates copies to the next waiting members.',
)]
class ExpireReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationQueueService $reservationQueueService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Processing Expired Reservations');

        $expiredCount = $this->reservationQueueService->expireReservations();

        if ($expiredCount > 0) {
            $io->success(sprintf('Expired %d reservation(s) and reallocated available copies.', $expiredCount));
        } else {
            $io->info('No expired reservations found.');
        }

        return Command::SUCCESS;
    }
}
