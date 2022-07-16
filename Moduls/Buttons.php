<?php

class Buttons
{
    public function selectButtons($name,$callback = false,$buttons = false)
    {
        switch ($name) {
            case 'start':
                $buttons = $this->getInlineKeyBoard([
                    [
                        ["text" => "Забронировать время", "callback_data" => "booking"]
                    ]
                ]);
                break;
            case 'hours':
            case 'day':
                $buttons = $this->getInlineKeyBoard($buttons);
                break;
        }
        return $buttons;
    }

    //клавиатура
    private
    function getKeyBoard($data, $one_time_keyboard = false)
    {
        $keyboard = array(
            "keyboard" => $data,
            "one_time_keyboard" => $one_time_keyboard,
            "resize_keyboard" => true
        );
        return json_encode($keyboard);
    }

    //inline клавиатура
    private
    function getInlineKeyBoard($data)
    {
        $keyboard = array(
            "inline_keyboard" => $data,
            "one_time_keyboard" => false,
            "resize_keyboard" => true
        );
        return json_encode($keyboard);
    }
    //удаление клавиатуры
    private
    function removeKeyBoard()
    {
        $keyboard = array(
            "remove_keyboard" => true
        );
        return json_encode($keyboard);
    }
}