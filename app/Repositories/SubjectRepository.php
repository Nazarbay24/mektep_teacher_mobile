<?php

namespace App\Repositories;

use App\Models\Diary;
use App\Models\Predmet as Model;
use App\Models\PredmetCriterial;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class SubjectRepository
{
    use Notifiable;

    protected $model;
    protected $diaryModel;
    protected $lang;

    public function __construct(Model $model, Diary $diaryModel)
    {
        $this->model = $model;
        $this->diaryModel = $diaryModel;

        if(app()->getLocale() == 'ru') $this->lang = 'rus';
        else if(app()->getLocale() == 'kk') $this->lang = 'kaz';
    }

    public function init(int $id_mektep)
    {
        $this->diaryModel->init($id_mektep);
    }


    public function getSubject($id) {
        $subject = $this->model
            ->select($this->model->getTable().'.id as id',
                    'sagat',
                    'predmet',
                    DB::raw('count('.$this->diaryModel->getTable().'.id) as sagat_passed'),
                    'subgroup',
                    'edu_predmet_name.predmet_'.$this->lang.' as predmet_name',
                    'mektep_class.class as class',
                    'mektep_class.group as group',
                    'mektep_class.edu_language as lang')
            ->leftJoin('edu_predmet_name', $this->model->getTable().'.predmet', '=', 'edu_predmet_name.id')
            ->leftJoin('mektep_class', $this->model->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('mektep_diary_'.auth()->user()->id_mektep.'_'.config('mektep_config.year'), function($join)
            {
                $join->on($this->model->getTable().'.id', '=', $this->diaryModel->getTable().'.id_predmet');
                $join->on($this->model->getTable().'.id_teacher', '=', $this->diaryModel->getTable().'.id_teacher');
                $join->where('submitted', '=', '1');
            })
            ->where($this->model->getTable().'.id', '=', $id)
            ->groupBy($this->model->getTable().'.id')
            ->first();
        if (!$subject) throw new \Exception('Not found',404);

        $isCriterial = PredmetCriterial::
        where('class', '=', $subject['class'])
            ->where('predmet', '=', $subject['predmet'])
            ->where('edu_language', '=', $subject['lang'])
            ->first();

        if($subject['sagat_passed'] > $subject['sagat']) {
            $subject['sagat_passed'] = $subject['sagat'];
        }

        $subject['lang'] = $subject['lang'] == 1 ? __('Казахский') : __('Русский');
        $subject['progress'] = round(($subject['sagat_passed'] / $subject['sagat']) * 100).'%';
        $subject['sagat_left'] = $subject['sagat'] - $subject['sagat_passed'];
        $subject['class'] = $subject['class'].'«'.$subject['group'].'»';
        $subject['is_criterial'] = (bool)$isCriterial;
        unset($subject['group']);
        unset($subject['predmet']);

        return $subject;
    }


    public function mySubjects() {
        $id_teacher = auth()->user()->id;

        $mySubjects = $this->model
            ->select($this->model->getTable().'.id as id',
                'sagat',
                DB::raw('count('.$this->diaryModel->getTable().'.id) as sagat_passed'),
                'subgroup',
                'edu_predmet_name.predmet_'.$this->lang.' as predmet_name',
                'mektep_class.class as class',
                'mektep_class.group as group')
            ->leftJoin('edu_predmet_name', $this->model->getTable().'.predmet', '=', 'edu_predmet_name.id')
            ->leftJoin('mektep_class', $this->model->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin($this->diaryModel->getTable(), function($join)
            {
                $join->on($this->model->getTable().'.id', '=', $this->diaryModel->getTable().'.id_predmet');
                $join->on($this->model->getTable().'.id_teacher', '=', $this->diaryModel->getTable().'.id_teacher');
                $join->where('submitted', '=', '1');
            })
            ->where($this->model->getTable().'.id_teacher', '=', $id_teacher)
            ->groupBy($this->model->getTable().'.id')
            ->get()->all();

        $prev_tema = $this->diaryModel
            ->select('id_predmet', 'tema')
            ->where('id_teacher', '=', $id_teacher)
            ->whereIn('id_predmet', array_column($mySubjects, 'id'))
            ->where('submitted', '=', 1)
            ->orderBy('date', 'desc')
            ->groupBy('id_predmet')
            ->get()
            ->keyBy('id_predmet')
            ->toArray();

        foreach ($mySubjects as $key => $item) {
            if(isset($prev_tema[$item['id']])) {
                $mySubjects[$key]['prev_tema'] = $prev_tema[$item['id']]['tema'] != null ? str_replace("\r\n",'', $prev_tema[$item['id']]['tema']) : __("Не задано");
            }
            else {
                $mySubjects[$key]['prev_tema'] = __("Не задано");
            }

            $mySubjects[$key]['progress'] = __('Пройдено').' '.(round(($item['sagat_passed'] / $item['sagat']) * 100).'%');
            $mySubjects[$key]['class'] = $item['class'].'«'.$item['group'].'»';
            unset($mySubjects[$key]['group']);
            unset($mySubjects[$key]['sagat']);
            unset($mySubjects[$key]['sagat_passed']);
        }

        return $mySubjects;
    }


    public function criterialSubjectsByClass($id_class) {
        $subjects = $this->model
            ->select($this->model->getTable().'.id as id_predmet',
                    $this->model->getTable().'.sagat as sagat',
                    'edu_predmet_name.predmet_'.$this->lang.' as predmet_name',
                    'mektep_teacher.name as name',
                    'mektep_teacher.surname as surname',
            )
            ->leftJoin('edu_predmet_name', $this->model->getTable().'.predmet', '=', 'edu_predmet_name.id')
            ->leftJoin('mektep_class', $this->model->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('mektep_teacher', $this->model->getTable().'.id_teacher', '=', 'mektep_teacher.id')
            ->join('edu_predmet_criterial', function($join)
            {
                $join->on($this->model->getTable().'.predmet', '=', 'edu_predmet_criterial.predmet');
                $join->on('mektep_class.class', '=', 'edu_predmet_criterial.class');
                $join->on('mektep_class.edu_language', '=', 'edu_predmet_criterial.edu_language');
            })
            ->where($this->model->getTable().'.id_class', '=', $id_class)
            ->orderBy($this->model->getTable().'.predmet')
            ->get()->all();



        return $subjects;
    }
}
