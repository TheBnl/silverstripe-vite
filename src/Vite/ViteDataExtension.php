<?php

namespace Vite\Vite;

use SilverStripe\ORM\DataExtension;

class ViteDataExtension extends DataExtension
{
    public function getVite() : ViteHelper
    {
        return ViteHelper::create();
    }
}
