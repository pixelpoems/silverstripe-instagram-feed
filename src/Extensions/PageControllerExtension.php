<?php

namespace Pixelpoems\InstagramFeed\Extensions;

use Pixelpoems\InstagramFeed\Services\InstagramService;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;

class PageControllerExtension extends Extension
{


    public function getFeed($reducedDisplay = true, $displayCount = 4): ArrayList
    {
        $this->instagramService = InstagramService::create('p_' . $this->owner->ID);

        $this->instagramService->setReducedDisplay($reducedDisplay);
        return $this->instagramService->getFeed((int)$displayCount);
    }

}
