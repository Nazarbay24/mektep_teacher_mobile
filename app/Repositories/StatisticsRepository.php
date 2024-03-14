<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class StatisticsRepository
{
    public function getStatistics()
    {
        $mektepter = DB::table('mektepter')->count();
        $parents = DB::table('mektep_parents')->where('login', '!=', '')->count();
        $classes = DB::table('mektep_class')->count();
        $students = DB::table('mektep_students')->count();
        $studentsMale = DB::table('mektep_students')->where('pol', '=', 'М')->count();
        $teachers = DB::table('mektep_teacher')->count();
        $teachersMale = DB::table('mektep_teacher')->where('pol', '=', 'М')->count();

        $studentsMaleProc = round(($studentsMale / $students) * 100, 1);
        $studentsFemaleProc = round( 100 - $studentsMaleProc, 1);

        $teachersMaleProc = round(($teachersMale / $teachers) * 100, 1);
        $teachersFemaleProc = round( 100 - $teachersMaleProc, 1);

        return [
            'schools' => $mektepter,
            'parents' => $parents,
            'classes' => $classes,
            'students' => $students,
            'students_male' => $studentsMaleProc.'%',
            'students_female' => $studentsFemaleProc.'%',
            'teachers' => $teachers,
            'teachers_male' => $teachersMaleProc.'%',
            'teachers_female' => $teachersFemaleProc.'%',
        ];
    }
}
