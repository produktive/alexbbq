<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Support\RichEditorDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:normalize-cook-descriptions {--dry-run : Show what would change without updating rows} {--force : Update rows without prompting} {--id= : Only normalize a single cook ID}')]
#[Description('Convert legacy cook descriptions into RichEditor-compatible HTML')]
class NormalizeCookDescriptions extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cookId = $this->option('id');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Normalize all cook descriptions for the RichEditor? This should only be run once.')) {
            $this->warn('No changes made.');

            return self::SUCCESS;
        }

        $query = Cook::query()
            ->whereNotNull('description')
            ->orderBy('id');

        if (filled($cookId)) {
            $query->whereKey($cookId);
        }

        $changed = 0;
        $unchanged = 0;
        $cleared = 0;
        $examples = [];

        $query->chunkById(200, function ($cooks) use ($dryRun, &$changed, &$unchanged, &$cleared, &$examples) {
            foreach ($cooks as $cook) {
                $original = $cook->getRawOriginal('description');
                $sanitized = RichEditorDocument::sanitizeHtml($original);

                if ($sanitized === $original) {
                    $unchanged++;

                    continue;
                }

                if (count($examples) < 8) {
                    $examples[] = [
                        $cook->id,
                        $this->summarize($original),
                        $this->summarize($sanitized),
                    ];
                }

                if ($sanitized === null) {
                    $cleared++;
                }

                if (! $dryRun) {
                    $cook->forceFill(['description' => $sanitized])->saveQuietly();
                }

                $changed++;
            }
        });

        if ($examples !== []) {
            $this->line('Description examples:');
            $this->table(['ID', 'Before', 'After'], $examples);
        }

        $this->info($dryRun
            ? "Would update {$changed} descriptions ({$cleared} cleared, {$unchanged} unchanged)."
            : "Updated {$changed} descriptions ({$cleared} cleared, {$unchanged} unchanged).");

        return self::SUCCESS;
    }

    private function summarize(?string $value): string
    {
        if ($value === null) {
            return '(null)';
        }

        $value = html_entity_decode(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if ($value === '') {
            return '(empty)';
        }

        return str($value)->limit(70)->value();
    }
}
