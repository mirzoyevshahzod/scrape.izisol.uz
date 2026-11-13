<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mintrans'da ma'lumoti topilgan avtoraqam va o'sha litsenziyalar
 * (1-bosqichda qayta so'ramaslik uchun).
 */
class MintransFound extends Model
{
    protected $table = 'mintrans_found';

    protected $fillable = ['plate', 'licenses', 'job_run_id', 'checks', 'checked_at'];

    protected function casts(): array
    {
        return ['licenses' => 'array', 'checked_at' => 'datetime'];
    }

    public static function record(string $plate, array $licenses, ?int $jobRunId = null): void
    {
        $row = static::firstOrNew(['plate' => $plate]);
        $row->fill([
            'licenses' => array_values($licenses),
            'job_run_id' => $jobRunId,
            'checked_at' => now(),
            'checks' => $row->exists ? $row->checks + 1 : 1,
        ])->save();
    }

    /**
     * Berilgan raqamlardan bazada borlari.
     *
     * @param  list<string>  $plates
     * @return array<string,list<array>> raqam => litsenziyalar
     */
    public static function licensesFor(array $plates): array
    {
        $result = [];

        foreach (array_chunk($plates, 1000) as $chunk) {
            foreach (static::whereIn('plate', $chunk)->get(['plate', 'licenses']) as $row) {
                $result[$row->plate] = $row->licenses;
            }
        }

        return $result;
    }
}
