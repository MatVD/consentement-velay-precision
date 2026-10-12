<?php
namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\EvidenceFile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

class EvidenceDownloadAuditor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    public function record(EvidenceFile $evidence): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw new AccessDeniedException('Utilisateur administrateur introuvable.');
        }

        $decision = $evidence->getDecision();
        $this->em->persist(new AuditEvent(
            $decision->getClient(), $decision->getRequest(),
            'evidence_downloaded', 'admin',
            ['user_id' => $user->getId(), 'evidence_id' => $evidence->getId()],
        ));
        $this->em->flush();
    }
}
