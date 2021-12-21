<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

class ParseRbkCommand extends Command
{
    protected static $defaultName = 'parse:rbk';

    protected function configure(): void
    {
        $this
            // описание показывается при запуске "php bin/console list"
            ->setDescription('Парсинг новостей с главной страницы сайта rbc.ru')

            // полное описание команды, отображающееся при запуске команды
            // с опцией "--help"
            ->setHelp('Парсинг новостей с главной страницы сайта rbc.ru')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        $client = HttpClient::create();
        $response = $client->request('GET', 'https://www.rbc.ru/');

        $statusCode = $response->getStatusCode();
        // $statusCode = 200

        $contentType = $response->getHeaders()['content-type'][0];
        // $contentType = 'application/json'

        $content = $response->getContent();

        $crawler = new Crawler($content);
        $feeds = $crawler->filter('.main__feed');

        foreach ($feeds as $feed) {
            $uri = $feed->filter('a')->getUri();
            $output->writeln($uri);
        }



        // $content = '{"id":521583, "name":"symfony-docs", ...}'





        // этот метод должен вернуть целое число с "кодом завершения"
        // команды. Вы также можете использовать это константы, чтобы сделать код более читаемым

        // вернуть это, если при выполнении команды не было проблем
        // (равноценно возвращению int(0))
        return Command::SUCCESS;

        // или вернуть это, если во время выполнения возникла ошибка
        // (равноценно возвращению int(1))
        // return Command::FAILURE;

        // или вернуть это, чтобы указать на неправильное использование команды, например, невалидные опции
        // или отсутствующие аргументы (равноценно возвращению int(2))
        // return Command::INVALID
    }
}