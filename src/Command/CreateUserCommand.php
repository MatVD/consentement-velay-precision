<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Créer un compte avec un rôle explicite, mot de passe saisi sans écho.')]
final class CreateUserCommand extends Command
{
    private const ROLES = [
        'admin' => 'ROLE_ADMIN',
        'operator' => 'ROLE_OPERATOR',
        'auditor' => 'ROLE_AUDITOR',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email du collaborateur');
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Rôle : admin, operator ou auditor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $roleName = (string) $input->getOption('role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Adresse email invalide.</error>');
            return Command::INVALID;
        }
        if (!isset(self::ROLES[$roleName])) {
            $output->writeln('<error>Rôle obligatoire : admin, operator ou auditor.</error>');
            return Command::INVALID;
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $output->writeln('<error>Un compte existe déjà pour cet email.</error>');
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

        $user = (new User())->setEmail($email)->setRoles([self::ROLES[$roleName]]);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $output->writeln('<info>Compte créé avec le rôle '.$roleName.'.</info>');
        return Command::SUCCESS;
    }
}
