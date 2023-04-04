<?php

namespace App\Services\TelegramBot;

use App\Models\Birthday;
use App\Models\MessageId;
use App\Models\Users;
use DateTime;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Exception;
use Illuminate\Support\Facades\Log;

class TelegramBirthDayService extends AbstractTelegramBotService
{
    /**
     * @param object $callback_query
     * @return void
     */
    protected function readAction(object $callback_query): void
    {
        try {
            $from = (object)$callback_query->from;
            list($action, $value) = explode(':', $callback_query->data);
        } catch (Exception $exception) {
            Log::error("Error while trying to read user action " . $exception->getMessage());
        }
    }

    protected function readUserAnswer($telegramMessageObject): void
    {
        try {
            $from = (object)$telegramMessageObject->from;
            $entities = (object)$telegramMessageObject->entities;
            $chat = (object)$telegramMessageObject->chat;
            $text = (object)$telegramMessageObject->text;

            $this->botCommands($entities, $text, $chat, $from->id);
        } catch (Exception $exception) {
            Log::error("Error while trying to read user answer " . $exception->getMessage());
        }
    }

    /**
     * @param array $request
     * @return void
     */
    protected function userLogOut(array $request): void
    {
        $bot = TelegraphChat::query()->where('chat_id', $request['chat']['id'])->first();
        
        if (isset($request['left_chat_member'])) {
            $left_member = $request;
            $group_user_name = '@'.$left_member['left_chat_member']['username'];
            $group_user_id = $left_member['left_chat_member']['id'];
            
            $user = Users::where('username', $group_user_name);
            $chat = TelegraphChat::where('name', $group_user_name);
            $birthday_table = Birthday::where('another_user_id', $group_user_id)->orWhere('birth_user_id', $group_user_id);
            
            if (isset($user) && isset($chat)) {
                $user->delete();
                $chat->delete();

                if (
                    $bot->name === 'New_Test_bot' ||
                    $bot->name === 'Office MSK (Bot)'
                ) {
                    $birthday_table->delete(); 
                }

                $message = 'Пользователь '. $group_user_name. " вышел из группы";
                $chat = TelegraphChat::find($bot->id);
                $chat->message($message)->send();

                unset($left_member);
                Log::debug($message);
            }
        }
    }

    /**
     * @param array $request
     * @return void
     */
    protected function userLogIn(array $request): void
    {
        if (!isset($request['new_chat_member'])) {
            return;
        }
        
        $bot = TelegraphChat::query()->where('chat_id', $request['chat']['id'])->first();
        $new_member = $request;

        Users::updateOrcreate(
            ['telegram_id' => $new_member['new_chat_member']['id']], 
            ['username' => "@".$new_member['new_chat_member']['username']]
        );

        TelegraphChat::updateOrcreate(
            ['chat_id' => $new_member['new_chat_member']['id']],
            ['name' => "@".$new_member['new_chat_member']['username']],
            ['telegraph_bot_id' => $bot['telegraph_bot_id']]
        );

        $message = "В группу вступил пользователь " .'@'.$new_member['new_chat_member']['username'];
        $chat = TelegraphChat::find($bot->id);
        $chat->message($message)->send();
        
        unset($new_member);
        Log::debug($message);
    }

    /**
     * Обрабатывает ответ по готовности к рабочему дню.
     * 
     * @param object $callback_query
     * @return void
     */
    protected function workDayResponse(object $callback_query): void
    {
        if (!isset($callback_query)) {
            return;
        }

        $full_data_buttons = $callback_query;
        $message_id = $full_data_buttons['message']['message_id'];
        $data = $full_data_buttons['data'];
        $username = '@'.$full_data_buttons['from']['username'];
        $chat_id = $full_data_buttons['message']['chat']['id'];
                                         
        switch ($full_data_buttons) {
            case $data === 'action:Да':                  
                $message = 'Отлично! Вы готовы работать.';                 
                $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$chat_id)->get();
            
                foreach ($db_chat_id as $db) {                     
                    $chat = TelegraphChat::find($db->id);
                    $chat->edit($message_id)->message($message)->send();
                }
                    
                $message = MessageId::where('chat_id', $chat_id); 
                $message->delete();   
                $this->insertWorkStatus('Да', $username);                     
                unset($full_data_buttons);
                break;
            case !empty($data_bad_resp) || $data === 'action:Нет':
                $message = 'Очень жаль, что вы не готовы начать рабочий день.';
                $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$chat_id)->get();

                foreach ($db_chat_id as $db) {                       
                    $chat = TelegraphChat::find($db->id);
                    $chat->edit($message_id)->message($message)->send();
                }

