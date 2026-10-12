<?php
namespace App\Command;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Entity\EvidenceFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:privacy:inventory', description: 'Comptages anonymes des données et des liens anciens, sans suppression.')]
final class PrivacyInventoryCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'older-than-days', null, InputOption::VALUE_REQUIRED,
            'Seuil d’âge indicatif pour les inventaires, sans purge', '365'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = filter_var(
            $input->getOption('older-than-days'),
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 36500]]
        );
        if (!is_int($days)) {
            $output->writeln('<error>Le nombre de jours doit être compris entre 1 et 36500.</error>');
            return Command::INVALID;
        }

        $cutoff = new \DateTimeImmutable('-'.$days.' days', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expired = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')->from(ConsentRequest::class, 'r')
            ->where('r.usedAt IS NULL')->andWhere('r.expiresAt < :now')
            ->setParameter('now', $now)->getQuery()->getSingleScalarResult();
        $oldInvitations = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')->from(ConsentRequest::class, 'r')
            ->where('r.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)->getQuery()->getSingleScalarResult();
        $archived = $this->em->getRepository(Client::class)->count(['lifecycleStage' => Client::ARCHIVED]);
        $dueForReview = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')->from(Client::class, 'c')
            ->where('c.lifecycleStage = :archived')
            ->andWhere('c.contractEndedAt <= :threshold')
            ->setParameter('archived', Client::ARCHIVED)
            ->setParameter('threshold', $now->modify('-1 year'))
            ->getQuery()->getSingleScalarResult();
        $oldDecisions = $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')->from(ConsentDecision::class, 'd')
            ->where('d.decidedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)->getQuery()->getSingleScalarResult();

        foreach ([
            'Clients' => $this->em->getRepository(Client::class)->count([]),
            'Clients archivés (nominatifs)' => $archived,
            'Archives de 1 an ou plus (à examiner, aucune action automatique)' => (int) $dueForReview,
            'Liens de consentement' => $this->em->getRepository(ConsentRequest::class)->count([]),
            'Liens expirés et inutilisés' => (int) $expired,
            'Liens de plus de '.$days.' jours' => (int) $oldInvitations,
            'Décisions' => $this->em->getRepository(ConsentDecision::class)->count([]),
            'Décisions de plus de '.$days.' jours' => (int) $oldDecisions,
            'Justificatifs référencés' => $this->em->getRepository(EvidenceFile::class)->count([]),
            'Événements d’audit' => $this->em->getRepository(AuditEvent::class)->count([]),
        ] as $label => $count) {
            $output->writeln($label.' : '.$count);
        }
        $output->writeln('<comment>Inventaire uniquement : aucune donnée effacée. Les durées et motifs de conservation restent à valider.</comment>');

        return Command::SUCCESS;
    }
}
