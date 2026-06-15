# Email Campaign Filament

Filament admin UI layer for `lalalili/email-campaign`.

`lalalili/email-campaign-filament` registers Filament resources for campaign management and SMTP profile administration.

## Requirements

- PHP 8.2+
- Laravel 11 / 12 / 13
- Filament 4 / 5
- `lalalili/audience-core`
- `lalalili/email-campaign`
- `lalalili/survey-core`

The current aitehub host uses Laravel 13 and Filament 5.

## Installation

```bash
composer require lalalili/email-campaign-filament
php artisan vendor:publish --tag=email-campaign-filament-config
```

For GitHub installs before a Packagist release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lalalili/audience-core.git"},
        {"type": "vcs", "url": "https://github.com/lalalili/email-campaign.git"},
        {"type": "vcs", "url": "https://github.com/lalalili/email-campaign-filament.git"}
    ]
}
```

## Enable Plugin

Register the plugin in a Filament panel provider:

```php
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentPlugin;

$panel->plugins([
    EmailCampaignFilamentPlugin::make(),
]);
```

## Configuration

`config/email-campaign-filament.php` controls navigation groups and sort order:

- `navigation_group`
- `navigation_sort`
- `smtp_navigation_group`
- `smtp_navigation_sort`

## Resources

- `EmailCampaignResource`
- `EmailSmtpProfileResource`

Campaign pages include campaign creation, view/edit flows, delivery relation management, recipient relation management, scheduling actions, and dispatch actions.

## Boundaries

- This package owns Filament resources only.
- Campaign models, jobs, events, tracking routes, and delivery logic live in `lalalili/email-campaign`.
- Host applications own panel registration, authorization policy decisions, and any custom navigation grouping.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
