<?php

declare(strict_types=1);

namespace app\support;

use app\exception\ApiException;
use app\model\ProfileField;

class EmployeeProfile
{
    public const TYPES = ['text', 'number', 'date', 'bool', 'select', 'file'];

    public static function definition(array $input, ?ProfileField $current = null): array
    {
        $key = $current ? (string) $current->field_key : trim((string) ($input['key'] ?? ''));
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $key)) {
            throw new ApiException('字段标识需以小写字母开头，只含小写字母、数字和下划线，最长 32 位');
        }
        $reserved = ['extra', 'username', 'email', 'phone', 'password', 'nickname', 'avatar', 'status', 'roles', 'profile', 'sub', 'employee', 'id'];
        if (in_array($key, $reserved, true)) {
            throw new ApiException('该标识已保留');
        }
        if (!$current && in_array($key, ProfileField::builtinKeys(), true)) {
            throw new ApiException('该标识属于基础字段');
        }
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 40) {
            throw new ApiException('字段名称需为 1-40 个字');
        }
        $type = trim((string) ($input['type'] ?? 'text'));
        if (!in_array($type, self::TYPES, true)) {
            throw new ApiException('不支持的字段类型');
        }
        if ($current && $current->isBuiltin() && $type !== (string) $current->field_type) {
            throw new ApiException('基础字段的类型不能修改');
        }
        $options = $type === 'select' ? self::options($input['options'] ?? []) : [];
        $sort = (int) ($input['sort'] ?? 100);
        if ($sort < 0 || $sort > 9999) {
            throw new ApiException('排序需在 0 到 9999 之间');
        }
        return [
            'field_key' => $key,
            'label' => $label,
            'field_type' => $type,
            'required' => self::flag($input['required'] ?? false) ? 1 : 0,
            'options' => $options,
            'sort' => $sort,
            'status' => 1,
        ];
    }

    public static function values(array $input, iterable $fields, array $existing = []): array
    {
        $clean = [];
        $defined = [];
        foreach ($fields as $field) {
            $key = (string) $field->field_key;
            $defined[$key] = true;
            $clean[$key] = self::value($field, $input[$key] ?? null);
        }
        $kept = 0;
        foreach ($existing as $key => $value) {
            $key = (string) $key;
            if (isset($defined[$key]) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $key)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $clean[$key] = mb_substr(trim((string) $value), 0, 200);
            $kept++;
            if ($kept >= 40) {
                break;
            }
        }
        return $clean;
    }

    private static function value(ProfileField $field, mixed $raw): string
    {
        $label = (string) $field->label;
        $type = (string) $field->field_type;
        if (is_bool($raw)) {
            $raw = $raw ? '1' : '0';
        } elseif (is_array($raw) || is_object($raw)) {
            throw new ApiException($label . '格式不正确');
        } elseif ($raw === null) {
            $raw = $type === 'bool' ? '0' : '';
        } else {
            $raw = trim((string) $raw);
        }
        if ($type === 'bool') {
            return self::flag($raw) ? '1' : '0';
        }
        if ($type === 'file') {
            return ProfileFiles::canonicalize($raw, $label, (bool) $field->required);
        }
        if ($raw === '') {
            if ($field->required) {
                throw new ApiException('请填写' . $label);
            }
            return '';
        }
        if ($type === 'text') {
            if (mb_strlen($raw) > 200) {
                throw new ApiException($label . '不能超过 200 个字');
            }
            return $raw;
        }
        if ($type === 'number') {
            if (!preg_match('/^-?\d+(\.\d+)?$/', $raw) || strlen($raw) > 32) {
                throw new ApiException($label . '需为数字');
            }
            return $raw;
        }
        if ($type === 'date') {
            $date = \DateTime::createFromFormat('Y-m-d', $raw);
            if (!$date || $date->format('Y-m-d') !== $raw) {
                throw new ApiException($label . '需为日期，格式如 2026-01-31');
            }
            return $raw;
        }
        $options = array_map('strval', array_values($field->options ?: []));
        if (!in_array($raw, $options, true)) {
            throw new ApiException($label . '不在可选范围内');
        }
        return $raw;
    }

    private static function options(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            throw new ApiException('选项格式不正确');
        }
        $options = [];
        foreach ($raw as $option) {
            if (is_array($option) || is_object($option)) {
                throw new ApiException('选项格式不正确');
            }
            $option = trim((string) $option);
            if ($option === '') {
                continue;
            }
            if (mb_strlen($option) > 40) {
                throw new ApiException('单个选项不能超过 40 个字');
            }
            $options[] = $option;
        }
        $options = array_values(array_unique($options));
        if (!$options || count($options) > 30) {
            throw new ApiException('选项需为 1-30 个');
        }
        return $options;
    }

    private static function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
