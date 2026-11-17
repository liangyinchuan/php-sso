<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class ProfileField extends Model
{
    protected $table = 'profile_fields';
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'builtin' => 'boolean',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    public const BUILTINS = [
        ['field_key' => 'department', 'label' => '部门', 'sort' => 10],
        ['field_key' => 'title', 'label' => '职位', 'sort' => 20],
        ['field_key' => 'employee_no', 'label' => '工号', 'sort' => 30],
    ];

    private static bool $ready = false;

    public static function builtinKeys(): array
    {
        return array_column(self::BUILTINS, 'field_key');
    }

    public static function ensureBuiltins(): void
    {
        if (self::$ready) {
            return;
        }
        foreach (self::BUILTINS as $item) {
            if (self::where('field_key', $item['field_key'])->exists()) {
                continue;
            }
            self::create([
                'field_key' => $item['field_key'],
                'label' => $item['label'],
                'field_type' => 'text',
                'required' => 0,
                'options' => [],
                'sort' => $item['sort'],
                'builtin' => 1,
                'status' => 1,
            ]);
        }
        self::$ready = true;
    }

    public static function active()
    {
        self::ensureBuiltins();
        return self::query()->where('status', 1)->orderBy('sort')->orderBy('id')->get();
    }

    public function isBuiltin(): bool
    {
        return (bool) $this->builtin || in_array((string) $this->field_key, self::builtinKeys(), true);
    }
}
