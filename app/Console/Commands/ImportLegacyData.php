<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:import-legacy-data')]
#[Description('Import legacy Alex.bbq version 1 database data')]
class ImportLegacyData extends Command
{
    public function handle(): int
    {
        $old = DB::connection('old_sqlite');

        $user = User::query()->first();

        if (! $user) {
            $this->error('No users exist in the new database. Create a user first.');

            return self::FAILURE;
        }

        $this->info('Importing smokers...');

        foreach ($old->table('smokers')->get() as $smoker) {
            Smoker::unguarded(fn () => Smoker::updateOrCreate(
                ['id' => $smoker->id],
                ['name' => $smoker->desc],
            ));
        }

        $this->info('Importing cooks...');

        $cookIds = [];

        foreach ($old->table('cooks')->orderBy('id')->get() as $cook) {
            Cook::unguarded(fn () => Cook::updateOrCreate(
                ['id' => $cook->id],
                [
                    'smoker_id' => $cook->smoker,
                    'user_id' => $user->id,
                    'title' => 'Imported Cook #'.$cook->id,
                    'description' => $cook->note,
                    'ended_at' => filled($cook->end) ? $cook->end : null,
                    'created_at' => $cook->start,
                    'updated_at' => now(),
                ],
            ));

            $cookIds[$cook->id] = true;
        }

        $this->info('Importing readings...');

        $old->table('readings')
            ->orderBy('time')
            ->chunk(1000, function ($rows) use ($cookIds): void {
                $insert = [];

                foreach ($rows as $reading) {
                    if (! isset($cookIds[$reading->cookid])) {
                        continue;
                    }

                    $insert[] = [
                        'cook_id' => $reading->cookid,
                        'time' => $reading->time,
                        'probe_food' => $reading->probe1,
                        'probe_bbq' => $reading->probe2,
                    ];
                }

                if ($insert !== []) {
                    Reading::insert($insert);
                }
            });

        $this->info('Syncing ended_at from readings where needed...');

        $updated = Cook::syncAllEndedAtFromReadings(onlyMissing: true);

        $this->info("Import complete. Synced ended_at for {$updated} cook(s).");

        return self::SUCCESS;
    }
}
