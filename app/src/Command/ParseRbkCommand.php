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
        $site = 'https://www.rbc.ru/';
        $output->writeln("Start parsing $site");
        $client = HttpClient::create();
        $response = $client->request('GET', $site);
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $output->writeln("Site unavailable, expected status code 200, given: $statusCode");
            return Command::FAILURE;
        }

        $content = $response->getContent();
        $output->writeln("Response given, start analysing content body");

        $crawler = new Crawler($content);
        $selector = '.main__feed a';
        $feeds = $crawler->filter($selector);

        $count = $feeds->count();
        $output->writeln("$site body contains $count blocks selected with $selector");
        if ($count === 0) {
            return Command::FAILURE;
        }

        foreach ($feeds as $feed) {

            $url = $feed->getAttribute('href');
            $output->writeln("Search is url already parsed: $url");

            $news = $this->entityManager
                ->getRepository(News::class)
                ->findOneBy(['url' => $url]);

            if ($news) {
                $output->writeln("News already exists, so skip it");
                continue;
            }

            $news = new News();
            $output->writeln("Trying to connect to $url");
            $news->setUrl($url);

            $name = trim($feed->nodeValue);
            $output->writeln("Parsing news with name: $name");
            $news->setName($name);



            $response = $client->request('GET', $url);
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $output->writeln("Site unavailable, expected status code 200, given: $statusCode");
                return Command::FAILURE;
            }

            $crawler = new Crawler($response->getContent());

            $promo = null;
            $selectors = ['.article__text__overview', '.article__text p'];
            foreach ($selectors as $selector) {
                $nodes = $crawler->filter($selector);
                $count = $nodes->count();
                $output->writeln("$url body contains $count blocks selected with $selector");
                if ($count === 0) {
                    $output->writeln('Trying next selector');
                    continue;
                }

                $promo = $nodes->text();
                $output->writeln("Promo: $promo");
                break;
            }

            if ($promo === null) {
                $output->writeln("Promo don't found");
                return COMMAND::FAILURE;
            }

            $news->setPromo($promo);


            $selectors = ['.article__main-image__wrap img', '.fotorama img', 'img.g-image'];
            $imageUrl = null;
            foreach ($selectors as $selector) {
                $nodes = $crawler->filter($selector);
                $count = $nodes->count();
                $output->writeln("$url body contains $count blocks selected with $selector");
                if ($count === 0) {
                    $output->writeln('Trying next selector');
                    continue;
                }

                $imageUrl = $nodes->attr('src');
                $output->writeln("Image url: $imageUrl");
                break;
            }

            if ($imageUrl !== null) {
                $news->setImageUrl($imageUrl);
            } else {
                $output->writeln("Image url don't found");
            }


            // сообщите Doctrine, что вы хотите (в итоге) сохранить Продукт (пока без запросов)
            $this->entityManager->persist($news);
        }

        $output->writeln("Nodes successfully iterated");
        $this->entityManager->flush();

        return Command::SUCCESS;

        // или вернуть это, если во время выполнения возникла ошибка
        // (равноценно возвращению int(1))
        // return Command::FAILURE;
    }
}