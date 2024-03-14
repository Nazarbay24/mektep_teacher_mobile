<?php

namespace App\Repositories;

use App\Models\Chetvert;
use App\Models\ClassSubgroup;
use App\Models\Diary;
use App\Models\Journal;
use App\Models\Journal as Model;
use App\Models\Predmet;
use App\Models\PredmetCriterial;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;


class JournalRepository
{
    protected $model;
    protected $diaryModel;
    protected $predmetModel;
    protected $chetvertModel;
    protected $journalModel;
    protected $lang;

    public function __construct(Model $model,
                                Diary $diaryModel,
                                Predmet $predmetModel,
                                Journal $journalModel,
                                Chetvert $chetvertModel)
    {
        $this->model = $model;
        $this->diaryModel = $diaryModel;
        $this->predmetModel = $predmetModel;
        $this->journalModel = $journalModel;
        $this->chetvertModel = $chetvertModel;

        if (app()->getLocale() == 'ru') $this->lang = 'rus';
        else if (app()->getLocale() == 'kk') $this->lang = 'kaz';
    }

    public function init(int $id_mektep)
    {
        $this->model->init($id_mektep);
        $this->diaryModel->init($id_mektep);
        $this->journalModel->init($id_mektep);
        $this->chetvertModel->init($id_mektep);
    }


    public function journalView($id_predmet, $id_teacher, $chetvert, $isCurrentChetvert, $canMark) {
        $predmet = $this->getPredmet($id_predmet, $id_teacher);
        $studentsList = $this->getStudentsList($predmet['id_mektep'], $predmet['id_class'], $predmet['subgroup'], $predmet['id_subgroup']);
        $datesMarksFormative = $this->getDatesMarksFormative($chetvert, $isCurrentChetvert, $predmet['id_predmet'], $predmet['id_class']);

        if ($canMark) {
            $chetvertDates = config('mektep_config.chetvert');
            $diary = $this->diaryModel
                ->where('id_predmet', '=', $predmet['id_predmet'])
                ->where('date', '>=', $chetvertDates[$chetvert]['start'])
                ->where('date', '<=', date("Y-m-d"))
                ->orderBy('date', 'desc')
                ->first();

            if (!$diary) {
                $canMark = false;
            }
        }


        return [
            'chetvert' => $chetvert,
            'current_date' => $datesMarksFormative['currentDate'],
            'can_mark' => $canMark,
            'sagat' => $predmet['sagat'],
            'id_class' => $predmet['id_class'],
            'id_predmet' => $predmet['id_predmet'],
            'subgroup' => $predmet['subgroup'],
            'class' => $predmet['class'],
            'predmet_name' => $predmet['predmet_name'],
            'dates' => $datesMarksFormative['journalDates'],
            'marks' => $datesMarksFormative['journalMarks'],
            'formative_marks' => $datesMarksFormative['formativeMarks'],
            'students_list' => $studentsList,
        ];
    }


    public function journalEdit($id_predmet, $id_teacher, $chetvert, $date, $isCurrentChetvert, $canMark) {
        $predmet = $this->getPredmet($id_predmet, $id_teacher);
        $studentsList = $this->getStudentsList($predmet['id_mektep'], $predmet['id_class'], $predmet['subgroup'], $predmet['id_subgroup']);
        $datesMarksFormative = $this->getDatesMarksFormative($chetvert, $isCurrentChetvert, $predmet['id_predmet'], $predmet['id_class']);

        $chetvertDates = config('mektep_config.chetvert');
        $holidays = config('mektep_config.holidays');
        if (!$date) {
            $endDate = date("Y-m-d") < $chetvertDates[$chetvert]['end'] ? date("Y-m-d") : $chetvertDates[$chetvert]['end'];

            $diary = $this->diaryModel
                ->where('id_predmet', '=', $predmet['id_predmet'])
                ->where('date', '>=', $chetvertDates[$chetvert]['start'])
                ->where('date', '<=', $endDate)
                ->orderBy('date', 'desc')
                ->first();
        }
        else {
            $diary = $this->diaryModel
                ->where('id_predmet', '=', $predmet['id_predmet'])
                ->where('date', '=', $date)
                ->first();
        }

        $studentsChetvertMarksQuery = Schema::hasTable($this->chetvertModel->getTable()) ? $this->chetvertModel
            ->select('id_student')
            ->where('id_predmet', '=', $predmet['id_predmet'])
            ->where('id_class', '=', $predmet['id_class'])
            ->where('chetvert_nomer', '=', $chetvert)
            ->get()->all()
        : null;

        $studentsChetvertMarks = [];
        if ($studentsChetvertMarksQuery) {
            foreach ($studentsChetvertMarksQuery as $item) {
                $studentsChetvertMarks[$item['id_student']] = true;
            }
        }


        foreach ($studentsList as $key => $student) {

            if ($diary['opened'] == 0) {
                $studentsList[$key]['can_mark'] = false;
            }
            else {
                $studentsList[$key]['can_mark'] = array_key_exists($student['id'], $studentsChetvertMarks) ? false : true;
            }


            foreach ($datesMarksFormative['journalMarks'] as $date) {

                if ($date['date'] == $diary['date']) {
                    foreach ($date['lessons'] as $lesson) {

                        if ($lesson['lesson_num'] == $diary['number']) {
                            foreach ($lesson['grades'] as $grade) {

                                if ($grade['id_student'] == $student['id']) {
                                    $studentsList[$key]['grade'] = $grade['grade'];
                                }

                            }
                        }

                    }
                }

            }

            foreach ($datesMarksFormative['formativeMarks'] as $grade) {
                if ($grade['id_student'] == $student['id']) {
                    $studentsList[$key]['formative_grade'] = $grade['grade'];
                }
            }
        }




        $dayOfMonth = date('d', strtotime($diary['date']));
        $month = date('m', strtotime($diary['date']));
        $selectedDay = ltrim($dayOfMonth, 0).' '.__('m_'.$month);

        $dates = [];
        foreach ($datesMarksFormative['journalDates'] as $item) {
            $dayOfMonth = date('d', strtotime($item['date']));
            $month = date('m', strtotime($item['date']));

            $item['text'] = ltrim($dayOfMonth, 0).' '.__('m_'.$month);
            $dates[] = $item;
        }

        $predmet['is_criterial'] = $this->getBoolPredmetIsCriterial($predmet['id_predmet']);

        $journal = [
            'date' => $diary['date'],
            'lesson_num' => $diary['number'],
            'chetvert' => __('q_'.$chetvert),
            'selected_day' => $selectedDay,
            'tema_selected' => (bool)$diary['opened'],
            'tema' => $diary['tema'],
            'sagat' => $predmet['sagat'],
            'id_class' => $predmet['id_class'],
            'id_predmet' => $predmet['id_predmet'],
            'subgroup' => $predmet['subgroup'],
            'class' => $predmet['class'],
            'predmet_name' => $predmet['predmet_name'],
            'is_criterial' => $predmet['is_criterial'],
            'dates' => $dates,
            'students_list' => $studentsList,
        ];

        return $journal;
    }