                $message = MessageId::where('chat_id', $chat_id);
                $message->delete();   
                $this->insertWorkStatus('Нет', $username);
                unset($full_data_buttons);
                break;
        }
    }


    /**
     * Обрабатывает ответ по готовности внести средства для именинника
     * 
     * @param object $callback_query
     * @return void
     */
    protected function birthdayResponse(object $callback_query): void
    {
        if(!isset($callback_query)) {
            return;
        }

        $full_data_buttons = $callback_query;
        $message_id = $full_data_buttons['message']['message_id'];
        $data = $full_data_buttons['data'];
        $chat_id = $full_data_buttons['message']['chat']['id'];
        $user_from_id = $full_data_buttons['from']['id'];
                          
        switch ($full_data_buttons) {                      
            case str_contains($data,'action:Да.др'):
                $birth_user_id  = trim(strrchr($data, ":"), ':');
                                
                $users = Users::where('telegram_id', $birth_user_id)->get();

                foreach ($users as $usr) {
                    $message =  $message = 'Отлично! Вы перевели средства на подарок '.
                    $usr->username . PHP_EOL . 'На всякий случай, ссылка все еще внизу ⬇️';
                }

                $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$chat_id)->get();
                foreach($db_chat_id as $db) {
                    $payment_url = 'https://www.google.com';  // здесь нужно вставить ссылку на актуальный счет сбора средств
                    $chat = TelegraphChat::find($db->id);
                    $chat->edit($message_id)->message($message)->keyboard(Keyboard::make()->buttons([
                    Button::make('Ссылка на оплату')->url($payment_url)]))->send();
                }
                // удаляем сообщение из базы с учетом чата, из которого поступил ответ
                $message = MessageId::where('chat_id', $chat_id);
                $message->delete();

                // запись статуса ответа в таблицу birthday 
                $result = Birthday::where([
                    ['birth_user_id', $birth_user_id],
                    ['another_user_id', $user_from_id]
                ]);

                if (!empty($result)) {
                    $result->update(['status' => 'Оплатил']);
                }   

                unset($full_data_buttons);
                break;
     
            case str_contains($data,'action:Нет.др') :
                $birth_user_id  = trim(strrchr($data, ":"), ':');

                $users = Users::where('telegram_id', $birth_user_id)->get();

                foreach ($users as $usr) {
                    $message = 'Вы отказались делать перевод средств на подарок '.
                    $usr->username;
                }

                $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$chat_id)->get();

                foreach ($db_chat_id as $db) {                  
                    $chat = TelegraphChat::find($db->id);
                    $chat->edit($message_id)->message($message)->send();
                }

                $message = MessageId::where('chat_id', $chat_id);
                $message->delete();
            
                // запись статуса ответа в таблицу birthday 
                $result = Birthday::where([
                    ['birth_user_id', $birth_user_id],['another_user_id', $user_from_id]
                ]);
                if(!empty($result)) {
                    $result->update(['status' => 'Отказался']);
                }     

                unset($full_data_buttons);
                break;
        }
    }

