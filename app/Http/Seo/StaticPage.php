<?php

declare(strict_types=1);

namespace App\Http\Seo;

enum StaticPage: string
{
    case Landing = 'home';
    case Terms = 'legal.terms';
    case Privacy = 'legal.privacy';
    case Cookies = 'legal.cookies';

    public function routeName(): string
    {
        return $this->value;
    }

    public function titleKey(): string
    {
        return 'seo.'.$this->value.'.title';
    }

    public function descriptionKey(): string
    {
        return 'seo.'.$this->value.'.description';
    }
}
