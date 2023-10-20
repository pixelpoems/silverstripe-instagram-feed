# Silverstripe Instagram Feed Module
[![stability-beta](https://img.shields.io/badge/stability-beta-33bbff.svg)](https://github.com/mkenney/software-guides/blob/master/STABILITY-BADGES.md#beta)

* [Requirements](#requirements)
* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
* [External Requirement Setup](#external-requirement-setup)

## Requirements

* Silverstripe CMS >=4.0
* Silverstripe Framework >=4.0
* Versioned Admin >=1.0

External Requirements:
* Meta Developer App [(Instruction)](#meta-developer-app)
* Instagram Basic Display API [(Instruction)](#instagram-basic-display-api)

## Installation
```
composer require pixelpoems/silverstripe-instagram-feed
```

## Configuration
You can add your instagram access token within a yml config:
```yml
Pixelpoems\InstagramFeed\Services\InstagramService:
    instagram_access_token: ''
```

or you set a `.env` Variable:
```.env
INSTAGRAM_ACCESS_TOKEN=''
```

If both variables are given, the yml variable will be used.

## Usage
This module comes with a configured Instagram Feed Element (Usage with [DNA Elemental]()).
If you want to use the feed somewhere else you can include the service like this:
```php
public function getInstagramFeed(): ArrayList
{
    $service = InstagramService::create();

    // To use the reduced display which only renders images (no carousels or videos)
    $service->setReducedDisplay(true);

    return $service->getFeed($limit = 12);
}
```

## The Instagram Post
| Key           | Description                                                                                                              |
|---------------|--------------------------------------------------------------------------------------------------------------------------|
| `ID`          | ID of the Instagram Post                                                                                                 |
| `MediaType`   | Type of Instagram Media<br/><br/>`IMAGE`<br/>`CAROUSEL_ALBUM`<br/>`VIDEO`                                                |
| `Link`        | Permalink for the Instagram Post                                                                                         |
| `ProfileLink` | Link of the Instagram Profile                                                                                            |
| `Username`    | Username of the instagram profile                                                                                        |
| `MediaSrc`    | Source of the Media (Image Source for `IMAGE`, First Image Source for `CAROUSEL_ALBUM` and Thumbnail Source for `VIDEO`) |
| `VideoSrc`    | Video Source - only available for Media Type `VIDEO`                                                                     |
| `Caption`     | Caption of the Instagram Post                                                                                            |
| `Timestamp`   | Timestamp of the Instagram Post                                                                                          |
| `Children`    | Contains the information of the child elements [`ID`, `MediaSrc`]- only available for Media Type `CAROUSEL_ALBUM`        |

## External Requirement Setup
### Meta Developer App
To use the **Instagram API** you have to create a **Meta App** first.
1. Go to https://developers.facebook.com/apps/create/ and select Type "None".
2. Provide your App details (Name and contact mail).
3. Select "Instagram Basic Display" from the product list.
4. Scroll down until you see an alert and click on Settings to update your App settings.
5. Select the platform Website.
6. Enter your Site URL.

### Instagram Basic Display API
https://developers.facebook.com/docs/instagram-basic-display-api
1. Open "Instagram Basic Display > Basic Display" on the product overview (left menu).
2. Click on "Create New App".
3. Fill OAuth Redirect, Deauthorize Callback and Data Deletion Request URL with your site URL.
4. Add Instagram testers.
5. Enter your Instagram username and select your profile.
6. Go to your Instagram account settings page > App and Websites > Tester invites, accept the invite.
7. Back to Products > Instagram > Basic Display > User Token Generator, you Instagram account should appear in the list, then click Generate Token button for authorize and generate long-lived access token for Instagram.
8. Login and authorize the App.
9. Copy generated Token.
10. Use this token within your yml config or your .env configuration (see [Configuration](#configuration)).


## Reporting Issues
Please [create an issue](https://github.com/pixelpoems/silverstripe-instagram-feed/issues) for any bugs you've found, or
features you're missing.
