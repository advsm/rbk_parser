<?php

namespace App\Parser;

use App\Entity\News;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

use Symfony\Component\DomCrawler\Crawler;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Output\OutputInterface;

class AbstractParser
{
    /**
     * Адрес страницы с новостями, с которой начинается парсинг. Необходимо прописать адрес в классе-наследнике.
     * @var string
     */
    protected $startUrl;

    /**
     * @var string
     */
    protected $newsListSelector;

    /**
     * @var string[]
     */
    protected $promoSelectors;

    /**
     * @var string[]
     */
    protected $imageUrlSelectors;

    /**
     * HTTP клиент, который используется для запросов.
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Загружает список новостей со стартовой страницы и возвращает список. Производит логирование и обработку ошибок.
     * @param int $limit
     * @return Crawler
     * @throws ParserException
     */
    public function parseNewsList($limit = 15)
    {
        $url = $this->getStartUrl();
        $crawler = $this->loadUrl($url);

        $selector = $this->newsListSelector;
        $newsList = $crawler->filter($selector);

        $count = $newsList->count();
        $this->log("$url contains $count blocks selected with $selector");
        if ($count === 0) {
            throw new ParserException("News wasn't found by selector $selector on $url");
        }

        foreach ($newsList as $singleNewsNode) {
            $news = $this->parseSingleNews($singleNewsNode);
            $this->getEntityManager()->persist($news);

            $limit = $limit - 1;
            if (!$limit) {
                break;
            }
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Обрабатывает отдельную запись из листа новостей и возвращает готовую модель. Ведёт логи.
     *
     * @param \DOMElement $node
     * @return News
     * @throws \App\Parser\ParserException
     */
    protected function parseSingleNews(\DOMElement $node) : News
    {
        $url = $this->parseSingleNewsUrlFromNode($node);
        if (!$url) {
            throw new ParserException("Single news url wasn't found in node");
        }

        if ($news = $this->getSingleNewsFromDb($url)) {
            $this->log("News exists in databases with ID: " . $news->getId());
            return $news;
        }

        $crawler = $this->loadUrl($url);

        $news = new News();
        $news->setUrl($url);

        $name = $this->parseSingleNewsNameFromNode($node);
        $this->log("Parsing news with name: $name");
        $news->setName($name);

        $promo = $this->parsePromo($crawler);
        if (!$promo) {
            throw new ParserException("Promo text don't parsed");
        }
        $this->log("Parsed promo: $promo");
        $news->setPromo($promo);

        $imageUrl = $this->parseImageUrl($crawler);
        $this->log("Parsed image url: $imageUrl");
        $news->setImageUrl($imageUrl);

        return $news;
    }

    /**
     * Возвращает ссылку на страницу отдельной новости. Метод подходит для переопределения в классах-наследниках.
     *
     * @param \DOMElement $node
     * @return string
     */
    protected function parseSingleNewsUrlFromNode(\DomElement $node) : string
    {
        return $node->getAttribute('href');
    }

    /**
     * Возвращает название отдельной новости. Метод подходит для переопределения в классах-наследниках.
     *
     * @param \DOMElement $node
     * @return string
     */
    protected function parseSingleNewsNameFromNode(\DOMElement $node) : string
    {
        return trim($node->nodeValue);
    }

    /**
     * Парсит превью новости.
     *
     * @param Crawler $crawler
     * @return string|null
     */
    protected function parsePromo(Crawler $crawler) : string|null
    {
        $crawler = $this->filterBySelectors($crawler, $this->promoSelectors);
        if (!$crawler) {
            return null;
        }

        return trim($crawler->text());
    }

    /**
     * Парсит из новости ссылку на картинку.
     *
     * @param Crawler $crawler
     * @return string|null
     */
    protected function parseImageUrl(Crawler $crawler) : string|null
    {
        $crawler = $this->filterBySelectors($crawler, $this->imageUrlSelectors);
        if (!$crawler) {
            $this->log("Image url node don't found");
            return null;
        }

        return trim($crawler->attr('src'));
    }

    /**
     * Возвращает первый элемент, подходящий под переданный массив селекторов.
     *
     * @param Crawler $crawler
     * @param $selectors
     * @return Crawler|null
     */
    protected function filterBySelectors(Crawler $crawler, $selectors) : Crawler|null
    {
        foreach ($selectors as $selector) {
            $nodes = $crawler->filter($selector);
            $count = $nodes->count();
            $this->log("Found $count blocks with selector $selector");
            if ($count === 0) {
                continue;
            }

            return $nodes;
        }

        return null;
    }

    /**
     * Проверяет, была ли новость спарсена ранее.
     *
     * @param string $url
     * @return News|null
     */
    protected function getSingleNewsFromDb(string $url) : News|null
    {
        $this->log("Search is url already parsed: $url");

        $news = $this->entityManager
            ->getRepository(News::class)
            ->findOneBy(['url' => $url]);

        return $news;
    }

    /**
     * Изолирует обработку ошибок HTTP клиента, добавляет логирование запросов.
     * @param string $url
     * @return Crawler
     * @throws ParserException
     */
    protected function loadUrl(string $url) : Crawler
    {
        $this->log("Loading url: $url");

        try {
            $response = $this->getHttpClient()->request('GET', $url);
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $message = "URL $url unavailable, expected status code 200, given: $statusCode";
                $this->log($message);
                throw new ParserException($message);
            }

            $content = $response->getContent();

        } catch (ExceptionInterface $e) {
            $message = sprintf("Exception handler via loading %s. %s : %s", $url, get_class($e), $e->getMessage());
            $this->log($message);
            throw new ParserException($message);
        }

        $this->log("Loading successful, content length: " . strlen($content));
        return new Crawler($content);
    }

    /**
     * @return HttpClientInterface
     */
    protected function getHttpClient()
    {
        if (!$this->client) {
            $this->client = HttpClient::create();
        }

        return $this->client;
    }

    /**
     * Проверяет валидность и возвращает URL, с которого начинается парсинг.
     *
     * @return string
     * @throws \App\Parser\ParserException
     */
    protected function getStartUrl()
    {
        if (!$this->startUrl) {
            throw new ParserException("Start url undefined");
        }

        return $this->startUrl;
    }

    /**
     * @param string $message
     * @return void
     */
    protected function log(string $message)
    {
        $this->output->writeln($message);
    }

    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @param EntityManagerInterface $manager
     * @return $this
     */
    public function setEntityManager(EntityManagerInterface $manager) : self
    {
        $this->entityManager = $manager;
        return $this;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }
}