<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads and safely rewrites the application's .env file.
 *
 * Safety rules:
 *  - keys must look like ENV keys (A-Z, 0-9, _) and protected keys are refused;
 *  - values may not contain line breaks or NUL bytes (no injecting extra keys);
 *  - values are quoted and escaped when needed;
 *  - the current file is backed up before every write, and the write is atomic
 *    (temp file + rename), so a failure never leaves a half-written .env.
 */
class EnvEditor
{
    /** Keys that can never be changed from the UI. */
    public const PROTECTED_KEYS = ['APP_KEY', 'APP_PREVIOUS_KEYS', 'PASSKEYS_USER_HANDLE_SECRET'];

    /** How many timestamped backups to keep in storage/app/env-backups. */
    public const BACKUPS_KEPT = 10;

    public function __construct(protected ?string $path = null)
    {
        $this->path ??= app()->environmentFilePath();
    }

    /**
     * Current values, keyed by name (comments and blank lines skipped).
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        if (! is_readable($this->path)) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($this->path)) as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                $values[$m[1]] = $this->decode($m[2]);
            }
        }

        return $values;
    }

    /**
     * Write the given key => value pairs, replacing existing lines in place
     * and appending new keys at the end.
     *
     * @param  array<string, string|int|bool|null>  $changes
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function set(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        foreach ($changes as $key => $value) {
            $this->assertWritable((string) $key, $value);
        }

        if (! is_writable($this->path)) {
            throw new RuntimeException('The .env file is not writable by the web server.');
        }

        $original = (string) file_get_contents($this->path);
        $eol = str_contains($original, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\n|\r/', $original);
        $pending = $changes;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $pending)) {
                $lines[$i] = $m[1].'='.$this->encode($pending[$m[1]]);
                unset($pending[$m[1]]);
            }
        }

        // Drop trailing empty lines, append new keys, keep one final newline.
        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        foreach ($pending as $key => $value) {
            $lines[] = $key.'='.$this->encode($value);
        }

        $this->backup($original);

        $temp = $this->path.'.tmp-'.bin2hex(random_bytes(4));
        file_put_contents($temp, implode($eol, $lines).$eol, LOCK_EX);

        if (! @rename($temp, $this->path)) {
            // Windows cannot rename over an open file; fall back to an in-place locked write.
            @unlink($temp);
            file_put_contents($this->path, implode($eol, $lines).$eol, LOCK_EX);
        }
    }

    public static function isSecret(string $key): bool
    {
        return (bool) preg_match('/(PASSWORD|SECRET|_KEY$|_KEY_|TOKEN|WEBHOOK)/', $key);
    }

    protected function assertWritable(string $key, mixed $value): void
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid setting name: {$key}");
        }

        if (in_array($key, self::PROTECTED_KEYS, true)) {
            throw new InvalidArgumentException("{$key} cannot be changed from the settings page.");
        }

        if (is_string($value) && preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException("{$key} cannot contain line breaks.");
        }
    }

    protected function encode(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        // Quote and escape anything else (spaces, #, quotes, $, =, …).
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    protected function decode(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            return stripcslashes(str_replace('\\$', '$', $m[1]));
        }

        if (preg_match("/^'([^']*)'/", $raw, $m)) {
            return $m[1];
        }

        // Unquoted: strip an inline comment.
        return trim((string) preg_replace('/\s+#.*$/', '', $raw));
    }

    protected function backup(string $contents): void
    {
        $dir = storage_path('app/env-backups');
        File::ensureDirectoryExists($dir);

        file_put_contents($dir.'/.env.'.now()->format('Ymd-His').'.bak', $contents, LOCK_EX);

        $backups = collect(File::files($dir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), '.env.'))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values();

        $backups->slice(self::BACKUPS_KEPT)->each(fn ($file) => File::delete($file->getPathname()));
    }
}
