<?php

namespace App\Console\Commands;

use App\Models\SiteMcpToken;
use App\Models\User;
use Illuminate\Console\Command;

class ManageMcpTokens extends Command
{
    protected $signature = 'site:mcp-token {action=create : create, list або revoke} {email? : Email адміністратора} {--name=codex : Назва підключення} {--days=90 : Термін дії, 1–365 днів} {--read-only : Лише читання} {--id= : ID токена для відкликання}';

    protected $description = 'Створити, переглянути або відкликати окремий токен MCP';

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->table(['ID', 'Адміністратор', 'Назва', 'Діє до', 'Відкликано', 'Останній запит'], SiteMcpToken::orderBy('id')->get()->map(fn ($token) => [$token->id, User::find($token->user_id)?->email, $token->name, $token->expires_at, $token->revoked_at, $token->last_used_at]));

            return self::SUCCESS;
        }
        if ($action === 'revoke') {
            $token = SiteMcpToken::find((int) $this->option('id'));
            if (! $token) {
                $this->error('Токен не знайдено. Вкажіть --id з команди list.');

                return self::FAILURE;
            }
            $token->update(['revoked_at' => now()]);
            $this->info('Токен #'.$token->id.' відкликано.');

            return self::SUCCESS;
        }
        if ($action !== 'create') {
            $this->error('Доступні дії: create, list, revoke.');

            return self::FAILURE;
        }
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->argument('email')))])->first();
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $name = trim((string) $this->option('name'));
        if (! $user?->is_admin || ! $days || $days < 1 || $days > 365 || $name === '' || mb_strlen($name) > 100) {
            $this->error('Вкажіть email чинного адміністратора, назву до 100 символів і --days від 1 до 365.');

            return self::FAILURE;
        }
        $plain = 'rv_mcp_'.bin2hex(random_bytes(32));
        $token = SiteMcpToken::create(['user_id' => $user->id, 'name' => $name, 'token_hash' => hash('sha256', $plain), 'scopes' => $this->option('read-only') ? ['content:read'] : ['content:read', 'content:write', 'content:publish', 'media:write'], 'expires_at' => now()->addDays($days)]);
        $this->info('Створено токен #'.$token->id.'. Діє до '.$token->expires_at->toIso8601String());
        $this->warn('Скопіюйте значення зараз у RIDNAVIRA_MCP_TOKEN. На сервері зберігається лише хеш; повторно показати токен неможливо.');
        $this->line($plain);

        return self::SUCCESS;
    }
}
