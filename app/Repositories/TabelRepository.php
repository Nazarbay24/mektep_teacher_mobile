<?php

namespace App\Repositories;

use App\Models\Chetvert;
use App\Models\ClassSubgroup;
use App\Models\CriterialMark;
use App\Models\Journal;
use App\Models\Predmet;
use App\Models\PredmetCriterial;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;

class TabelRepository
{
    protected $chetvertModel;
    protected $predmetModel;
    protected $criterialMarkModel;
    protected $journalModel;
    protected $lang;

    public function __construct(Chetvert $chetvertModel,
                                Predmet $predmetModel,
                                CriterialMark $criterialMarkModel,
                                Journal $journalModel)
    {
        $this->chetvertModel = $chetvertModel;
        $this->predmetModel = $predmetModel;
        $this->criterialMarkModel = $criterialMarkModel;
        $this->journalModel = $journalModel;

        if (app()->getLocale() == 'ru') $this->lang = 'rus';
        else if (app()->getLocale() == 'kk') $this->lang = 'kaz';
    }

    public function init(int $id_mektep)
    {
        $this->journalModel->init($id_mektep);
        $this->chetvertModel->init($id_mektep);
        $this->criterialMarkModel->init($id_mektep);
    }


    public function chetvertTabel($id_predmet)
    {
        $predmet = $this->getPredmet($id_predmet);
        $studentsList = $this->getStudentsList($predmet['id_class'], $predmet['subgroup'], $predmet['id_subgroup']);

        $chetvertMarks = Schema::hasTable($this->chetvertModel->getTable()) ? $this->chetvertModel
            ->where('id_class', '=', $predmet['id_class'])
            ->where('id_predmet', '=', $predmet['id_predmet'])
            ->orderBy('chetvert_nomer')
            ->get()->all()
        : null;

        if ($chetvertMarks) {
            foreach ($chetvertMarks as $mark) {
                $key = array_search($mark['id_student'], array_column($studentsList, 'id'));

                $studentsList[$key][$mark['chetvert_nomer']] = $mark['mark'];
            }
        }


        return [
            'predmet_name' => $predmet['predmet_name'],
            'class' => $predmet['class'],
            'sagat' => $predmet['sagat'],
            'students_list' => $studentsList,
        ];
    }


