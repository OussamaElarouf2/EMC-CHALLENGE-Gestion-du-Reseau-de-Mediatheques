<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OpenLibrary\BookImporter;
use App\Service\OpenLibrary\ImportResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-isbn',
    description: 'Import book records from Open Library API from a text file (one ISBN per line).'
)]
class ImportIsbnCommand extends Command
{
    public function __construct(
        private readonly BookImporter $bookImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the text file containing ISBNs')
            ->addOption('pause', null, InputOption::VALUE_REQUIRED, 'Pause between API calls in seconds (to respect Open Library rate limits)', '0.35');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('file');
        $pauseSeconds = (float) $input->getOption('pause');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $io->error(sprintf('File not found or unreadable: "%s"', $filePath));
            return Command::FAILURE;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            $io->warning('File is empty. No ISBNs to import.');
            return Command::SUCCESS;
        }

        $isbns = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== '' && !str_starts_with($line, '#')));
        $total = count($isbns);

        $io->title('Open Library Batch ISBN Import');
        $io->text(sprintf('Found %d ISBNs to process from "%s". Rate limit throttle: %.2fs between requests.', $total, basename($filePath), $pauseSeconds));

        $stats = [
            ImportResult::STATUS_COMPLETE => 0,
            ImportResult::STATUS_TO_COMPLETE => 0,
            ImportResult::STATUS_SKIPPED => 0,
            ImportResult::STATUS_NOT_FOUND => 0,
            ImportResult::STATUS_INVALID => 0,
            ImportResult::STATUS_ERROR => 0,
        ];

        $details = [];

        $io->progressStart($total);

        foreach ($isbns as $isbn) {
            $result = $this->bookImporter->import($isbn);

            if (isset($stats[$result->status])) {
                $stats[$result->status]++;
            } else {
                $stats[ImportResult::STATUS_ERROR]++;
            }

            $details[] = [
                'isbn' => $isbn,
                'status' => $result->status,
                'title' => $result->edition?->getTitle() ?? '-',
                'message' => $result->message ?? '',
            ];

            $io->progressAdvance();

            // Throttle API calls only if an HTTP request was likely made (not skipped or invalid)
            if (!in_array($result->status, [ImportResult::STATUS_SKIPPED, ImportResult::STATUS_INVALID], true)) {
                if ($pauseSeconds > 0) {
                    usleep((int) ($pauseSeconds * 1_000_000));
                }
            }
        }

        $io->progressFinish();

        // Print Summary Table
        $io->section('Import Batch Report');

        $table = new Table($output);
        $table->setHeaders(['Status', 'Count', 'Description']);
        $table->addRows([
            ['<info>COMPLETE</info>', $stats[ImportResult::STATUS_COMPLETE], 'Successfully imported with full metadata (title, author, publisher, cover)'],
            ['<comment>TO_COMPLETE</comment>', $stats[ImportResult::STATUS_TO_COMPLETE], 'Imported with missing fields, flagged for librarian completion (DEC-005)'],
            ['<fg=cyan>SKIPPED</fg=cyan>', $stats[ImportResult::STATUS_SKIPPED], 'Already present in local catalogue (local data prioritized, DEC-005)'],
            ['<fg=yellow>NOT_FOUND</fg=yellow>', $stats[ImportResult::STATUS_NOT_FOUND], 'No record returned by Open Library API'],
            ['<fg=magenta>INVALID</fg=magenta>', $stats[ImportResult::STATUS_INVALID], 'Invalid ISBN format'],
            ['<error>ERROR</error>', $stats[ImportResult::STATUS_ERROR], 'Failed due to an unexpected error'],
        ]);
        $table->render();

        $successCount = $stats[ImportResult::STATUS_COMPLETE] + $stats[ImportResult::STATUS_TO_COMPLETE];
        $io->success(sprintf('Batch import complete: %d / %d records added to the catalogue.', $successCount, $total));

        return Command::SUCCESS;
    }
}
