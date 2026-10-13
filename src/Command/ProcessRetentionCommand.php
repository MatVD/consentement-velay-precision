<?php
namespace App\Command;

use App\Service\ClientRetentionProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:retention:process',
    description: 'Prévisualiser le cycle de conservation, ou appliquer une purge après triple confirmation.'
)]
final class ProcessRetentionCommand extends Command
{
    public function __construct(private readonly ClientRetentionProcessor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Autoriser les transitions irréversibles.');
        $this->addOption('confirm-irreversible', null, InputOption::VALUE_NONE, 'Confirmer la disparition des dossiers, PDF et preuves.');
        $this->addOption('confirm-legal-approval', null, InputOption::VALUE_NONE, 'Confirmer que les règles de conservation ont été validées.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de dossiers (1 à 500)', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var(
            $input->getOption('limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 500]]
        );
        if (!is_int($limit)) {
            $output->writeln('<error>Limite invalide (1 à 500).</error>');
            return Command::INVALID;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $preview = $this->processor->preview($now);
        $output->writeln('Dossiers éligibles à anonymisation définitive : '.$preview['due_clients']);
        $output->writeln('Cohortes agrégées à supprimer : '.$preview['expired_aggregates']);
        $output->writeln('PDF en attente de suppression physique : '.$preview['pending_pdfs']);

        if (!$input->getOption('apply')) {
            $output->writeln('<info>Simulation uniquement. Aucun dossier ou fichier supprimé.</info>');
            return Command::SUCCESS;
        }
        if (getenv('VELAY_RETENTION_PURGE_ENABLED') !== '1'
            || !$input->getOption('confirm-irreversible')
            || !$input->getOption('confirm-legal-approval')
        ) {
            $output->writeln('<error>Purge refusée : activation serveur et deux confirmations explicites requises.</error>');
            return Command::FAILURE;
        }

        $result = $this->processor->process($now, $limit);
        $output->writeln('Dossiers définitivement supprimés : '.$result['anonymized']);
        $output->writeln('Dossiers ajoutés aux compteurs anonymes : '.$result['aggregate_counted']);
        $output->writeln('Cohortes agrégées supprimées : '.$result['statistics_expired']);
        $output->writeln('PDF effectivement effacés : '.$result['pdfs_removed']);
        $output->writeln('PDF en attente de nouvelle tentative : '.$result['pdfs_pending']);
        if ($result['pdfs_pending'] > 0) {
            $output->writeln('<error>Intervention requise : des fichiers privés restent en attente de suppression.</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
