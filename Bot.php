<?php
//error_reporting(E_ALL);
//ini_set('error_log', __DIR__ . '/log.txt');
//error_log('Запись в лог', 0);

header('Content-type: text/html; charset=utf-8');
//$rootPath = $_SERVER['DOCUMENT_ROOT'];
require_once('Moduls/Buttons.php');
require_once('Moduls/MySQL.php');

$bot = new Bot();
$bot->init('php://input');

class Bot
{

    private $botToken = "5335232222:AAGhmMk9kdgwA2EJYj9ui24an8SY21WE7Fw";
    private $apiUrl = "https://api.telegram.org/bot";
    private $ADMIN = [659025951 => 'Андрей', 122815990 => 'Герман', 5170835463 => 'Хесель', 519029692 => 'Дмитрий',1927819764 => 'Итика', 2132203025=>'Кристина', 1853858051=>'Денис', 472611922=>'Фёдор', 882013448=>'Игорь'];
    private $buttons;
    private $bd;

    public function __construct()
    {
        $this->buttons = new Buttons();
        $this->bd = new MySQL('localhost', 'meetingroom', 'meetingRoom', 'meetingroom');
    }

    public function init($data_php)
    {
        $data = $this->getData($data_php);
//        $this->setFileLog($data);
        if (array_key_exists('message', $data)) {

            $chatId = $data['message']['chat']['id'];
            $message = $data['message']['text'];
            if ($this->isAdmin($chatId) === false) die($this->sendMessage($chatId, 'Доступ запрещён!'));
            if ($message == '/start') {
                $this->sendMessage($chatId, "Выберите действие", $this->buttons->selectButtons('start'));
            }
        }

        if (isset($data['callback_query'])) {

            $chatId = $data['callback_query']['from']['id'];
            $messageId = $data['callback_query']['message']['message_id'];
            $message = $data['callback_query']['data'];
            $buttonsCallback = $data['callback_query']['message']['reply_markup']['inline_keyboard'];
            $callbackId = $data['callback_query']['id'];

            if ($this->isAdmin($chatId) === false) die($this->sendMessage($chatId, 'Доступ запрещён!'));

            if ($message == 'booking') {
                $this->deleteMessage($chatId, $messageId);
                $date = strtotime('monday this week');
                $buttons = $this->getDayWeek($date);
                $this->sendMessage($chatId, 'Выберите день', $this->buttons->selectButtons('day', false, $buttons));
            }
            if ($message == 'cancel') {
                $this->deleteMessage($chatId, $messageId);
                $this->sendMessage($chatId, 'Выберите действие', $this->buttons->selectButtons('start'));
            }
            if (strpos($message, 'day') !== false) {
                $time = str_replace('day&', '', $message);
                $buttons = $this->getAllButtons($time);
                $this->editMessage($chatId, $messageId, 'Выберите время', $this->buttons->selectButtons('hours', false, $buttons));
            }
            if (strpos($message, 'hours') !== false) {
                $message = str_replace('hours&', '', $message);
                $time = $this->getTimeDay($buttonsCallback);
                $firstElement = explode('&', $message);
                $message = $firstElement[1];
                $firstElement = $firstElement[0];

                if (strpos($message, $firstElement) !== false) {
                    $message = str_replace($firstElement . "#", '', $message);
                    $selected = "$message";
                } else {
                    $selected = "$firstElement#$message";
                }
                $buttons = $this->getAllButtons($time,$selected);
                $this->editMessage($chatId, $messageId, 'Выберите время', $this->buttons->selectButtons('hours', false, $buttons));
            }
            if (strpos($message, 'book&') !== false) {
                $message = str_replace('book&', '', $message);
                $time = explode('%', $message);
                $hours = $time[1];
                $time = $time[0];
                $rez = $this->formationArrayBooking($hours, $time, $chatId);
                $rez ? $text = $this->getMessageBooking($hours,$time) : $text = 'Ошибка!';
                $this->deleteMessage($chatId, $messageId);
                $this->sendMessage($chatId, $text, $this->buttons->selectButtons('start'));
            }
            if (strpos($message, 'week') !== false || strpos($message, 'back') !== false) {
                $time = str_replace(['week&', 'back&'], '', $message);
                $buttons = $this->getDayWeek(strtotime('monday this week', $time));
                $this->editMessage($chatId, $messageId, 'Выберите день', $this->buttons->selectButtons('day', false, $buttons));
            }
            if (strpos($message, '+') !== false) {
                $message = explode('+', $message);
                $chatIdBooking = $message[1];
                $idBooking = $message[0];
                if($chatIdBooking == $chatId){
                    $this->deleteBooking($idBooking);
                    $time = $this->getTimeDay($buttonsCallback);
                    $buttons = $this->getAllButtons($time);
                    $this->editMessage($chatId, $messageId, 'Выберите время', $this->buttons->selectButtons('hours', false, $buttons));
                }else {
                    $this->answerCallbackQuery($callbackId, "Эта дата занята");
                }
            }
            if ($message == '-') {
                $this->answerCallbackQuery($callbackId, "Эта дата уже прошла");
            }
        }

    }

