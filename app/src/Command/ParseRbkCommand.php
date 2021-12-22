<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;

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
        $content = $response->getContent();

        $crawler = new Crawler($content);
        $feeds = $crawler->filter('.main__feed a');

        foreach ($feeds as $feed) {
            $url = $feed->getAttribute('href');
            $response = $client->request('GET', $url);

            $crawler = new Crawler($response->getContent());
            $promo = $crawler->filter('.article__text__overview')->text();
            $imageUrl = $crawler->filter('.article__main-image__wrap img')->attr('src');

            $news = new News();
            $news->setName($feed->nodeValue);
            $news->setUrl($feed->getAttribute('href'));
            $news->setPromo($promo);
            $news->setImageUrl($imageUrl);

            // сообщите Doctrine, что вы хотите (в итоге) сохранить Продукт (пока без запросов)
            $this->entityManager->persist($news);
        }

        // действительно выполните запросы (например, запрос INSERT)
        $this->entityManager->flush();

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