    public function setMark($id_teacher, $date, $id_predmet, $id_student, $id_class, $lessonNum, $mark) {
//        $checkMark = $this->model->select('id')
//        ->where('jurnal_teacher_id', '=', $id_teacher)
//        ->where('jurnal_date', '=', $date)
//        ->where('jurnal_predmet', '=', $id_predmet)
//        ->where('jurnal_student_id', '=', $id_student)
//        ->where('jurnal_class_id', '=', $id_class)
//        ->where('jurnal_lesson_num', '=', $lessonNum)
//        ->first();
//
//        if ($checkMark) return false;

        return $this->model->create([
            'jurnal_date' => $date,
            'jurnal_predmet' => $id_predmet,
            'jurnal_student_id' => $id_student,
            'jurnal_mark' => $mark,
            'jurnal_teacher_id' => $id_teacher,
            'jurnal_lesson' => $lessonNum,
            'jurnal_class_id' => $id_class,
        ]);
    }



    public function getPredmet($id_predmet, $id_teacher) {
        $predmet = $this->predmetModel
            ->select($this->predmetModel->getTable().'.sagat as sagat',
                $this->predmetModel->getTable().'.id_class as id_class',
                $this->predmetModel->getTable().'.id as id_predmet',
                $this->predmetModel->getTable().'.id_mektep as id_mektep',
                $this->predmetModel->getTable().'.subgroup as subgroup',
                'mektep_class.class as class',
                'mektep_class.group as group',
                'edu_predmet_name.predmet_'.$this->lang.' as predmet_name')
            ->leftJoin('mektep_class', $this->predmetModel->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('edu_predmet_name', 'mektep_predmet.predmet', '=', 'edu_predmet_name.id')
            ->where($this->predmetModel->getTable().'.id', '=', $id_predmet)
            ->where($this->predmetModel->getTable().'.id_teacher', '=', $id_teacher)
            ->first();
        if (!$predmet) throw new \Exception('Not found',404);

        $predmet['class'] = $predmet['class'].'«'.$predmet['group'].'»';
        unset($predmet['group']);
        return $predmet;
    }


    public function getStudentsList($id_mektep, $id_class, $subgroup, $id_subgroup) {
        $studentsList = Student::
            select('id',
                'name',
                'surname',
                'lastname')
            ->where('id_mektep', '=', $id_mektep)
            ->where('id_class', '=', $id_class)
            ->orderBy('surname')
            ->get()->all();
        if (!$studentsList) throw new \Exception('Not found',404);

        $studentsListWithFIO = [];
        foreach ($studentsList as $key => $item) {
            $studentsListWithFIO[] = [
                "id" => (int)$item['id'],
                "fio" => $item['surname'].' '.$item['name'],
            ];
        }

        if ($id_subgroup > 0) {
            $subgroup = ClassSubgroup::select('group_students_'.$subgroup.' as ids')->where('id', '=', $id_subgroup);
            $subgroup_students = json_decode($subgroup['ids']);

            foreach ($studentsListWithFIO as $key => $student) {
                if (!in_array($student['id'], $subgroup_students)) {
                    unset($studentsListWithFIO[$key]);
                }
            }
        }

        return $studentsListWithFIO;
    }


    public function getDatesMarksFormative($chetvert, $isCurrentChetvert, $id_predmet, $id_class) {
        $chetvertDates = config('mektep_config.chetvert');
        $holidays = config('mektep_config.holidays');
        $journalDatesQuery = $this->diaryModel
            ->where('id_class', '=', $id_class)
            ->where('id_predmet', '=', $id_predmet)
            ->where('date', '>=', $chetvertDates[$chetvert]['start'])
            ->where('date', '<=', $chetvertDates[$chetvert]['end'])
            ->orderBy('date', 'DESC')
            ->orderBy('number')
            ->get()->all();

        $journalDates = [];
        $currentDate = null;
        foreach($journalDatesQuery as $key => $item) {
            if (!in_array($item['date'], $holidays)) { // заменить на текущую дату
                $journalDates[] = [
                    'date' => $item['date'],
                    'lesson_num' => $item['number'],
                    'text' => date("d.m", strtotime($item['date']))
                ];
            }
            if ($isCurrentChetvert) {
                if ($item['date'] == date("Y-m-d")) { // заменить на текущую дату
                    $currentDate = $item['date'];
                }
            }
        }


        $journalMarksQuery = $this->journalModel
            ->where('jurnal_class_id', '=', $id_class)
            ->where('jurnal_predmet', '=', $id_predmet)
            ->where('jurnal_date', '>=', $chetvertDates[$chetvert]['start'])
            ->where('jurnal_date', '<=', $chetvertDates[$chetvert]['end'])
            ->get()->all();

        $journalMarksArray = [];
        foreach ($journalMarksQuery as $item) {
            $journalMarksArray[$item['jurnal_date']][$item['jurnal_lesson']][$item['jurnal_student_id']] = $item['jurnal_mark'];
        }

        $journalMarks = [];
        foreach ($journalMarksArray as $date => $lessons) {
            $item = [
                'date' => $date,
                'lessons' => []
            ];
            foreach ($lessons as $lesson => $students) {
                $lessonItem = [
                    'lesson_num' => $lesson,
                    'grades' => []
                ];
                foreach ($students as $student_id => $mark) {
                    $lessonItem['grades'][] = [
                        'id_student' => $student_id,
                        'grade' => $mark
                    ];
                }
                $item['lessons'][] = $lessonItem;
            }
            $journalMarks[] = $item;
        }



        $ff = [];
        foreach ($journalMarks as $date) {
            foreach ($date['lessons'] as $lesson) {
                foreach ($lesson['grades'] as $grade) {
                    if ($grade['grade'] >= 1 && $grade['grade'] <= 10) {
                        $ff[$grade['id_student']]['marks'][] = $grade['grade'];
                        $ff[$grade['id_student']]['formative'] = strval(round(array_sum($ff[$grade['id_student']]['marks']) / count($ff[$grade['id_student']]['marks']), 1));
                    }
                }
            }
        }
        $formativeMarks = [];
        foreach ($ff as $id => $mark) {
            $formativeMarks[] = [
                'id_student' => $id,
                'grade' => $mark['formative']
            ];
        }

        return [
            'currentDate' => $currentDate,
            'journalDates' => $journalDates,
            'journalMarks' => $journalMarks,
            'formativeMarks' => $formativeMarks,
        ];
    }


    public function getFormative($id_student, $id_predmet, $chetvert) {
        $chetvertDates = config('mektep_config.chetvert');
        $allMarksQuery = $this->journalModel
            ->where('jurnal_student_id', '=', $id_student)
            ->where('jurnal_predmet', '=', $id_predmet)
            ->where('jurnal_date', '>=', $chetvertDates[$chetvert]['start'])
            ->where('jurnal_date', '<=', $chetvertDates[$chetvert]['end'])
            ->get()->all();

        $allMarks = [];
        foreach ($allMarksQuery as $item) {
            if ($item['jurnal_mark'] >= 1 && $item['jurnal_mark'] <= 10) {
                $allMarks[] = $item['jurnal_mark'];
            }
        }

        $formative = null;
        if (count($allMarks) > 0) {
            $formative = strval(round(array_sum($allMarks) / count($allMarks), 1));
        }

        return $formative;
    }


    public function getBoolPredmetIsCriterial($id_predmet):bool {
        $predmet = $this->predmetModel
            ->select($this->predmetModel->getTable().'.predmet as predmet',
                'mektep_class.class as class',
                'mektep_class.edu_language as edu_language')
            ->leftJoin('mektep_class', $this->predmetModel->getTable().'.id_class', '=', 'mektep_class.id')
            ->where($this->predmetModel->getTable().'.id', '=', $id_predmet)
            ->first();

        $isCriterial = PredmetCriterial::
            where('class', '=', $predmet['class'])
            ->where('predmet', '=', $predmet['predmet'])
            ->where('edu_language', '=', $predmet['edu_language'])
            ->first();

        return (bool)$isCriterial;
    }
}