    // формирования сообщения при успешном бронировании
    private function getMessageBooking ($hours, $time)
    {
        $text = 'Вы забронировали время';
        $arrayTime = explode('#', $hours);
        for($i = 0; $i < count($arrayTime) - 1; $i++) {
            $start = $arrayTime[$i];
            $end = (int) $arrayTime[$i] + 1;
            $text.= "c $start по $end, ";
        }
        $text .= date('d.m.Y',$time);
        return $text;
    }

    // получаем кнопки в зависимости от условий
    private function getAllButtons ($time,$selected = false)
    {
        $booking = $this->selectBooking($time);
        $buttons = $this->getHours($booking, $selected);
        if (!empty($selected)) {
            $buttons[][] = [
                'text' => 'Забронировать',
                'callback_data' => "book&$time%$selected"
            ];
        }
        $buttons[][] = [
            'text' => 'Назад',
            'callback_data' => 'back&' . $time
        ];
        return $buttons;
    }

    // получаем time из этого дня
    private function getTimeDay ($buttonsCallback)
    {
        $lastKey = array_key_last($buttonsCallback);
        $time = str_replace('back&', '', $buttonsCallback[$lastKey][0]['callback_data']);
        return $time;
    }

    // формирования данных брони для БД
    private function formationArrayBooking($time, $dayYear, $chatId)
    {
        $flag = true;
        $arrayTime = explode('#', $time);
        $name = $this->ADMIN[$chatId];
        $dayYear = date('z', $dayYear);
        for ($i = 0; $i < count($arrayTime) - 1; $i++) {

            $rez = $this->insertBooking($chatId, $name, $arrayTime[$i], $dayYear);
            if ($rez !== true) {
                $flag = false;
            }
        }
        return $flag;
    }

    // занесение брони в БД
    private function insertBooking($chatId, $name, $time, $dayYear)
    {
        $array = [
            'telegram' => $chatId,
            'name' => $name,
            'time' => $time,
            'dayYear' => $dayYear
        ];
        $rez = $this->bd->SQL_Insert($array, 'booking');
        return $rez;
    }

    // удаление брони из БД
    private function deleteBooking($id)
    {
        $rez = $this->bd->SQL_Delete('booking', "id = $id");
        return $rez;
    }

    // получение броней из БД
    private function selectBooking($dayYear)
    {
        $dayYear = date('z', $dayYear);
        $array = ['name', 'time','telegram','id'];
        $rez = $this->bd->SQL_Select($array, 'booking', "dayYear = $dayYear");
        for ($i = 0; $i < count($rez); $i++) {
            $arrayRez[$rez[$i]['time']]['name'] = $rez[$i]['name'];
            $arrayRez[$rez[$i]['time']]['telegram'] = $rez[$i]['telegram'];
            $arrayRez[$rez[$i]['time']]['id'] = $rez[$i]['id'];
        }
        return isset($arrayRez) ? $arrayRez : [];
    }

