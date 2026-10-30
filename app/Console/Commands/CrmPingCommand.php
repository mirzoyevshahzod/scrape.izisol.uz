<?php

namespace App\Console\Commands;

use App\Services\EgsCrmClient;
use Illuminate\Console\Command;

/**
 * EGS CRM API'si ishlayotganini va kalit to'g'riligini tekshiradi,
 * import uchun granitsalar ro'yxatini chiqaradi.
 */
class CrmPingCommand extends Command
{
    protected $signature = 'crm:ping';

    protected $description = 'EGS CRM API kaliti va ulanishni tekshiradi, granitsalar ro\'yxatini chiqaradi';

    public function handle(EgsCrmClient $crm): int
    {
        $result = $crm->ping();

        if (! $result['ok']) {
            $this->error("CRM API ishlamayapti ({$result['ms']} ms): {$result['message']}");

            return self::FAILURE;
        }

        $this->info("{$result['message']} · {$result['ms']} ms");
        $this->table(['ID', 'Granitsa', 'Qayerdan', 'Qayerga'], array_map(
            fn (array $border) => [$border['id'], $border['name'], $border['country_from'] ?? '', $border['country_to'] ?? ''],
            $crm->borders()
        ));

        return self::SUCCESS;
    }
}