// обработка команд ===================================================

    /**
     * Обрабатывает команды бота
     */
    protected function botCommands(object $entities, object $text, object $chat, int $from_id): void
    {
        $group = TelegraphChat::find(1);
        $test_group_id = $group->chat_id;  // принимает запрос только из определенной группы
      
        if (!isset($entities) && $chat->id !== $test_group_id) {
            return;
        }
        $entities = (array)$entities;
        foreach($entities as $entity) {
            $type = $entity['type'];
        }
        $text = (array)$text;
            
        switch ($text['scalar']) {
             // команда new_admin
            case ($type === 'bot_command' && str_contains($text['scalar'], '/new_admin')):
                $textArr = explode(PHP_EOL, $text['scalar']);   

                if (count($textArr) < 2 || count($textArr) > 2 || $textArr[1] === '') {
                    $message = $this->bugMessageNewAdminCommand();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                } else {
                    // результат, прошедший проверку 
                    $username_tg = $textArr[1];
                    $result = $this->newAdminCommandStatus($username_tg);

                    if ($result) {
                        $message = 'Пользователь ' . $username_tg . " получил права администратора.";
                        $chat = TelegraphChat::find(1);
                        $chat->message($message)->send();
                        Log::debug($message);
                    }     
                    if (!$result) {  // проверка на существование пользователя в группе                 
                        $message = $this->unknownUserForNewAdminCommand($username_tg);
                        $chat = TelegraphChat::find(1);
                        $chat->message($message)->send();
                    }    
                }
                break;

                // работа с командой /info
            case ($type === 'bot_command' && str_contains($text['scalar'], '/info')):           
                $info_bot = $this->infoBotCommand();
                $chat = TelegraphChat::find(1);
                $chat->message($info_bot)->send();
                Log::debug('Получение всей информации о работе бота');
                break;

                // работа с командой /all
            case ($type === 'bot_command' && str_contains($text['scalar'],'/all')):
                $usr = $this->selectAllUsers();

                if (empty($usr)) {
                    $message = $this->notUsersAllCommandMessage();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                    unset($full_data);
                    Log::debug('В группе пока нет пользователей');
                }

                if (!empty($usr)) {
                    $message = '<b>Пользователи:</b> '.PHP_EOL. $usr;
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                    unset($full_data);
                    Log::debug('Произошел вывод всех пользователей из базы');
                }     
                break;

                // работа с командой /edit

            case ($type === 'bot_command' && str_contains($text['scalar'], '/edit')) :
                $textArr = explode(PHP_EOL, $text['scalar']);

                // проверка на количество элементов в массиве
                if (
                    count($textArr) < 5 ||
                    count($textArr) > 5 ||
                    $textArr[1] === '' ||
                    $textArr[2] === ''||
                    $textArr[3] === ''||
                    $textArr[4] === ''
                ) {
                    $message = $this->bugMessageFormatEditCommand();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                    unset($full_data);
                } else {              
                    $fullname =  $textArr[2];
                    $date_of_birth = $textArr[3];
                    $office_number = $textArr[4];
                    $username_tg = $textArr[1];

                // проверка на соответствие элементов массива              
                if (!preg_match('/(\d+)/s', $date_of_birth) || !str_contains($date_of_birth, '.')) {
                    $message = $this->bugMessageFormatEditCommand();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                    unset($full_data);

                // проверка на буквы в номере офиса
                } elseif (!is_numeric($office_number)) {
                    $message = $this->bugMessageOfficeNumberFormat();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();
                    unset($full_data);
                } else {

                    // работа с датой 
                    $correct_date = explode("-", str_replace(".", "-", $date_of_birth));

                    if (
                        !is_numeric($correct_date[0]) ||
                        !is_numeric($correct_date[1]) ||
                        !is_numeric($correct_date[2]) ||
                        $correct_date[2] < 1000 ||
                        strlen(trim($correct_date[0])) !== 2 ||
                        strlen(trim($correct_date[1])) !== 2
                    ) {                          
                        $message = $this->bugMessageDateFormatEditCommand();
                        $chat = TelegraphChat::find(1);
                        $chat->message($message)->send();
                        unset($full_data);
                    } else { 
                        if (!checkdate($correct_date[1],$correct_date[0],$correct_date[2])) {                               
                            $message = $this->bugMessageDateFormatEditCommand();
                            $chat = TelegraphChat::find(1);
                            $chat->message($message)->send();
                            unset($full_data);
                        } else { 
                    // результат, прошедший проверку
                            $result = $this->editCommandResult($date_of_birth, $username_tg, $fullname, $office_number);
                            if ($result) {
                                $message = 'Данные пользователя '. $username_tg." обновлены.";
                                $chat = TelegraphChat::find(1);
                                $chat->message($message)->send();
                                Log::debug($message);
                                unset($full_data); 
                            }     
                            if (!$result) {                          
                                $message = $this->unknownUserEditCommandMessage($username_tg);
                                $chat = TelegraphChat::find(1);
                                $chat->message($message)->send();
                                unset($full_data); 
                            } 
                        } 
                    } 
                } 
            }            
                break;

                //  работа с командой /admin
            case ($type === 'bot_command' && str_contains($text['scalar'],'/admin')):             
                $textArr = explode(PHP_EOL, $text['scalar']);
                // проверка на количество элементов в массиве
                if (
                    count($textArr) < 5 ||
                    count($textArr) > 5 ||
                    $textArr[1] == '' ||
                    $textArr[2] == ''||
                    $textArr[3] == ''||
                    $textArr[4] == ''
                ) {
                    $message = $this->bugMessageFormatAdminCommand();
                    $chat = TelegraphChat::find(1);
                    $chat->message($message)->send();                     
                } else {              
                    $fullname =  $textArr[2];
                    $date_of_birth = $textArr[3];
                    $office_number = $textArr[4];
                    $username_tg = $textArr[1];
                                
                    // `проверка на соответствие элементов массива              
                    if (!preg_match('/(\d+)/s', $date_of_birth) || !str_contains($date_of_birth, '.')) {
                        $message = $this->bugMessageFormatAdminCommand();
                        $chat = TelegraphChat::find(1);
                        $chat->message($message)->send();
                        unset($full_data);
                    // проверка на буквы в номере офиса
                    } elseif (!is_numeric($office_number)) {
                        $message = $this->bugMessageOfficeNumberFormatAdminCommand();
                        $chat = TelegraphChat::find(1);
                        $chat->message($message)->send();
                        unset($full_data);
                    } else {          
                        // работа с датой 
                        $correct_date = explode("-", str_replace(".", "-", $date_of_birth));

                        if (
                            !is_numeric($correct_date[0]) ||
                            !is_numeric($correct_date[1]) ||
                            !is_numeric($correct_date[2]) ||
                            $correct_date[2] < 1000 ||
                            strlen(trim($correct_date[0])) !== 2 ||
                            strlen(trim($correct_date[1])) !== 2
                        ) {                          
                            $message = $this->bugMessageDateFormatAdminCommand();
                            $chat = TelegraphChat::find(1);
                            $chat->message($message)->send();
                            unset($full_data);
                        } else { 
                            if (!checkdate($correct_date[1],$correct_date[0],$correct_date[2])) {                               
                                $message = $this->bugMessageDateFormatAdminCommand();
                                $chat = TelegraphChat::find(1);
                                $chat->message($message)->send();
                                unset($full_data);
                            } else { 
                                    
                            // результат, прошедший проверку
                            $result = $this->adminCommandResult($date_of_birth, $username_tg, $fullname, $office_number, $from_id);
                            
                            if($result) {
                                $message = $this->messageAdminCommandResult($username_tg);
                                $chat = TelegraphChat::find(1);
                                $chat->message($message)->send();    
                                unset($full_data); 
                            } 
                        } 
                    } 
                } 
            }
        break;
    }
}
            
   
/**
 * Group of methods for birthday
 * Kernel start
 * 
 */

    /** 
     * Отправка сообщений о предстоящем дне рождения 
     * @return void
     */
    protected function sendBirthdayMessages(): void
    {
            $group = TelegraphChat::find(1);
            $test_group_id = $group->chat_id;  // тестовый чат
            $date_day = date('d', strtotime('+3 day'));
            $date_month = date('m', strtotime('+3 day'));

            //выбираем человека с предстоящим др
            $user = Users::select('username', 'telegram_id', 'date_of_birth', 'fullname', 'office_number')
                ->whereMonth('date_of_birth', $date_month)
                ->whereDay('date_of_birth', $date_day)
                ->get();
            foreach ($user as $val) {
                $birth_tg_id = $val->telegram_id;
                $result_date = $val->date_of_birth;
                $result_user = $val->username;
                $result_fullname = $val->fullname;
                $result_office_number = $val->office_number;
                $correct_date = $val->getTransactionDateAttribute($val->date_of_birth);
            }
            
            // если он есть
            if (!empty($result_date)) {
            $message = 'У пользователя '.$result_user.' '.$result_fullname.' '.$correct_date.' '.$result_office_number.' скоро день рождения! Скинемся на подарок?'.PHP_EOL . 'Если вы уже перешли по ссылке и произвели оплату, нажмите "+". Если отказываетесь, нажмите "-".';
        
            // выбираем всех, у кого нет предстоящего др и есть полные данные в базе 
            $other_users = Users::select('username', 'telegram_id')
                ->whereMonth('date_of_birth', '!=', $date_month)
                ->whereDay('date_of_birth', '!=', $date_day)
                ->get();

            foreach ($other_users as $other_user) {
                $tg_id = $other_user->telegram_id;

            // преобразовываем их в id для отправки кнопок
                $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$tg_id)->get();
                foreach ($db_chat_id as $db) {   
                $payment_url = 'https://www.google.com';  // добавить актуальный счет для сбора средств
                    
            // отправляем кнопки
                $response = Telegraph::chat(TelegraphChat::find($db->id))
                    ->message($message)   // поменять find на $db->id для массовой рассылки или на 1 для тестовой в группу
                    ->keyboard(Keyboard::make()
                    ->buttons([
                        Button::make('Ссылка на оплату')->url($payment_url),
                        Button::make('+')->action('Да.др')->param('birth_user_id',  $birth_tg_id),
                        Button::make('-')->action('Нет.др')->param('birth_user_id',  $birth_tg_id),           
                        ]))->send();
                
                // echo 'Сообщение отправлено в тестовый чат '.$test_group_id.PHP_EOL, "<br>";  // тестовый чат
                Log::debug('Сообщение массово отправлено в чаты: '.$tg_id);  // массовая отправка

                //фиксируем message_id 
                $message_id = $response->telegraphMessageId();     
                json_decode($message_id);
            
                // если человек активировал бота и сообщение отправилось
                if (!empty($message_id)) {
                // вставить данное сообщение в базу с пометкой, в какой чат отправилось сообщение 
                    MessageId::insert([
                        'message_id' => $message_id,
                        'type' => 'birthday',
                        'chat_id' => $tg_id    // поменять на групповой чат $test_group_id (если слали сообщение в чат) для теста или на $tg_id для массовой рассылки
                    ]);

                    // добавляем в таблицу birthday новую связку
                    Birthday::updateOrcreate(          
                        ['another_user_id' => $tg_id ],
                        ['birth_user_id' => $birth_tg_id]
                    ); 
                } 
                } 
            } 
        }
    }

    public function getsendBirthdayMessages(): void
    {
        $this->sendBirthdayMessages();
    }

    /**
     * Таймаут отправленных сообщений о предстоящем дне рождения
     * @return void
     */
    protected function birthdayMessagesTimeOut(): void
    {
        // данный метод будет работать только тогда, когда наступил день рождения человека и в базе есть отосланные сообщения о его др, на которые не ответили
        // он должен запускаться каждый день, а соответствие найдется в коде
        $date_day = date('d');
        $date_month = date('m');

        // получаем всех, кому было отправлено сообщение о др
        $users = Users::select('username', 'telegram_id')->whereMonth('date_of_birth','!=', $date_month)->whereDay('date_of_birth','!=', $date_day)->get();    
        foreach ($users as $user) {
            $not_birthday_user_id = $user->telegram_id;
        }

        // получаем того, кого упомянули в сообщении о др
        $users = Users::select('username', 'telegram_id')
            ->whereMonth('date_of_birth', $date_month)
            ->whereDay('date_of_birth', $date_day)
            ->get();

        foreach ($users as $user) {
            $birthday_username = $user->username;
            $birthday_user_id = $user->telegram_id;
        }

        // получаем сообщения, отправленные массовой рассылкой о др, которые пришли всем людям, у кого сегодня не др
        $message_id = MessageId::where([['type', 'birthday'],['chat_id', $not_birthday_user_id]])->get(); // для теста использовать тестовую группу или $not_birthday_user_id для массовой
        foreach($message_id as $val) {
            //получаем id этих чатов для работы с редактированием сообщений
            $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$val->chat_id)->get();
            foreach($db_chat_id as $db) {
                if (!empty($birthday_user_id)) {
                    //вставляем в find эти чаты 
                    $chat = TelegraphChat::find($db->id); // для теста использовать 1 . для массовой использовать $db->id
                    //проводим редакцию
                    $chat->edit($val->message_id)->message("Вы отказались делать перевод средств на подарок ". $birthday_username)->send();
                }
                Log::debug('Сообщения о др отосланы из чатов: '. $val->chat_id);

            // удаляем из базы сообщения с поздравлением человека
                $message = MessageId::where([['type', 'birthday'],['chat_id', $not_birthday_user_id]]);  // для теста использовать тест группу $test_group_id, для массовой $not_birthday_user_id
                $message->delete();
            }
            // выбираем ччеловека, у которого сегодня др 
            $addNotBirthdayStatus = Birthday::where([['status', null], ['birth_user_id', $birthday_user_id ]])->orWhere([['status', ''],['birth_user_id', $birthday_user_id ]]);  
            // и устанавливаем статус того, кто не скинулся человеку на др
            $addNotBirthdayStatus->update(['status' => 'Отказался']);
        }
    }

    public function getbirthdayMessagesTimeOut()
    {
        $this->birthdayMessagesTimeOut();
    }

    /**
     * Отправка статистики по всем, кто скинулся или не скинулся на день рождения имениннику
     */
    protected function statisticsOfBirthday(): void
    {
        // сообщения должны приходить администратору а не в тестовую группу!  
        $date_day = date('d');
        $date_month = date('m');
        $users = Users::select('username', 'telegram_id', 'fullname', 'date_of_birth', 'office_number')
            ->whereMonth('date_of_birth', $date_month)
            ->whereDay('date_of_birth', $date_day)
            ->get();    

        foreach ($users as $user) {
            $birthday_user_id = $user->telegram_id;
            $birthday_username = $user->username;
            $birthday_date_of_birth = $user->getTransactionDateAttribute($user->date_of_birth);
            $birthday_fullname = $user->fullname;
            $birthday_office_number = $user->office_number;
            
            $users_bad_resp_birthday = $this->badResponseBirthdayUsers($birthday_user_id);
            $users_good_resp_birthday = $this->goodResponseBirthdayUsers($birthday_user_id);
        }

        if (isset($users_bad_resp_birthday)) {
            $users_bad_resp_birthday = implode("", $users_bad_resp_birthday);
        }
        if(isset($users_good_resp_birthday)) {
            $users_good_resp_birthday = implode("", $users_good_resp_birthday);
        }
          
        $users = Users::select('telegram_id')->where('is_admin', 1)->get();

        foreach($users as $user) {
            $telegram_id = $user->telegram_id;
            $db_chat_id = TelegraphChat::select('id')->where('chat_id', $telegram_id)->get(); 
            // вывод всех админов в группе
            foreach($db_chat_id as $db) {
                // обработчик. выдает уникальное сообщение в зависимости от скинувшихся и нет
                if (!empty($users_bad_resp_birthday) && !empty($users_good_resp_birthday)) {
                    $message = 'Cтатистика сборов дня рождения ' . PHP_EOL .$birthday_username . ' ' .
                    $birthday_fullname . ' ' . $birthday_date_of_birth . ' ' . $birthday_office_number . 
                    PHP_EOL . PHP_EOL . 'Скинулись:' . PHP_EOL . $users_good_resp_birthday . PHP_EOL .
                    'Не скинулись' . PHP_EOL . $users_bad_resp_birthday;

                    $chat = TelegraphChat::find($db->id);  // менять чат для массовой рассылки 
                    $chat->message($message)->send();     
                }

                if (empty($users_bad_resp_birthday) && !empty($users_good_resp_birthday)) {
                    $message = 'Cтатистика сборов дня рождения '. PHP_EOL . 
                    $birthday_username . ' ' . $birthday_fullname . ' ' . $birthday_date_of_birth . ' ' .
                    $birthday_office_number . PHP_EOL . PHP_EOL .
                    'Скинулись:' . PHP_EOL . $users_good_resp_birthday;

                    $chat = TelegraphChat::find($db->id); // менять чат для массовой рассылки 
                    $chat->message($message)->send();  
                }

                if (empty($users_good_resp_birthday) && !empty($users_bad_resp_birthday)) {
                    $message = 'Cтатистика сборов дня рождения ' . PHP_EOL .
                    $birthday_username . ' ' . $birthday_fullname . ' ' .
                    $birthday_date_of_birth . ' ' . $birthday_office_number . PHP_EOL . PHP_EOL .
                    'Не скинулись:' . PHP_EOL . $users_bad_resp_birthday;

                    $chat = TelegraphChat::find($db->id);   // менять чат для массовой рассылки 
                    $chat->message($message)->send();  
                }

                if (empty($users_good_resp_birthday) && empty($users_bad_resp_birthday)) {
                    $message = 'Нет статистики по сборам на день рождения ' . PHP_EOL . $birthday_username . 
                    ' ' . $birthday_fullname . ' ' . $birthday_date_of_birth . ' ' . $birthday_office_number;

                    $chat = TelegraphChat::find($db->id); // менять чат для массовой рассылки 
                    $chat->message($message)->send();   
                } 
            } 
        }
    }
    public function getstatisticsOfBirthday()
    {
        $this->statisticsOfBirthday();
    }


