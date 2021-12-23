<?php

namespace App\Parser;

class RbkTopParser extends AbstractParser
{
    protected $startUrl = 'https://www.rbc.ru/';

    protected $newsListSelector = '.main__feed a';
    protected $promoSelectors = ['.article__text__overview', '.article__text p'];
    protected $imageUrlSelectors = ['.article__main-image__wrap img', '.fotorama img', 'img.g-image'];
}