# email-campaign-filament

`lalalili/email-campaign` 的 Filament 後台 UI 層。以 Filament plugin 形式提供郵件活動、
收件人、SMTP profile 與投遞追蹤的管理介面。

## 需求

- `lalalili/email-campaign`、`lalalili/audience-core`、`lalalili/survey-core`
- Filament v4 或 v5

## 安裝

```bash
composer require lalalili/email-campaign-filament
php artisan vendor:publish --tag=email-campaign-filament-config
```

於 Panel provider 註冊 plugin：

```php
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(EmailCampaignFilamentPlugin::make());
}
```

## 說明

實際寄送、追蹤與排程邏輯位於 `lalalili/email-campaign`；本套件僅負責 Filament 資源、
表單與動作，並套用授權檢核。
