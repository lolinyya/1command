<?php

namespace App\Command;

use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-users',
    description: 'импорт пользователей'
)]
class ImportUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::REQUIRED, 'имя CSV-файла')
            ->addOption('delimiter', 'd', InputOption::VALUE_OPTIONAL, 'разделитель полей', ';')
            ->addOption('no-header', null, InputOption::VALUE_NONE, 'первая строка не является заголовком');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = $input->getArgument('filename');
        $delimiter = $input->getOption('delimiter');
        $skipHeader = !$input->getOption('no-header');
        $filePath = __DIR__ . '/files/' . $filename;

        if (!file_exists($filePath)) {
            $io->error("файл не найден: $filePath");
            return Command::FAILURE;
        }
        try {
            $reader = ReaderEntityFactory::createCSVReader();
            $reader->setFieldDelimiter($delimiter);
            $reader->open($filePath);
        } catch (IOException $e) {
            $io->error('ошибка открытия файла: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('импорт пользователей');
        $this->entityManager->beginTransaction();

        try {
            $imported = 0;
            $updated = 0;
            $errors = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                $rowIndex = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;
                    $cells = $row->toArray();
                    if (empty(array_filter($cells))) {
                        continue;
                    }
                    if ($skipHeader && $rowIndex === 1) {
                        continue;
                    }
                    $email = $cells[0] ?? null;
                    $firstName = $cells[1] ?? null;
                    $lastName = $cells[2] ?? null;
                    $age = isset($cells[3]) ? (int)$cells[3] : null;
                    $statusName = $cells[4] ?? null;
                    if (empty($email)) {
                        $io->warning("строка $rowIndex: пропущена, нет email");
                        $errors++;
                        continue;
                    }
                    if ($age !== null && !is_numeric($cells[3])) {
                        $io->warning("строка $rowIndex: возраст не является числом, строка пропущена");
                        $errors++;
                        continue;
                    }
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($user) {
                        $updated++;
                    } else {
                        $user = new User();
                        $imported++;
                    }
                    $user->setEmail($email);
                    $user->setFirstName($firstName ?: null);
                    $user->setLastName($lastName ?: null);
                    $user->setAge($age);
                    if (!empty($statusName)) {
                        $status = $this->entityManager->getRepository(Status::class)->findOneBy(['name' => $statusName]);
                        if (!$status) {
                            $status = new Status();
                            $status->setName($statusName);
                            $this->entityManager->persist($status);
                            $io->note("строка $rowIndex: создан новый статус '$statusName'");
                        }
                        $user->setStatus($status);
                    } else {
                        $user->setStatus(null);
                    }
                    $this->entityManager->persist($user);
                    if (($imported + $updated) % 100 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                }
            }
            $this->entityManager->flush();
            $this->entityManager->commit();
            $io->success(sprintf(
                'импорт завершён',
                $imported,
                $updated,
                $errors
            ));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error('ошибка при импорте: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $reader->close();
        }
    }
}
