<?php

declare(strict_types=1);

namespace App\Console\Commands\Source;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;

class SyncUsersSource extends Command
{
    protected $signature = 'app:sync-users-source';

    protected $description = 'Sincroniza todos os usuários para garantir que possuem uma fonte padrão e atualiza as despesas existentes para usar essa fonte.';

    public function handle(): int
    {
        $this->info('Iniciando sincronização de fontes dos usuários...');

        DB::table('users')->get()->each(function (stdClass $user): void {
            if (! isset($user->id) || (! is_int($user->id) && ! is_string($user->id))) {
                return;
            }

            $userId = $user->id;

            $this->line(sprintf('Processando usuário ID %s...', $userId));

            $sourceId = DB::table('sources')
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->value('id');

            if (! $sourceId) {
                $sourceId = DB::table('sources')->insertGetId([
                    'user_id' => $userId,
                    'name' => 'Principal',
                    'color' => '#4F46E5',
                    'is_default' => true,
                    'allow_negative' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->info(sprintf('Fonte padrão criada (ID %d).', $sourceId));
            } else {
                if (is_int($sourceId) || is_string($sourceId)) {
                    $this->line(sprintf('Fonte padrão já existe (ID %s).', $sourceId));
                }
            }

            $updated = DB::table('expenses')
                ->where('user_id', $userId)
                ->update(['source_id' => $sourceId]);

            $this->line('Despesas atualizadas: ' . $updated);
        });

        $this->info('Sincronização concluída com sucesso.');

        return self::SUCCESS;
    }
}
