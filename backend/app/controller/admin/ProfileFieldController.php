<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\model\ProfileField;
use app\support\EmployeeProfile;
use app\support\Present;
use support\Request;
use support\Response;

class ProfileFieldController
{
    public function index(Request $request): Response
    {
        $list = [];
        foreach (ProfileField::active() as $field) {
            $list[] = Present::field($field);
        }
        return ok($list);
    }

    public function store(Request $request): Response
    {
        ProfileField::ensureBuiltins();
        if (ProfileField::query()->count() >= 40) {
            throw new ApiException('员工字段数量过多');
        }
        $data = EmployeeProfile::definition((array) $request->post());
        if (ProfileField::where('field_key', $data['field_key'])->exists()) {
            throw new ApiException('字段标识已存在');
        }
        $field = ProfileField::create($data + ['builtin' => 0]);
        return ok(Present::field($field), '字段已添加');
    }

    public function update(Request $request, string $id): Response
    {
        $field = $this->find($id);
        $data = EmployeeProfile::definition((array) $request->post(), $field);
        $field->fill($data);
        $field->save();
        return ok(Present::field($field), '字段已保存');
    }

    public function destroy(Request $request, string $id): Response
    {
        $field = $this->find($id);
        if ($field->isBuiltin()) {
            throw new ApiException('基础字段不能删除');
        }
        $field->delete();
        return ok(null, '字段已删除');
    }

    private function find(string $id): ProfileField
    {
        $field = ProfileField::find((int) $id);
        if (!$field) {
            throw new ApiException('字段不存在', 404);
        }
        return $field;
    }
}