    /**
     * @throws \Exception
     */
    public function criterialTabel($id_predmet, $chetvert)
    {
        $predmet = $this->getPredmet($id_predmet);
        $studentsList = $this->getStudentsList($predmet['id_class'], $predmet['subgroup'], $predmet['id_subgroup']);
        $criterialMarks = $this->getCriterialMarks($predmet['id_class'], $predmet['id_predmet']);
        $predmetCriterial = $this->getPredmetCriterial($predmet['class_num'], $predmet['predmet'], $predmet['edu_language']);
        if (!$predmetCriterial) throw new \Exception('Not found',404);

        if($predmet['class_num'] == 1){
            $mark2 = range(0,20);
            $mark3 = range(21,50);
            $mark4 = range(51,80);
            $mark5 = range(81,100);
        }else{
            $mark2 = range(0,39);
            $mark3 = range(40,64);
            $mark4 = range(65,84);
            $mark5 = range(85,100);
        }

        // максимальные баллы за четверть
        $criterialMax[1] = json_decode($predmet['max_ch_1']);
        $criterialMax[2] = json_decode($predmet['max_ch_2']);
        $criterialMax[3] = json_decode($predmet['max_ch_3']);
        $criterialMax[4] = json_decode($predmet['max_ch_4']);

        if( $chetvert < 5 && !is_array($criterialMax[$chetvert]) ) {
//            throw new \Exception(__('СОр, СОч за этот четверть не настроено'),404);

            return [
                'predmet_name' => $predmet['predmet_name'],
                'class' => $predmet['class'],
                'sagat' => $predmet['sagat'],
                'chetvert' => $chetvert,
                'is_half_year' => false,
                'sor' => [
                    [
                        'sor_num' => 1,
                        'sor_max' => 1,
                    ]
                ],
                'soch' => false,
                'soch_max' => 0,
                'students_list' => $studentsList,
            ];
        }

        // количество СОР за четверть
        $sorCount[1] = is_array($criterialMax[1]) ? count($criterialMax[1])-1 : 0;
        $sorCount[2] = is_array($criterialMax[2]) ? count($criterialMax[2])-1 : 0;
        $sorCount[3] = is_array($criterialMax[3]) ? count($criterialMax[3])-1 : 0;
        $sorCount[4] = is_array($criterialMax[4]) ? count($criterialMax[4])-1 : 0;

//        $sorCount[1] = $predmetCriterial['num_sor_1'];
//        $sorCount[2] = $predmetCriterial['num_sor_2'];
//        $sorCount[3] = $predmetCriterial['num_sor_3'];
//        $sorCount[4] = $predmetCriterial['num_sor_4'];

        // количество СОЧ за четверть
        $sochCount[1] = is_array($criterialMax[1]) && $criterialMax[1][0] != null ? 1 : 0;
        $sochCount[2] = is_array($criterialMax[2]) && $criterialMax[2][0] != null ? 1 : 0;
        $sochCount[3] = is_array($criterialMax[3]) && $criterialMax[3][0] != null ? 1 : 0;
        $sochCount[4] = is_array($criterialMax[4]) && $criterialMax[4][0] != null ? 1 : 0;

        $sochCountForGodovoy = false;
        foreach ($sochCount as $item) {
            if($item == 1) $sochCountForGodovoy = true;
        }

//        $sochCount[1] = $predmetCriterial['num_soch_1'];
//        $sochCount[2] = $predmetCriterial['num_soch_2'];
//        $sochCount[3] = $predmetCriterial['num_soch_3'];
//        $sochCount[4] = $predmetCriterial['num_soch_4'];


// ЧЕТВЕРТНОЙ начало *******************
        if ($chetvert < 5)
        {
            $isHalfYear = ($chetvert == 2 || $chetvert == 4) && $sochCount[$chetvert-1] == 0 ? true : false; // полугодие ли этот четверть
            $formativeMarks = $this->getFormativeMarks($predmet['id_class'], $predmet['id_predmet'], $chetvert, $isHalfYear);

            $sochMax = $sochCount[$chetvert] > 0 ? $criterialMax[$chetvert][0] : 0;

            if ($isHalfYear) {  // максимально возможный балл всех СОР за четверть, если это полугодие то за 2 четверти
                if ($sochCount[$chetvert] > 0) {
                    $sorMaxAll = abs((array_sum($criterialMax[$chetvert]) + array_sum($criterialMax[$chetvert-1])) - $sochMax);
                }
                else {
                    $sorMaxAll = abs((array_sum($criterialMax[$chetvert]) + array_sum($criterialMax[$chetvert-1])));
                }
            }
            else {
                if ($sochCount[$chetvert] > 0) {
                    $sorMaxAll = abs(array_sum($criterialMax[$chetvert]) - $sochMax);
                }
                else {
                    if(is_array($criterialMax[$chetvert])) {
                        $sorMaxAll = abs(array_sum($criterialMax[$chetvert]));
                    }
                    else {
                        $sorMaxAll = 0;
                    }
                }
            }


            foreach ($studentsList as $student_key => $student) { // тут начинается основная логика вычисления процентов четверти
                $mark = 0;
                $sorTotalGrade = 0;
                $sochGrade = null;
                $totalProc = null;

                for ($i = 0; $i < $sorCount[$chetvert]; $i++) { // суммируем все баллы СОР за четверть
                    if (isset($criterialMarks[$student['id']][$chetvert][$i+1])) {
                        $sorTotalGrade = $sorTotalGrade + $criterialMarks[$student['id']][$chetvert][$i+1];
                    }
                }
                if ($isHalfYear) { // если это полугодие прибавляем к СОР баллы предедущего четверти
                    for ($i = 0; $i < $sorCount[$chetvert-1]; $i++) {
                        if (isset($criterialMarks[$student['id']][$chetvert-1][$i+1])) {
                            $sorTotalGrade = $sorTotalGrade + $criterialMarks[$student['id']][$chetvert-1][$i+1];
                        }
                    }
                }

                if (isset($criterialMarks[$student['id']][$chetvert][0])) { // если есть получаем бал СОЧ
                    $sochGrade = $criterialMarks[$student['id']][$chetvert][0];
                }


                if ($isHalfYear || $sochCount[$chetvert] > 0) // Вычисляем проценты если это полугодие или есть СОЧ, иначе просто показываем оценки СОР
                {
                    $formativeProc = null;
                    if (array_key_exists($student['id'],$formativeMarks )) {
                        $formativeProc = round(number_format((($formativeMarks[$student['id']]/10) * 100 * ($sochCount[$chetvert] > 0 ? 0.25 : 0.5)), 1, '.', ''));
                    }
                    $sorProc = round(number_format((($sorTotalGrade/$sorMaxAll)*100 * ($sochCount[$chetvert] > 0 ? 0.25 : 0.5)), 1, '.', ''));

                    if ($sochCount[$chetvert] > 0) { // если есть СОЧ за четверть вычисляем суммарный проц с вместе с СОЧ, иначе без СОЧ
                        if ($sochGrade) { // если есть оценка СОЧ у студента
                            $sochProc = round(number_format((($sochGrade/$sochMax)*100 * 0.5), 1, '.', ''));
                            $totalProc = round(number_format(($formativeProc + $sorProc + $sochProc), 1, '.', '')); // суммарный проц с СОЧ

                            $studentsList[$student_key]['soch_grade'] = $sochGrade;
                            $studentsList[$student_key]['soch_proc'] = $sochProc.' %';
                        }
                        else {
                            $studentsList[$student_key]['soch_grade'] = 0;
                            $studentsList[$student_key]['soch_proc'] = '0 %';
                        }
                    }
                    else { // суммарный проц без СОЧ
                        $totalProc = round(number_format(($formativeProc + $sorProc), 1, '.', ''));
                    }


                    // оцениваем если студенту выставлена оценка СОЧ или за полугодие СОЧ не оценивается
                    if ($totalProc && (($sochCount[$chetvert] > 0 && is_numeric($sochGrade)) || $sochCount[$chetvert] == 0))
                    {
                        if     (in_array($totalProc, $mark2)) $mark = 2;
                        elseif (in_array($totalProc, $mark3)) $mark = 3;
                        elseif (in_array($totalProc, $mark4)) $mark = 4;
                        elseif (in_array($totalProc, $mark5)) $mark = 5;
                    }

                    if (array_key_exists($student['id'],$formativeMarks )) {
                        $studentsList[$student_key]['formative_grade'] = $formativeMarks[$student['id']];
                    }
                    $studentsList[$student_key]['formative_proc'] = $formativeProc ? $formativeProc.' %' : '0 %';
                    $studentsList[$student_key]['sor_proc'] = $sorProc ? $sorProc.' %' : '0 %';
                    $studentsList[$student_key]['total_proc'] = $totalProc ? $totalProc.' %' : '0 %';
                    $studentsList[$student_key]['grade'] = strval($mark);
                } // конец вычисления

                // оценки СОР
                $studentsList[$student_key]['sor'] = [];
                for ($i = 0; $i < $sorCount[$chetvert]; $i++) {
                    if (isset($criterialMarks[$student['id']][$chetvert][$i+1])) {
                        $studentsList[$student_key]['sor'][] = strval($criterialMarks[$student['id']][$chetvert][$i+1]);
                    }
                    else {
                        $studentsList[$student_key]['sor'][] = '';
                    }
                }
            }

            $sor = []; // максимально возможные баллы за каждый СОР
            for ($i = 0; $i < $sorCount[$chetvert]; $i++) {
                $sor[] = [
                    'sor_num' => $i+1,
                    'sor_max' => $criterialMax[$chetvert][$i+1],
                ];
            }

            //$halfYearSystem = $sochCount[1] > 0 ? false : true;

            return [
                'predmet_name' => $predmet['predmet_name'],
                'class' => $predmet['class'],
                'sagat' => $predmet['sagat'],
                'chetvert' => $chetvert,
                'is_half_year' => $isHalfYear,
                'sor' => $sor,
                'soch' => (bool)$sochCount[$chetvert],
                'soch_max' => $sochMax,
                'students_list' => $studentsList,
            ];
        }
// ЧЕТВЕРТНОЙ конец *****************


// ГОДОВОЙ начало ******************
        else
        {
            $formativeMarks = $this->getFormativeMarks($predmet['id_class'], $predmet['id_predmet'], $chetvert, false, true);

            $sochMax = 0;
            $sorMaxAll = 0;
            for ($chet = 1; $chet < 5; $chet++)
            {
                if ($sochCount[$chet] > 0) {
                    $sochMax = $sochMax + $criterialMax[$chet][0];
                }
                for ($sor = 0; $sor < $sorCount[$chet]; $sor++) {
                    $sorMaxAll = $sorMaxAll + $criterialMax[$chet][$sor+1];
                }
            }


            foreach ($studentsList as $student_key => $student) {
                $mark = 0;
                $sorTotal = 0;
                $sochTotal = null;


                for ($chet = 1; $chet < 5; $chet++) {
                    for ($sor = 0; $sor < $sorCount[$chet]; $sor++) {
                        if (isset($criterialMarks[$student['id']][$chet][$sor+1])) {
                            $sorTotal = $sorTotal + $criterialMarks[$student['id']][$chet][$sor+1];
                        }
                    }
                }

                if ($sochCountForGodovoy) {
                    for ($chet = 1; $chet < 5; $chet++) {
                        if (isset($criterialMarks[$student['id']][$chet][0])) {
                            $sochTotal = $sochTotal + $criterialMarks[$student['id']][$chet][0];
                        }
                    }
                }

                if (array_key_exists($student['id'],$formativeMarks )) {
                    $formativeProc = round(number_format((($formativeMarks[$student['id']] / 10) * 100 * ($sochCountForGodovoy ? 0.25 : 0.5)), 1, '.', ''), 1);
                }
                $sorProc = round(number_format((($sorTotal / $sorMaxAll) * 100 * ($sochCountForGodovoy ? 0.25 : 0.5)), 1, '.', ''), 1);

                if ($sochTotal) {
                    $sochProc = round(number_format((($sochTotal / $sochMax) * 100 * 0.5), 1, '.', ''), 1);
                    $totalProc = round(number_format(($formativeProc + $sorProc + $sochProc), 1, '.', ''));

                    $studentsList[$student_key]['soch_grade'] = intval($sochTotal);
                    $studentsList[$student_key]['soch_proc'] = $sochProc.' %';
                } else {
                    $totalProc = round(number_format(($formativeProc + $sorProc), 1, '.', ''));
                }


                if (in_array($totalProc, $mark2)) $mark = 2;
                elseif (in_array($totalProc, $mark3)) $mark = 3;
                elseif (in_array($totalProc, $mark4)) $mark = 4;
                elseif (in_array($totalProc, $mark5)) $mark = 5;

                if (array_key_exists($student['id'],$formativeMarks )) {
                    $studentsList[$student_key]['formative_grade'] = $formativeMarks[$student['id']];
                }
                else {
                    $studentsList[$student_key]['formative_grade'] = '0';
                }
                $studentsList[$student_key]['sor_grade'] = strval($sorTotal);
                $studentsList[$student_key]['formative_proc'] = $formativeProc ? $formativeProc . ' %' : '0 %';
                $studentsList[$student_key]['sor_proc'] = $sorProc ? $sorProc . ' %' : '0 %';
                $studentsList[$student_key]['total_proc'] = $totalProc ? $totalProc . ' %' : '0 %';
                $studentsList[$student_key]['grade'] = strval($mark);
            }

            $halfYearSystem = $sochCount[1] > 0 ? false : true;
            return [
                'predmet_name' => $predmet['predmet_name'],
                'class' => $predmet['class'],
                'sagat' => $predmet['sagat'],
                'chetvert' => $chetvert,
                'is_half_year' => $halfYearSystem,
                'soch' => (bool)$sochCount[2],
                'soch_max' => strval($sochMax),
                'sor_max' => strval($sorMaxAll),
                'students_list' => $studentsList,
            ];
        }
// ГОДОВОЙ конец ******************



    }






