<?php

namespace App\Repositories;

use App\Models\Teacher as Model;
use Illuminate\Notifications\Notifiable;

class TeacherRepository {
    use Notifiable;

    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }


    public function login($iin, $password, $ipAdress) {
        $userAccounts = $this->model
            ->where('iin', $iin)
            ->where('status', 1)
            ->where('blocked', 0)
            ->where('id_mektep', '>', 0)
            ->get()->all();

        foreach ($userAccounts as $user) {
            if ($user->parol == $password) {
                if (count($userAccounts) == 1) {
                    $userAccounts[0]->device = 'mobile';
                    $userAccounts[0]->ip = $ipAdress;
                    $userAccounts[0]->last_visit = date('Y-m-d H:i:s');

                    $userAccounts[0]->save();
                }
                return $userAccounts;
            }
        }

        return false;
    }


    public function getSchools($iin)
    {
        $schools = $this->model
            ->select('id_mektep as id',
                'specialty',
                'mektepter.name_kaz as name_kk',
                'mektepter.name_rus as name_ru',
                'edu_punkt.oblast_kaz as oblast_kk',
                'edu_punkt.oblast_rus as oblast_ru',
                'edu_punkt.punkt_kaz as punkt_kk',
                'edu_punkt.punkt_rus as punkt_ru')
            ->join('mektepter', $this->model->table.'.id_mektep', '=', 'mektepter.id')
            ->join('edu_punkt', 'mektepter.edu_punkt', '=', 'edu_punkt.id')
            ->where('iin', $iin)
            ->where('status', 1)
            ->where('blocked', 0)
            ->get()->all();

        return $schools;
    }


    public function choiceSchool($id, $iin, $ipAdress) {
        $account = $this->model
            ->where('iin', $iin)
            ->where('id_mektep', $id)
            ->where('status', 1)
            ->where('blocked', 0)
            ->first();

        $account->device = 'mobile';
        $account->ip = $ipAdress;
        $account->last_visit = date('Y-m-d H:i:s');
        $account->save();

        return $account;
    }


    public function teacherLog($teacher_id, $ipAdress, $deviceInfo) {
        return $this->model
            ->where('id', $teacher_id)
            ->where('status', 1)
            ->where('blocked', 0)
            ->update([
                'device' => 'mobile',
                'last_visit' => date('Y-m-d H:i:s'),
                'ip' => $ipAdress,
                'os' => $deviceInfo
            ]);
    }
}
