<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WipeDemoData extends Command
{
    protected $signature = 'app:wipe-demo
                            {--force : Skip the confirmation prompt}
                            {--keep-super-admin : Preserve super admin accounts (default: true)}
                            {--keep-config : Preserve plans, roles, permissions (default: true)}
                            {--purge-photos : Also delete meter reading photos from storage}
                            {--dry-run : Show what would be wiped without doing it}';

    protected $description = 'Wipe demo business data (clients, meters, readings, invoices, payments, vendors) while keeping platform config and super admins.';

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Water Billing — Demo Data Wiper');
        $this->newLine();

        // ── Show what we're about to do ─────────────────────────────
        $counts = $this->collectCounts();

        $this->components->twoColumnDetail('<fg=yellow>About to wipe</>', '');
        foreach ($counts['wipe'] as $table => $count) {
            $this->components->twoColumnDetail("  {$table}", (string) $count);
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Will keep</>', '');
        foreach ($counts['keep'] as $table => $count) {
            $this->components->twoColumnDetail("  {$table}", (string) $count);
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run — no changes made.');
            return self::SUCCESS;
        }

        // ── Confirm ─────────────────────────────────────────────────
        if (! $this->option('force')) {
            $this->newLine();
            if (! $this->confirm('This cannot be undone. Continue?', false)) {
                $this->components->warn('Aborted.');
                return self::FAILURE;
            }
        }

        // ── Execute ─────────────────────────────────────────────────
        $this->newLine();
        $this->components->info('Wiping…');

        DB::transaction(function () {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($this->wipeTables() as $table) {
                DB::table($table)->truncate();
                $this->components->task("Truncated {$table}");
            }

            // Users and vendors need scoped deletes, not truncate,
            // so the super admin survives.
            $this->deleteScopedUsers();
            DB::table('vendors')->delete();
            $this->components->task('Deleted vendor-owned users + vendors');

            // Clean orphaned role assignments
            $this->cleanOrphanRoleAssignments();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        // ── Optional: purge photos ─────────────────────────────────
        if ($this->option('purge-photos')) {
            $deleted = $this->purgeReadingPhotos();
            $this->components->task("Deleted {$deleted} reading photo(s) from storage");
        }

        $this->newLine();
        $this->components->info('Demo data wiped.');
        $this->components->bulletList([
            'Super admin account(s) preserved.',
            'Plans, roles, and permissions preserved.',
            'Run `php artisan db:seed` if you need to re-seed the demo vendor.',
        ]);

        return self::SUCCESS;
    }

    /**
     * Tables that are safe to TRUNCATE — pure business data.
     */
    protected function wipeTables(): array
    {
        return [
            'payments',
            'invoice_items',
            'invoices',
            'readings',
            'meters',
            'tariffs',
            'subscription_invoices',
            'subscriptions',
            'audit_logs',

            // Framework bookkeeping
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ];
    }

    /**
     * Delete only vendor-owned users — never super admins.
     */
    protected function deleteScopedUsers(): void
    {
        if ($this->option('keep-super-admin')) {
            DB::table('users')->whereNotNull('vendor_id')->delete();
        } else {
            DB::table('users')->delete();
        }
    }

    /**
     * Detach role/permission rows for users that no longer exist.
     */
    protected function cleanOrphanRoleAssignments(): void
    {
        $userIds = DB::table('users')->pluck('id');

        DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->whereNotIn('model_id', $userIds)
            ->delete();

        DB::table('model_has_permissions')
            ->where('model_type', \App\Models\User::class)
            ->whereNotIn('model_id', $userIds)
            ->delete();
    }

    /**
     * Remove reading photos from storage. Returns the deleted count.
     */
    protected function purgeReadingPhotos(): int
    {
        $disk = Storage::disk('public');
        $files = $disk->files('meter-readings');

        foreach ($files as $file) {
            $disk->delete($file);
        }

        return count($files);
    }

    /**
     * Counts for the pre-flight display.
     */
    protected function collectCounts(): array
    {
        $wipe = [];
        foreach ($this->wipeTables() as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $wipe[$table] = DB::table($table)->count();
            }
        }

        $users    = DB::table('users');
        $vendors  = DB::table('vendors');

        if ($this->option('keep-super-admin')) {
            $wipe['users (non-super-admin)'] = (clone $users)->whereNotNull('vendor_id')->count();
        } else {
            $wipe['users'] = (clone $users)->count();
        }

        $wipe['vendors'] = (clone $vendors)->count();

        $keep = [
            'plans'        => DB::table('plans')->count(),
            'clients'        => DB::table('clients')->count(),
            'roles'        => DB::table('roles')->count(),
            'permissions'  => DB::table('permissions')->count(),
        ];

        if ($this->option('keep-super-admin')) {
            $keep['super admin users'] = (clone $users)->whereNull('vendor_id')->count();
        }

        return ['wipe' => $wipe, 'keep' => $keep];
    }
}
