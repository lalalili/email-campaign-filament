<?php

namespace Lalalili\EmailCampaignFilament\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\AudienceCore\AudienceCoreServiceProvider;
use Lalalili\EmailCampaign\EmailCampaignServiceProvider;
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentServiceProvider;
use Lalalili\PackageTestingSupport\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AudienceCoreServiceProvider::class,
            EmailCampaignServiceProvider::class,
            EmailCampaignFilamentServiceProvider::class,
        ];
    }

    /**
     * 活動表單會讀取名單欄位（可用變數清單、個性化對應），需要 audience-core 的資料表。
     */
    protected function defineDatabaseMigrations(): void
    {
        // AudienceList 有 LogsActivity，寫入時需要這張表。
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../../audience-core/database/migrations');
    }
}