    public function getCriterialMarks($id_class, $id_predmet)
    {
        $criterialMarksQuery = $this->criterialMarkModel
            ->where('id_class', '=', $id_class)
            ->where('id_predmet', '=', $id_predmet)
            ->get()->all();

        $criterialMarks = [];
        foreach ($criterialMarksQuery as $item) {
            $criterialMarks[$item['id_student']][$item['chetvert']][$item['razdel']] = $item['student_score'];
        }

        return $criterialMarks;
    }

    public function getPredmetCriterial($class_num, $predmet, $edu_language)
    {
        return PredmetCriterial::
                    where('class', '=', $class_num)
                    ->where('predmet', '=', $predmet)
                    ->where('edu_language', '=', $edu_language)
                    ->first();
    }

    public function getPredmet($id_predmet)
    {
        $predmet = $this->predmetModel
            ->select(
                $this->predmetModel->getTable().'.id_class as id_class',
                $this->predmetModel->getTable().'.id as id_predmet',
                $this->predmetModel->getTable().'.sagat as sagat',
                $this->predmetModel->getTable().'.predmet as predmet',
                $this->predmetModel->getTable().'.subgroup as subgroup',
                $this->predmetModel->getTable().'.id_subgroup as id_subgroup',
                $this->predmetModel->getTable().'.max_ch_1 as max_ch_1',
                $this->predmetModel->getTable().'.max_ch_2 as max_ch_2',
                $this->predmetModel->getTable().'.max_ch_3 as max_ch_3',
                $this->predmetModel->getTable().'.max_ch_4 as max_ch_4',
                'mektep_class.class as class',
                'mektep_class.group as group',
                'mektep_class.edu_language as edu_language',
                'edu_predmet_name.predmet_'.$this->lang.' as predmet_name')
            ->leftJoin('mektep_class', $this->predmetModel->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('edu_predmet_name', 'mektep_predmet.predmet', '=', 'edu_predmet_name.id')
            ->where($this->predmetModel->getTable().'.id', '=', $id_predmet)
            ->first();
        if (!$predmet) throw new \Exception('Not found',404);

        $predmet['class_num'] = $predmet['class'];
        $predmet['class'] = $predmet['class'].'«'.$predmet['group'].'»';
        unset($predmet['group']);

        return $predmet;
    }

    public function getStudentsList($id_class, $subgroup, $id_subgroup)
    {
        $studentsList = Student::
        select('id',
            'name',
            'surname',
            'lastname')
            ->where('id_class', '=', $id_class)
            ->orderBy('surname_latin')
            ->get()->all();
        if (!$studentsList) throw new \Exception('Not found',404);

        if ($id_subgroup > 0) {
            $subgroup = ClassSubgroup::select('group_students_'.$subgroup.' as ids')->where('id', '=', $id_subgroup)->first();
            $subgroup_students = json_decode($subgroup['ids']);
        }

        $studentsListWithFIO = [];
        foreach ($studentsList as $key => $item) {
            if ($id_subgroup > 0) {
                if (in_array($item['id'], $subgroup_students)) {
                    $studentsListWithFIO[] = [
                        "id" => (int)$item['id'],
                        "fio" => $item['surname'].' '.$item['name'],
                    ];
                }
            }
            else {
                $studentsListWithFIO[] = [
                    "id" => (int)$item['id'],
                    "fio" => $item['surname'].' '.$item['name'],
                ];
            }
        }

        return $studentsListWithFIO;
    }

    public function getFormativeMarks($id_class, $id_predmet, $chetvert, $isHalfYear = false, $isYear = false)
    {
        if ($isYear) {
            $allMarksQuery = $this->journalModel
                ->where('jurnal_class_id', '=', $id_class)
                ->where('jurnal_predmet', '=', $id_predmet)
                ->get()->all();
        }
        else {
            $chetvertDates = config('mektep_config.chetvert');
            $chetvertStart = $isHalfYear ? $chetvertDates[$chetvert-1]['start'] : $chetvertDates[$chetvert]['start'];
            $chetvertEnd = $chetvertDates[$chetvert]['end'];

            $allMarksQuery = $this->journalModel
                ->where('jurnal_class_id', '=', $id_class)
                ->where('jurnal_predmet', '=', $id_predmet)
                ->where('jurnal_date', '>=', $chetvertStart)
                ->where('jurnal_date', '<=', $chetvertEnd)
                ->get()->all();
        }


        $allMarks = [];
        foreach ($allMarksQuery as $item) {
            if ($item['jurnal_mark'] >= 1 && $item['jurnal_mark'] <= 10) {
                $allMarks[$item['jurnal_student_id']]['marks'][] = $item['jurnal_mark'];
            }
        }
        foreach ($allMarks as $id => $marks) {
            $allMarks[$id] = strval(round(array_sum($marks['marks']) / count($marks['marks']), 1));
        }

        return $allMarks;
    }
}
