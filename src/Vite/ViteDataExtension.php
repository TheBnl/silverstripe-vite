<?php

namespace ViteHelper\Vite;

use SilverStripe\Core\Extension;

class ViteExtension extends Extension
{
    public function onAfterInit()
    {
        $vite = $this->getVite();
        $vite->initVite();
    }

    public function getVite() : ViteHelper
    {
        return ViteHelper::create();
    }
}
