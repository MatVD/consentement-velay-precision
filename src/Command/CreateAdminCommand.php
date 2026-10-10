<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Créer un compte administrateur (mot de passe masqué).')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email de l’administrateur');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Adresse email invalide.</error>');
            return Command::FAILURE;
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $output->writeln('<error>Compte existant.</error>');
            return Command::FAILURE;
        }
        $question = new Question('Mot de passe (12 caractères minimum) : ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = $this->getHelper('question')->ask($input, $output, $question);
        if (!is_string($password) || mb_strlen($password) < 12) {
            $output->writeln('<error>Mot de passe insuffisant.</error>');
            return Command::FAILURE;
        }
        $user = (new User())->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $output->writeln('<info>Administrateur créé.</info>');
        return Command::SUCCESS;
    }
}
