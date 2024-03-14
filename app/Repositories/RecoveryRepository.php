<?php

namespace App\Repositories;

use App\Models\Teacher;

class RecoveryRepository
{
    protected $teacherModel;

    public function __construct(Teacher $teacherModel)
    {
        $this->teacherModel = $teacherModel;
    }


    public function findUser($surname, $iin, $email) {
        if (is_numeric($iin) && strlen($iin)==12) {
//            if (!$this->validateIIN($iin)) {
//                return [
//                    "code" => 404,
//                    "message" => __("Некорректный ИИН")
//                ];
//            }
        }
        else {
            return [
                "code" => 404,
                "message" => __("Некорректный ИИН")
            ];
        }


        $teacher = $this->teacherModel
            ->where('iin', '=', $iin)
            ->where('surname', '=', $surname)
            ->orderBy('email', 'desc')
            ->first();
        if (!$teacher) {
            return [
                "code" => 404,
                "message" => __("В базе отсутствуют указанные данные")
            ];
        }

        if ($teacher->email) {
            if ($teacher->email == $email) {
                return [
                    "code" => 200,
                    "message" => __("Запрашиваемые данные найдены. Отправить напоминание пароля на вашу почту?"),
                    "surname" => $surname,
                    "iin" => $iin,
                    "email" => $email,
                ];
            }
            else {
                $hide_email = explode('@',$teacher->email);
                $email_lenght = strlen($hide_email[0]);
                $email_first = substr($hide_email[0],0,1);
                $email_last = substr($hide_email[0],($email_lenght-1),1);
                $email_start ='';
                $ns=1;
                while ($ns<=($email_lenght-2)){
                    $email_start.='*';
                    $ns++;
                }
                $str_email = $email_first.$email_start.$email_last.'@'.$hide_email[1];

                return [
                    "code" => 404,
                    "message" => __("Данные обнаружены, но указанная вами почта - другая. Восстановление прервано! Напоминаем, что при регистрации использован электронный адрес:").' '.$str_email
                ];
            }
        }
        else {
            return [
                "code" => 404,
                "message" => __("Данные обнаружены, но эл.почта не указана. Чтобы указать эл.почту обращайтесь к администратору школы")
            ];
        }
    }

    public function validateIIN($iin) {
        $s = 0;
        for ($i = 0; $i < 11; $i++)
        {
            $s = $s + ($i + 1) * $iin[$i];
        }
        $k = $s % 11;
        if ($k == 10)
        {
            $s = 0;
            for ($i = 0; $i < 11; $i++)
            {
                $t = ($i + 3) % 11;
                if($t == 0)
                {
                    $t = 11;
                }
                $s = $s + $t * $iin[$i];
            }
            $k = $s % 11;
            if ($k == 10)
                return false;
            return ($k == substr($iin,11,1));
        }
        return ($k == substr($iin,11,1));
    }

    public function getUser($surname, $iin, $email) {
        return $this->teacherModel
            ->where('surname', '=', $surname)
            ->where('iin', '=', $iin)
            ->where('email', '=', $email)
            ->first();
    }
}
