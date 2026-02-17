<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class AppExtension
{
    #[AsTwigFilter('sum')]
    public function sumFilter(array $array): float|int
    {
        return array_sum($array);
    }
}
