<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search-users',
    description: 'poisk'
)]
class SearchUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'email')
            ->addOption('active', 'a', InputOption::VALUE_OPTIONAL, '1-ok 0-ne ok');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email  = $input->getOption('email');
        $active = $input->getOption('active');

        $statusName = null;
        if ($active !== null) {
            $active = trim((string) $active);
            if (in_array($active, ['1', 'true', 'yes'], true)) {
                $statusName = 'Активен';
            } elseif (in_array($active, ['0', 'false', 'no'], true)) {
                $statusName = 'Не активен';
            } else {
                $io->error('--active 0 ili 1');
                return Command::FAILURE;
            }
        }

        $qb = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.status', 's')
            ->addSelect('u', 's');

        if ($email && trim($email) !== '') {
            $qb->andWhere('LOWER(u.email) LIKE LOWER(:email)')
                ->setParameter('email', '%' . trim($email) . '%');
        }

        if ($statusName !== null) {
            $qb->andWhere('s.name = :statusName')
                ->setParameter('statusName', $statusName);
        }

        $qb->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');

        $users = $qb->getQuery()->getResult();

        if (empty($users)) {
            $io->info('Пользователи не найдены');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Фамилия', 'Имя', 'Возраст', 'Email', 'Статус'],
            array_map(
                fn($user) => [
                    $user->getId() ?? '—',
                    $user->getLastName()   ?? '—',
                    $user->getFirstName()  ?? '—',
                    $user->getAge()        ?? '—',
                    $user->getEmail()      ?? '—',
                    $user->getStatus()?->getName() ?? '(нет статуса)',
                ],
                $users
            )
        );

        $io->success('Найдено пользователей: ' . count($users));

        return Command::SUCCESS;
    }
}