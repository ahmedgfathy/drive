<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Folder;
use App\Models\Share;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use App\Policies\SharePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        File::class => FilePolicy::class,
        Folder::class => FolderPolicy::class,
        Share::class => SharePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
