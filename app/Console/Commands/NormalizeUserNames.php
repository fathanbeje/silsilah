<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class NormalizeUserNames extends Command
{
    protected $signature = 'users:normalize-uppercase';

    protected $description = 'Normalize all user names and nicknames into uppercase';

    public function handle()
    {
        $updated = 0;

        User::query()
            ->select(['id', 'name', 'nickname'])
            ->orderBy('id')
            ->chunk(100, function ($users) use (&$updated) {
                foreach ($users as $user) {
                    $name = User::normalizeUppercase($user->name);
                    $nickname = User::normalizeUppercase($user->nickname);

                    if ($name === $user->name && $nickname === $user->nickname) {
                        continue;
                    }

                    $user->timestamps = false;
                    $user->forceFill([
                        'name' => $name,
                        'nickname' => $nickname,
                    ])->save();

                    $updated++;
                }
            });

        $this->info("Uppercase normalization complete. Updated {$updated} users.");

        return 0;
    }
}