/**
 * Group of methods for workday
 * Kernel start
 * 
 */

    /**
     * Отправка кнопок с предложением поработать
     * @return void
     */
    protected function buttonsWorkDay(): void
    {
        // дополнительное исключение в виде праздников 
        $group = TelegraphChat::find(1);
        $test_group_id = $group->chat_id;  // группа для работы бота
        $date = date('d.m');

        // if($date >= '01')   // доработать
        $message = 'Вы готовы приступить к работе?';
        $users = Users::select(
            'telegram_id',
            'username',
            'date_of_birth',
            'fullname','office_number'
        )->get();

        foreach($users as $user) {
            $telegram_id = $user->telegram_id;
            $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$telegram_id)->get();

            foreach($db_chat_id as $db) {  
                $response = Telegraph::chat(TelegraphChat::find($db->id))->message($message)   // поменять find на $db->id   или на 1
                    ->keyboard(Keyboard::make()->buttons([
                        Button::make('Да')->action('Да'),
                        Button::make('Нет')->action('Нет'),
                    ]))->send();

                // фиксируем id всех отправленных сообщений
                $message_id = $response->telegraphMessageId();    
                json_decode($message_id);

                if (!empty($message_id)) {
                    MessageId::insert([
                        'message_id' => $message_id,
                        'type' => 'start_work',
                        'chat_id' => $telegram_id  
                    ]); 
                } 
            } 
        }
    }

    public function getButtonsWorkDay()
    {
        $this->buttonsWorkDay();    
    }

    /**
     * Обновление статуса в конце рабочего дня 
     * @return void
     */
    protected function unsetWorkStatus(): void
    {
        Users::where('work_status', 'Да')
            ->orWhere('work_status', 'Нет')
            ->update([
                'work_status' => ''
            ]); 
    }

    public function getUnsetWorkStatus()
    {
        $this->unsetWorkStatus();
    }

    /**
     * Таймаут для сообщений о предложении поработать 
     * @return void
     */
    protected function buttonsWorkDayTimeOut()
    {
        //данный метод сбросит кнопки в 10:00 и поставит статус Нет всем, кто не ответил на предложение поработать
        $message_id = MessageId::where('type', 'start_work')->get();

        foreach($message_id as $val) {
            $db_chat_id = TelegraphChat::select('id')->where('chat_id' ,$val->chat_id)->get();
            foreach($db_chat_id as $db) {
                $chat = TelegraphChat::find($db->id); // для теста использовать 1 . для массовой использовать $db->id
                $chat->edit($val->message_id)
                    ->message("Очень жаль, что вы не готовы начать рабочий день.")
                    ->send();
            }
            $message = MessageId::where('type', 'start_work');
            $message->delete();
        }
        $addNotWorkStatus = Users::where('work_status', null)->orWhere('work_status', '');
        $addNotWorkStatus->update(['work_status' => 'Нет']);
    }

    public function getbuttonsWorkDayTimeOut()
    {
        $this->buttonsWorkDayTimeOut();
    }


    /**
     * Отправляет статистику по начавшим рабочий день
     * @return void
     */
    protected function statisticOfWorkDay(): void
    {
        // сообщения должны приходить администратору а не в тестовую группу!
        $users_not_work = $this->selectNotWorkUsers();
        $users_work = $this->selectWorkUsers();
        date_default_timezone_set('Europe/Moscow');
        $date = Date("d.m.Y H:i");
  
        $users = Users::select('telegram_id')->where('is_admin', 1)->get();

        foreach($users as $user) {
            $telegram_id = $user->telegram_id;
            $db_chat_id = TelegraphChat::select('id')->where('chat_id', $telegram_id)->get(); 
            // вывод всех админов в группе
            foreach($db_chat_id as $db) {
                if (!empty($users_not_work) && !empty($users_work)) {
                    $message = 'Статистика на сегодня '.'<i>'.$date.'</i>'.PHP_EOL.PHP_EOL.
                    'К работе приступили:'.PHP_EOL.$users_work.PHP_EOL.
                    'Не приступили к работе'.PHP_EOL.$users_not_work;
                    $chat = TelegraphChat::find($db->id);
                    $chat->message($message)->send();  
                }

                if (empty($users_not_work) && !empty($users_work)) {
                    $message = 'Статистика на сегодня '.'<i>'.$date.'</i>'.PHP_EOL.PHP_EOL.
                    'К работе приступили:'.PHP_EOL.$users_work;
                    $chat = TelegraphChat::find($db->id);
                    $chat->message($message)->send();  
                }

                if (empty($users_work) && !empty($users_not_work)) {
                    $message = 'Статистика на сегодня '.'<i>'.$date.'</i>'.PHP_EOL.PHP_EOL.
                    'Не приступили к работе:'.PHP_EOL.$users_not_work;
                    $chat = TelegraphChat::find($db->id);
                    $chat->message($message)->send();  
                }

                if (empty($users_work) && empty($users_not_work)) {
                    $message = 'Статистики нет.';
                    $chat = TelegraphChat::find($db->id);
                    $chat->message($message)->send();  
                } 
            } 
        }
    }

    public function getstatisticOfWorkDay()
    {
        $this->statisticOfWorkDay();
    }

    /** 
     * Helpers for bot.
     * Text for messages.
     */


    protected function insertWorkStatus(string $status, string $username): void 
    {
        $work_user = Users::where('username', $username); 
        $work_user->update(['work_status' => $status]);
    }

    protected function bugMessageNewAdminCommand(): string 
    {
        $message = 'Некорректный формат команды /new_admin. Используйте шаблон с переносом строки для каждого значения:'
        . PHP_EOL . '/new_admin' . PHP_EOL . '@username';

        return $message;
    }
    
    protected function newAdminCommandStatus(string $username_tg): bool
    {
        $db_user = Users::select('username')->where('username', $username_tg)->get();

        foreach($db_user as $user) {
            if (isset($user->username)) {           
                $user = Users::where('username', $user->username);
                $user->update(['is_admin' => 1]);

                return true; 
            }    
        }
        return false;
    }

    protected function unknownUserForNewAdminCommand(string $username_tg): string
    {
        $message = 'Пользователь ' . $username_tg .
        ' не состоит в этой группе. Проверьте правильность написания @username в команде /new_admin. 
        Для вывода всех участников воспользуйтесь командой /all';

        return $message;
    }

    protected function infoBotCommand(): string
    {
        $message = 'Команда <b>/info</b>:'.PHP_EOL.'выводит всю информацию о работе бота.'.PHP_EOL.PHP_EOL.
        'Команда <b>/edit</b>:'.PHP_EOL.'позволяет добавить полную информацию о новом участнике группы. <i>Шаблон</i>:'
        .PHP_EOL.'/edit'.PHP_EOL.'@username'.PHP_EOL.'Фамилия Имя Отчество(если есть)'.PHP_EOL.'дд.мм.гггг'.PHP_EOL.'номер офиса'.
        PHP_EOL.PHP_EOL.
        'Команда <b>/all</b>:'.PHP_EOL.'выводит информацию об участниках группы. Если пользователь не имеет полной информации о себе, команда выведет его @username'.PHP_EOL.PHP_EOL.
        'Команда <b>/admin</b>:'.PHP_EOL.'добавляет информацию об администраторе группы. Необходимо воспользоваться данной командой, если вы являетесь администратором и создателем группы. <i>Шаблон</i>:'.
         PHP_EOL.'/edit'.PHP_EOL.'@username(ваш)'.PHP_EOL.'Фамилия Имя Отчество(если есть)'.PHP_EOL.'дд.мм.гггг'.PHP_EOL.'номер офиса'.PHP_EOL.PHP_EOL.
        'Команда <b>/new_admin</b>:'.PHP_EOL.'дает права администратора любому участнику группы. <i>Шаблон</i>:'.
         PHP_EOL.'/edit'.PHP_EOL.'@username'.PHP_EOL.PHP_EOL.
        '❗Для заполнения поля @username в команде /admin скопируйте его из настроек своего аккаунта вручную.'.PHP_EOL.PHP_EOL.
        '<b> ‼️Важно!</b> Для полноценного выполнения функционала бота каждый участник группы должен начать с ним личную беседу @office_msc_bot, нажав start.';

        return $message;
    }

    protected function selectAllUsers(): string
    {
        $db_users = Users::select('username', 'fullname', 'date_of_birth', 'office_number')->get();
        $usernameArr = [];  

        foreach ($db_users as $users) {   
            if (isset($users->date_of_birth)) { // отбор пользователей, чья дата дня рождения не занесена в базу
                $correct_date = $users->getTransactionDateAttribute($users->date_of_birth);    
                array_push(
                    $usernameArr,
                    $users->username . ' ' . str_replace("_", " ", $users->fullname) . ' ' . $correct_date . ' ' . $users->office_number . PHP_EOL
                );
            } else {
                array_push(
                    $usernameArr,
                    $users->username .' ' . str_replace("_", " ", $users->fullname) . ' ' . $users->date_of_birth . ' ' . $users->office_number . PHP_EOL
                ); 
            } 
        }
        $usr = implode("", $usernameArr);

        return $usr; 
    }

    protected function notUsersAllCommandMessage(): string
    {
        $message = 'В группе пока нет участников. Вы можете добавить пользователей прямо сейчас.' . PHP_EOL . PHP_EOL .
        'Для получения полной информации используйте команду /info';

        return $message;
    }

    protected function bugMessageFormatEditCommand(): string
    {
        $message = 'Некорректный формат команды /edit. Используйте шаблон с переносом строки для каждого значения:'
        .PHP_EOL. '/edit'.PHP_EOL.'@username'.PHP_EOL.'Фамилия Имя Отчество(если есть)'.PHP_EOL.'дд.мм.гггг'
        .PHP_EOL.'номер офиса'.PHP_EOL;

        return $message;
    }

    protected function bugMessageOfficeNumberFormat(): string
    {
        $message = 'Неправильный формат значений для команды /edit. Номер офиса должен содержать только цифры. 
        Используйте шаблон с переносом строки для каждого значения:'
        . PHP_EOL . '/edit' . PHP_EOL . '@username' . PHP_EOL . 'Фамилия Имя Отчество(если есть)' . PHP_EOL . 'дд.мм.гггг' . PHP_EOL . 'номер офиса' . PHP_EOL;

        return $message;
    }

    protected function bugMessageDateFormatEditCommand(): string
    {
        $message = 'Неправильный формат даты для команды /edit. Ожидается дд.мм.гггг';

        return $message;
    }

    /**
     * Выполняет команду /edit
     * @param string $date_of_birth
     * @param string $username_tg
     * @param string $fullname
     * @param int $office_number
     * @return bool
     */
    protected function editCommandResult(string $date_of_birth, string $username_tg, string $fullname, int $office_number): bool
    {
        $date = new DateTime($date_of_birth);
        $date->format('Y-m-d');                                           
        $db_user = Users::select('username', 'fullname', 'date_of_birth', 'office_number')
            ->where('username', $username_tg)
            ->get();
        foreach ($db_user as $user) {
            if(isset($user->username)) {
                $db_user = Users::where('username',$user->username); 
                $db_user->update([
                    'fullname' => $fullname,
                    'date_of_birth' => $date->format('Y-m-d'),
                    'office_number' => $office_number
                ]); 
            } 
        }
        if(!isset($user->username)) {
            return false;
        }

        return true; 
    }

    protected function unknownUserEditCommandMessage($username_tg): string
    {
        $message = 'Пользователь ' . $username_tg .
        ' не состоит в этой группе. Проверьте правильность написания @username в команде /edit.';

        return $message;
    }

    protected function bugMessageFormatAdminCommand(): string
    {
        $message = 'Некорректный формат команды /admin. Используйте шаблон с переносом строки для каждого значения:'
        . PHP_EOL . '/edit' . PHP_EOL . '@username' . PHP_EOL . 'Фамилия Имя Отчество(если есть)' . PHP_EOL . 'дд.мм.гггг'
        . PHP_EOL . 'номер офиса ' . PHP_EOL;

        return $message;
    }

    protected function bugMessageOfficeNumberFormatAdminCommand(): string
    {
        $message = 'Неправильный формат значений для команды /admin. Номер офиса должен содержать только цифры. 
        Используйте шаблон с переносом строки для каждого значения:' . PHP_EOL . '/edit' . PHP_EOL . '@username'
        . PHP_EOL . 'Фамилия Имя Отчество(если есть)' . PHP_EOL . 'дд.мм.гггг' . PHP_EOL . 'номер офиса' . PHP_EOL;

        return $message;
    }

    protected function bugMessageDateFormatAdminCommand(): string
    {
        $message = 'Неправильный формат даты для команды /admin. Ожидается дд.мм.гггг';

        return $message;
    }

    /**
     * Выполняет команду /admin
     * @param 
     */
    protected function adminCommandResult(
        string $date_of_birth,
        string $username_tg,
        string $fullname,
        int $office_number,
        int $from_id
    ): bool {
        $date = new DateTime($date_of_birth);
        $date->format('Y-m-d');    
                                                                                      
        Users::updateOrcreate(
            ['telegram_id' => $from_id], 
            ['username' => $username_tg,
            'fullname' => $fullname,
            'date_of_birth' => $date->format('Y-m-d'),
            'office_number' => $office_number,
            'is_admin' => 1]
        );
        TelegraphChat::updateOrcreate(
            ['chat_id' => $from_id],
            ['name' => $username_tg]
        );

        return true;
    }

    protected function messageAdminCommandResult($username_tg): string
    {
        $message = 'Добавлены данные администратора ' . $username_tg . PHP_EOL . 
        'Проверьте правильность написания вашего @username, нажав на него: '
        . $username_tg . PHP_EOL . 'Если Вам не удалось перейти по ссылке ' . $username_tg .
        ', исправьте ваш @username, повторно вызвав команду /admin';

        return $message;
    }

    /**
     * Выводит всех пользователей, кто не вложил деньги на день рождения
     * @param int $birthday_user_id
     * @return array
     */
    protected function badResponseBirthdayUsers(int $birthday_user_id): array
    {
        $users_bad_resp_birthday[] = '';
        $result = Birthday::select('another_user_id')
            ->where([
                ['status', 'Отказался'],
                ['birth_user_id', $birthday_user_id]
            ])
            ->get();

        foreach ($result as $res) {
            $db_users = Users::where('telegram_id', $res->another_user_id)->get();
            foreach ($db_users as $users) {
                $users_bad_resp_birthday[] = $users->username . ' ' . $users->fullname . PHP_EOL;
            } 
        }

        return $users_bad_resp_birthday;
    }

    protected function goodResponseBirthdayUsers(int $birthday_user_id): array
    {
        $users_good_resp_birthday[] = '';
        $result = Birthday::select('another_user_id')
            ->where([
                ['status', 'Оплатил'],
                ['birth_user_id', $birthday_user_id]
            ])
            ->get();
        foreach ($result as $res) {       
            $db_users = Users::where('telegram_id', $res->another_user_id)->get();
            foreach ($db_users as $users) {
                $users_good_resp_birthday[] = $users->username . ' ' . $users->fullname . PHP_EOL;
            } 
        }

        return $users_good_resp_birthday;   
    }

    protected function selectNotWorkUsers(): string
    {
        $db_users = Users::select('username', 'fullname', 'date_of_birth', 'office_number')
            ->where('work_status', 'Нет')
            ->get();
        $usernameArr = [];

        foreach ($db_users as $users) {   
            if (isset($users->date_of_birth)) { 
                $correct_date = $users->getTransactionDateAttribute($users->date_of_birth);  
                array_push(
                    $usernameArr, $users->username . ' ' . $users->fullname . ' ' .
                    $correct_date . ' ' . $users->office_number . PHP_EOL
                );       
            } else {
                array_push(
                    $usernameArr, $users->username . ' ' . $users->fullname . ' ' .
                    $users->date_of_birth . ' ' . $users->office_number . PHP_EOL
                ); 
            } 
        }
        $usr = implode("", $usernameArr);

        return $usr;
    }

    protected function selectWorkUsers()
    {
        $db_users = Users::select('username', 'fullname', 'date_of_birth', 'office_number')
            ->where('work_status', 'Да')
            ->get();
        $usernameArr = [];

        foreach ($db_users as $users) {   
            if (isset($users->date_of_birth)) { 
                $correct_date = $users->getTransactionDateAttribute($users->date_of_birth);  
                array_push(
                    $usernameArr, $users->username . ' ' . $users->fullname . ' ' .
                    $correct_date . ' ' . $users->office_number . PHP_EOL
                );       
            } else {
                array_push(
                    $usernameArr, $users->username . ' ' . $users->fullname . ' ' .
                    $users->date_of_birth . ' ' . $users->office_number . PHP_EOL
                ); 
            } 
        }
        $usr = implode("", $usernameArr);

        return $usr;
    }
}
