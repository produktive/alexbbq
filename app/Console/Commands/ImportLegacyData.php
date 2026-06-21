<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        $user = User::first();

        if (! $user) {
            $this->error('No users exist in the new database. Create a user first.');

            return self::FAILURE;
        }

        /**
         * -------------------------
         * SMOKERS
         * -------------------------
         */
        $this->info('Importing smokers...');

        foreach ($old->table('smokers')->get() as $smoker) {
            Smoker::unguarded(function () use ($smoker) {
                Smoker::updateOrCreate(
                    ['id' => $smoker->id],
                    [
                        'name' => $smoker->desc,
                    ]
                );
            });
        }

        /**
         * -------------------------
         * COOKS + ID MAP
         * -------------------------
         */
        $this->info('Importing cooks...');

        $cookIdMap = [];

        foreach ($old->table('cooks')->orderBy('id')->get() as $cook) {
            $newCook = Cook::unguarded(function () use ($cook, $user) {
                return Cook::create([
                    'id' => $cook->id,
                    'smoker_id' => $cook->smoker,
                    'user_id' => $user->id,
                    'title' => 'Imported Cook #'.$cook->id,
                    'description' => $cook->note,
                    'created_at' => $cook->start,
                    'updated_at' => now(),
                ]);
            });

            $cookIdMap[$cook->id] = true;
        }

        /**
         * -------------------------
         * READINGS
         * -------------------------
         */
        $this->info('Importing readings...');

        $old->table('readings')
            ->orderBy('time')
            ->chunk(1000, function ($rows) use ($cookIdMap) {

                $insert = [];

                foreach ($rows as $reading) {

                    // skip orphan cook references
                    if (! isset($cookIdMap[$reading->cookid])) {
                        continue;
                    }

                    $insert[] = [
                        'cook_id' => $reading->cookid,
                        'time' => $reading->time,
                        'probe_food' => $reading->probe1,
                        'probe_bbq' => $reading->probe2,
                    ];
                }

                if (! empty($insert)) {
                    Reading::insert($insert);
                }
            });

        $this->info('Import complete.');

        return self::SUCCESS;
    }
}
