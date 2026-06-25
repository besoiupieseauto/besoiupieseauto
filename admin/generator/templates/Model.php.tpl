<?php

namespace Evasystem\Core\{{Module}};

use Evasystem\Core\AdvancedCRUD;

class {{Module}}Model
{
    public static function get{{Module}}sAll()
    {
        return AdvancedCRUD::select('{{db}}');
    }

    public static function get{{Module}}sId($id, $db = '{{db}}', $where = 'randomn_id')
    {
        return AdvancedCRUD::select($db, '*', "WHERE " . $where . " = '$id' ");
    }

    public static function createTask($taskData, $db = '{{db}}')
    {
        return AdvancedCRUD::create($db, $taskData);
    }

    public static function updateTask($taskId, $taskData)
    {
        return AdvancedCRUD::update('{{db}}', $taskData, "WHERE id = $taskId");
    }

    public static function del($taskId, $db = '{{db}}', $where = 'randomn_id')
    {
        return AdvancedCRUD::delete($db, "WHERE $where = $taskId");
    }

    public static function udape($taskId, $taskData, $db = '{{db}}')
    {
        return AdvancedCRUD::update($db, $taskData, "WHERE randomn_id = $taskId");
    }
}