    // получение часов в виде кнопок
    private function getHours($booking, $selected = false)
    {
        if ($selected) {
            $arrHours = explode("#", $selected);
        } else {
            $arrHours = [];
        }
        $j = 0;
        for ($i = 10; $i <= 17; $i++) {

            if (in_array($i, $arrHours)) {
                $flag = "✅";
            } else {
                $flag = '';
            }

            if (array_key_exists($i, $booking)) {
                $buttons[$j][] = [
                    'text' =>  $booking[$i]['name']. " " ."$i - " . $i + 1 ,
                    'callback_data' =>$booking[$i]['id']."+".$booking[$i]['telegram']
                ];
            } else {
                $buttons[$j][] = [
                    'text' => "$flag $i - " . $i + 1,
                    'callback_data' => "hours&$i&$selected"
                ];
            }
            if ($j == 3) {
                $j = 0;
            } else {
                $j++;
            }
        }
        return $buttons;
    }

    // получение дней недели в виде кнопок
    private function getDayWeek($date)
    {
        $timeThisDay = strtotime(date('Y-m-d'));
        $days = array(1 => 'Пн', 'Вт', 'Ср', 'Чт', 'Пт');
        for ($i = 1; $i < 6; $i++) {
            if($timeThisDay > $date) {
                $text =  "❌ " . $days[date('N', $date)] . " " . date('d.m', $date);
                $callbackData = '-';
            }else{
                $text = $days[date('N', $date)] . " " . date('d.m', $date);
                $callbackData = "day&" . $date;
            }
            $buttonsDay[0][] = [
                'text' => $text,
                'callback_data' => $callbackData
            ];
            $date = strtotime('+1 day', $date);
        }
        $buttonsDay[1][] = [
            'text' => 'Прошлая неделя',
            'callback_data' => "week&" . strtotime('monday previous week', $date)
        ];
        $buttonsDay[1][] = [
            'text' => 'Следующая неделя',
            'callback_data' => "week&" . strtotime('monday next week', $date)
        ];
        $buttonsDay[][] = [
            'text' => 'Отмена',
            'callback_data' => "cancel"
        ];
        return $buttonsDay;
    }

    // изменения сообщения
    private function editMessage($chatId, $messageId, $text, $buttons = false)
    {
        $content = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => $buttons

        ];
        // отправляем запрос на удаление
        $this->requestToTelegram($content, "editMessageText");
    }

    // удаление сообщения
    private function deleteMessage($chatId, $messageID)
    {
        $content = [
            'chat_id' => $chatId,
            'message_id' => $messageID,
        ];
        // отправляем запрос на удаление
        $this->requestToTelegram($content, "deleteMessage");
    }

    // уведомление на inline кнопку
    private function answerCallbackQuery($callbackQueryID, $text)
    {
        $content = [
            'callback_query_id' => $callbackQueryID,
            'text' => $text,
            'show_alert' => true

        ];
        // отправляем запрос на удаление
        $this->requestToTelegram($content, "answerCallbackQuery");
    }

    // проверка на админа
    private function isAdmin($chatId)
    {

        return array_key_exists($chatId, $this->ADMIN);
    }

    // функция логирования в файл
    private function setFileLog($data)
    {
        $fh = fopen(__DIR__ . '/log.txt', 'a') or die('can\'t open file');
        ((is_array($data)) || (is_object($data))) ? fwrite($fh, print_r($data, TRUE) . "\n") : fwrite($fh, $data . "\n");
        fclose($fh);
    }

    // функция отправки текстового сообщения c кнопкой или без
    private function sendMessage($chatId, $text, $buttons = false, $disable_notification = false)
    {
        $id_message = $this->requestToTelegram([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $buttons,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'disable_notification' => $disable_notification
        ], "sendMessage");
        $rezultArray = json_decode($id_message, true);
        return ($rezultArray['result']['message_id']);
    }

    /**
     * Парсим что приходит преобразуем в массив
     * @param $data
     * @return mixed
     */
    private function getData($data)
    {
        return json_decode(file_get_contents($data), TRUE);
    }

    /** Отправляем запрос в Телеграмм
     * @param $data
     * @param string $type
     * @return mixed
     */
    private function requestToTelegram($data, $type)
    {
        $result = null;

        if (is_array($data)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $this->botToken . '/' . $type);
            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $result = curl_exec($ch);
            curl_close($ch);
        }
        return $result;
    }

}

?>