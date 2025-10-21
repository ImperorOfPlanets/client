<?php
namespace App\Helpers\Editor;

use \App\Models\Propertys;

class Editor
{
    //Объект
    public $object = null;

    //Все поля
    public 	$fields = null;

    //Роли пользователя
    public $roles = null;

    public function __construct($object = null)
    {
        $this->object = $object;
        $this->fields = $this->object->allProperties();
        $this->roles = $this->getRoles();
        foreach ($this->fields as $key=>$property) { // Используем ссылку (&), чтобы изменять элементы массива
            $this->fields[$key] = $this->determineAccess($property);
        }
    }

    public function getRoles()
    {
        $roles = session('roles', []);
        
        // Гарантируем, что возвращается массив
        return is_array($roles) ? $roles : [];
    }

    private function determineAccess($field)
    {
        $access = $field['access'] ?? ['show' => [], 'edit' => []];
        $showRoles = $access['show'] ?? [];
        $editRoles = $access['edit'] ?? [];

        $field['isShow'] = false;
        $field['isEdit'] = false;

        foreach ($this->roles as $roleId) {
            // Проверка прав на редактирование (включает просмотр)
            if (isset($editRoles[$roleId]) && $editRoles[$roleId]) {
                $field['isEdit'] = true;
                $field['isShow'] = true;
                break; // Нет необходимости проверять другие роли
            }

            // Проверка прав только на просмотр
            if (isset($showRoles[$roleId]) && $showRoles[$roleId]) {
                $field['isShow'] = true;
            }
        }

        // Если есть право редактирования, автоматически даем право просмотра
        if ($field['isEdit']) {
            $field['isShow'] = true;
        }

        return $field;
    }
}