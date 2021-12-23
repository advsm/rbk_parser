<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\Persistence\ManagerRegistry;
use App\Entity\News;

class NewsController extends AbstractController
{
    #[Route('/news', name: 'news')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(News::class);
        $news = $repository->findBy(criteria: [], orderBy: ['id' => 'DESC'], limit: 15);

        return $this->render('news/index.html.twig', [
            'news' => $news,
        ]);
    }
}
