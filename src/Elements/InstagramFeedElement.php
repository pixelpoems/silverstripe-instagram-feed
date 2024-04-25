<?php

namespace Pixelpoems\InstagramFeed\Elements;

use DNADesign\Elemental\Models\BaseElement;
use Instagram\Exception\InstagramDownloadException;
use Pixelpoems\InstagramFeed\Services\InstagramService;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use Yizack\InstagramFeed;

class InstagramFeedElement extends BaseElement
{
    private static string $singular_name = 'Instagram Feed';

    private static string $plural_name = 'Instagram Feeds';

    private static string $table_name = 'InstagramFeed_Element';

    private static string $description = '';

    private static string $icon = 'font-icon-block-instagram';

    private static bool $inline_editable = false;

    protected $instagramService = null;

    public function __construct($record = [], $creationType = self::CREATE_OBJECT, $queryParams = [])
    {
        parent::__construct($record, $creationType, $queryParams);
        $this->instagramService = InstagramService::create();
    }

    protected function provideBlockSchema(): array
    {
        $blockSchema = parent::provideBlockSchema();

        if($this->instagramService->checkOnErrors()) {
            $blockSchema['content'] = "ERROR: Please check the Error message within the element";
            return $blockSchema;
        }

        $feedCount = $this->getFeed()->count();

        if(!$feedCount) {
            $blockSchema['content'] = 'No Posts';
            return $blockSchema;
        }

        $blockSchema['content'] = $this->getFeed()->count() . ' Posts';
        return $blockSchema;
    }

    private static array $db = [
        'DisplayCount' => 'Int',
        'ReducedDisplay' => 'Boolean'
    ];

    private static array $defaults = [
        'DisplayCount' => 12,
        'ReducedDisplay' => false
    ];

    public function getIsVisible()
    {
        if(!$this->instagramService->hasToken()) return false;
        return $this->getFeed()->count() > 0;
    }

    public function getFeed(): ArrayList
    {
        $this->instagramService->setReducedDisplay($this->ReducedDisplay);
        return $this->instagramService->getFeed((int)$this->DisplayCount);
    }

    public function getType(): string
    {
        return _t(self::class . '.SINGULARNAME', 'Instagram Feed');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['DisplayCount', 'ReducedDisplay']);

        if($this->instagramService->hasToken()) {
            $tokenInfo = _t(self::class . '.TokenInfo', 'Token for the Instagram API is set.');

            $fields->addFieldsToTab('Root.Main', [
                LiteralField::create('FeedErrorInfo', '<p class="message">' . $tokenInfo . '</p>')
            ], 'Title');
        }

        if($this->instagramService->checkOnErrors()) {
            $fields->addFieldsToTab('Root.Main', [
                LiteralField::create('FeedErrorInfo', $this->instagramService->getErrorDescription(true))
            ], 'Title');
        }

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

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }
}
