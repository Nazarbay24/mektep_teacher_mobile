<?php

namespace App\Repositories;

use App\Models\Diary as Model;
use App\Models\Message;
use App\Models\Plan;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiaryRepository
{
    use Notifiable;

    protected $model;
    protected $planModel;
    protected $lang;

    public function __construct(Model $model)
    {
        $this->model = $model;

        if(app()->getLocale() == 'ru') $this->lang = 'rus';
        else if(app()->getLocale() == 'kk') $this->lang = 'kaz';
    }

    public function init(int $id_mektep)
    {
        $this->model->init($id_mektep);
    }


    public function todayDiary($teacher) {
        $diary = $this->model
            ->select(
                $this->model->getTable().'.date as date',
                $this->model->getTable().'.number as lesson_num',
                $this->model->getTable().'.id_predmet as id_predmet',
                $this->model->getTable().'.tema as tema',
                $this->model->getTable().'.submitted as submitted',
                'mektep_class.class as class',
                'mektep_class.group as group',
                'mektep_class.smena as smena',
                'mektep_predmet.subgroup as subgroup',
                'edu_predmet_name.predmet_'.$this->lang.' as predmet_name')
            ->leftJoin('mektep_class', $this->model->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('mektep_predmet', $this->model->getTable().'.id_predmet', '=', 'mektep_predmet.id')
            ->leftJoin('edu_predmet_name', 'mektep_predmet.predmet', '=', 'edu_predmet_name.id')
            ->where($this->model->getTable().'.id_teacher', auth()->user()->id)
            ->where($this->model->getTable().'.date', date("Y-m-d"))//заменить на текущую дату date("Y-m-d")
            ->orderBy('mektep_class.smena', 'asc')
            ->orderBy($this->model->getTable().'.number', 'asc')
            ->get()->all();

        $smenaQuery = DB::table('mektep_smena')
            ->where('id_mektep', '=', auth()->user()->id_mektep)
            ->get()->all();
        $smenaQuery = json_decode(json_encode($smenaQuery), true);

        $smenaTime = [];
        foreach ($smenaQuery as $item) {
            for ($i = 1; $i <= 10; $i++) {
                $smenaTime[$item['smena']][$i]['start_time'] = $item['z'.$i.'_start'];
                $smenaTime[$item['smena']][$i]['end_time'] = $item['z'.$i.'_end'];
            }
        }

        foreach ($diary as $key => $item) {
            $prev_tema = $this->model
                ->select('tema', 'submitted as prev_submitted')
                ->where('id_teacher', '=', auth()->user()->id)
                ->where('id_predmet', '=', $item['id_predmet'])
                ->where('date', '<', $item['date'])
                ->where('submitted', '=', 1)
                ->orderBy('date', 'desc')
                ->first();

            if ($prev_tema) {
                $diary[$key]['prev_submitted'] = $prev_tema['prev_submitted'] != null ? $prev_tema['prev_submitted'] : 0;
                $diary[$key]['prev_tema'] = $prev_tema['tema'] != null ? str_replace("\r\n",'', $prev_tema['tema']) : __("Не задано");
            }
            else {
                $diary[$key]['prev_submitted'] = 0;
                $diary[$key]['prev_tema'] = __("Не задано");
            }

            $diary[$key]['tema'] = $item['tema'] != null ? $item['tema'] : __("Не задано");

            $diary[$key]['class'] = $item['class'].'«'.$item['group'].'»';
            unset($diary[$key]['group']);

            $diary[$key]['start_time'] = $item['date'].' '.$smenaTime[$item['smena']][$item['lesson_num']]['start_time'].':00';
            $diary[$key]['end_time'] = $item['date'].' '.$smenaTime[$item['smena']][$item['lesson_num']]['end_time'].':00';
            unset($diary[$key]['date']);
        }


        $messages = Message::
            where('poluchatel_id', '=', $teacher->id.'@t')
            ->where('id_mektep', '=', $teacher->id_mektep)
            ->where('read_status', '=', 0)
            ->get()->all();


        $date = date("Y-m-d"); //заменить на текущую дату date("Y-m-d")
        $dayOfWeek = date('w', strtotime($date));
        $dayOfMonth = date('d', strtotime($date));
        $month = date('m', strtotime($date));

        $todayInfo = [
            'current_time' => date('Y-m-d H:i:s'), //заменить на текущую дату date('Y-m-d H:i:s')
            'day_number' => $dayOfWeek,
            'day' => __('d_'.$dayOfWeek).', '.ltrim($dayOfMonth, 0).' '.__('m_'.$month),
            'message_count' => count($messages)
        ];

        $todayInfo = $this->getCurrentTime();
        $todayInfo['message_count'] = count($messages);

        return [
            "info" => $todayInfo,
            "diary" => $diary
        ];
    }


    public function diary($monday, $saturday) {
        $weekDiary = $this->model
            ->select('date',
                    'number as lesson_num',
                    'edu_predmet_name.predmet_'.$this->lang.' as predmet_name',
                    'mektep_class.class as class',
                    'mektep_class.group as group',
                    'mektep_class.smena as smena',
                    'mektep_predmet.subgroup as subgroup',
                    'mektep_predmet.id as predmet_id',)
            ->leftJoin('mektep_predmet', $this->model->getTable().'.id_predmet', '=', 'mektep_predmet.id')
            ->leftJoin('edu_predmet_name', 'mektep_predmet.predmet', '=', 'edu_predmet_name.id')
            ->leftJoin('mektep_class', $this->model->getTable().'.id_class', '=', 'mektep_class.id')
            ->where($this->model->getTable().'.id_teacher', '=', auth()->user()->id)
            ->where($this->model->getTable().'.date', '>=', $monday)
            ->where($this->model->getTable().'.date', '<=', $saturday)
            ->orderBy($this->model->getTable().'.date')
            ->orderBy('mektep_class.smena')
            ->orderBy($this->model->getTable().'.number')
            ->orderBy($this->model->getTable().'.id')
            ->get()->all();

        $smenaQuery = DB::table('mektep_smena')
            ->where('id_mektep', '=', auth()->user()->id_mektep)
            ->get()->all();
        $smenaQuery = json_decode(json_encode($smenaQuery), true);

        $smenaTime = [];
        foreach ($smenaQuery as $item) {
            for ($i = 1; $i <= 10; $i++) {
                $smenaTime[$item['smena']][$i]['start_time'] = $item['z'.$i.'_start'];
                $smenaTime[$item['smena']][$i]['end_time'] = $item['z'.$i.'_end'];
            }
        }

        foreach ($weekDiary as $key => $item) {
            $weekDiary[$key]['class'] = $item['class'].'«'.$item['group'].'»';
            unset($weekDiary[$key]['group']);

            $weekDiary[$key]['start_time'] = $smenaTime[$item['smena']][$item['lesson_num']]['start_time'];
            $weekDiary[$key]['end_time'] = $smenaTime[$item['smena']][$item['lesson_num']]['end_time'];
            $weekDiary[$key]['current_time'] = date('H:i');

            $day = date('w', strtotime($item['date']));
            $weekDiary[$key]['day_number'] = $day;
            $weekDiary[$key]['day'] = __('d_'.$day);
        }


         $weekDiaryFilteredByDay = [];
         foreach ($weekDiary as $key => $item) {
             if ($item['date'] == date("Y-m-d") /*date("Y-m-d")*/) { // заменить на текущую дату
                 $weekDiaryFilteredByDay[$item['day_number']]['current_day'] = true;
             }

             $weekDiaryFilteredByDay[$item['day_number']]['date'] = date("d.m", strtotime($item['date']));
             $weekDiaryFilteredByDay[$item['day_number']]['day'] = $item['day'];
             $weekDiaryFilteredByDay[$item['day_number']]['lessons'][] = $item;


             unset($item['current_time']);
             unset($item['day_number']);
             unset($item['day']);
             unset($item['date']);
         }
        $weekDiaryFilteredByDay2 = [];
         foreach ($weekDiaryFilteredByDay as $item) {
             $weekDiaryFilteredByDay2[] = $item;
         }

         return $weekDiaryFilteredByDay2;
    }


    public function setTema($id_teacher, $id_predmet, $plan, $date) {
        $diary = $this->model
            ->where('id_teacher', '=', $id_teacher)
            ->where('id_predmet', '=', $id_predmet)
            ->where('date', '=', $date)
            ->first();
        if (!$diary) throw new \Exception(__('Тема не найдена'),404);

        $diary->tema = $plan['title'];
        $diary->homework = $plan['homework'];
        $diary->literatura = $plan['literature'];
        $diary->submitted = 1;
        $diary->opened = 1;

        return $diary->save();
    }


    public function getCurrentTime() {
        $date = date("Y-m-d");
        $dayOfWeek = date('w', strtotime($date));
        $dayOfMonth = date('d', strtotime($date));
        $month = date('m', strtotime($date));

        return [
            'current_time' => date('Y-m-d H:i:s'), //заменить на текущую дату date('Y-m-d H:i:s')
            'day_number' => $dayOfWeek,
            'day' => __('d_'.$dayOfWeek).', '.ltrim($dayOfMonth, 0).' '.__('m_'.$month),
        ];
    }
}
