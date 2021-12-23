<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\ORM\EntityManagerInterface;
use App\Parser\RbkTopParser;

class ParseRbkCommand extends Command
{
    protected static $defaultName = 'parse:rbk';
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Парсинг новостей с главной страницы сайта rbc.ru')
             ->setHelp('Парсинг новостей с главной страницы сайта rbc.ru')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parser = new RbkTopParser();
        $parser->setEntityManager($this->entityManager);
        $parser->setOutput($output);
        $parser->parseNewsList();

        return Command::SUCCESS;
    }
}