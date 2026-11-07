<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Admin switches such as auto_publish (FR-54). */
class Setting extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    protected $table = 'ms_settings';

    protected $fillable = ['name', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        $row = static::find($name);

        return $row ? $row->value : $default;
    }

    public static function put(string $name, mixed $value, ?int $by = null): void
    {
        static::updateOrCreate(['name' => $name], ['value' => $value, 'updated_by' => $by]);
    }
}
