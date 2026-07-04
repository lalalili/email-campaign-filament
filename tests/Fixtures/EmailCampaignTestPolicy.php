<?php

namespace Lalalili\EmailCampaignFilament\Tests\Fixtures;

use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * 授權測試用 Policy：Filament v5 預設 shouldCheckPolicyExistence，
 * 只認 Policy 方法而非 Gate::define 的 ability，故以靜態旗標控制放行結果。
 */
class EmailCampaignTestPolicy
{
    public static bool $allowView = true;

    public static bool $allowUpdate = true;

    public static function reset(): void
    {
        static::$allowView = true;
        static::$allowUpdate = true;
    }

    public function viewAny(User $user): bool
    {
        return static::$allowView;
    }

    public function view(User $user, EmailCampaign $campaign): bool
    {
        return static::$allowView;
    }

    public function update(User $user, EmailCampaign $campaign): bool
    {
        return static::$allowUpdate;
    }
}
