<?php

namespace Pixelpoems\InstagramFeed\Elements;

use DNADesign\Elemental\Models\BaseElement;
use Instagram\Exception\InstagramDownloadException;
use Pixelpoems\InstagramFeed\Services\InstagramService;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ArrayList;
use Yizack\InstagramFeed;

class InstagramFeedElement extends BaseElement
{
    private static string $singular_name = 'Instagram Feed';

    private static string $plural_name = 'Instagram Feeds';

    private static string $table_name = 'InstagramFeed_Element';

    private static string $description = '';

    private static string $icon = 'font-icon-block-instagram';

    private static bool $inline_editable = false;

    private static array $db = [
        'DisplayCount' => 'Int',
        'ReducedDisplay' => 'Boolean'
    ];

    private static array $defaults = [
        'DisplayCount' => 12,
        'ReducedDisplay' => false
    ];

    public function getFeed(): ArrayList
    {
        $service = InstagramService::create();
        $service->setReducedDisplay($this->ReducedDisplay);
        return $service->getFeed((int)$this->DisplayCount);
    }

    public function getType(): string
    {
        return _t(self::class . '.SINGULARNAME', 'Instagram Feed');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['DisplayCount', 'ReducedDisplay']);

        $fields->addFieldsToTab('Root.Main', [
            NumericField::create('DisplayCount', $this->fieldLabel('DisplayCount'))
                ->setDescription(_t(self::class . '.DisplayCountDescription', 'Count of Posts that will be displayed (Max. 25).')),
            CompositeField::create(
                CheckboxField::create('ReducedDisplay', $this->fieldLabel('ReducedDisplay'))
                    ->setDescription(_t(self::class . '.ReducedDisplayDescription', 'For all posts, only the first image or the thumbnail image (for videos) is displayed.'))
            )->setTitle(_t(self::class . '.Display', 'Display'))
        ]);

        return $fields;
    }
}
